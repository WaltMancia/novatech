<?php
declare(strict_types=1);

/**
 * Runner mínimo sin dependencias: php tests/run.php
 * (PHPUnit se integra en la Fase 5 con el pipeline CI.)
 */

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_URL=http://localhost:8080');
putenv('LOG_STREAM=/dev/null');

require __DIR__ . '/../bootstrap/autoload.php';

use NovaTech\Core\{Container, Request, Response, Router, Validator};
use NovaTech\Core\Contracts\{RateLimitStore, Session};
use NovaTech\Core\Exceptions\{HttpException, ValidationException};
use NovaTech\Core\Support\{ArraySession, FileRateLimitStore};
use NovaTech\Domain\{DuplicateEmailException, Role, User};
use NovaTech\Repositories\UserRepository;
use NovaTech\Services\{AuditLog, AuthService, PasswordHasher};

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  \033[31m✗\033[0m {$name}\n      " . $e->getMessage() . "\n";
    }
}

function check(bool $cond, string $msg = 'Aserción falsa'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg ? $msg . ': ' : '') . 'esperado ' . var_export($expected, true) . ', obtenido ' . var_export($actual, true));
    }
}

function fails(callable $fn, string $class = Throwable::class): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($e instanceof $class, 'Excepción inesperada: ' . $e::class);
        return $e;
    }
    throw new RuntimeException('Se esperaba una excepción');
}


final class InMemoryUsers implements UserRepository
{
    /** @var array<int, User> */
    public array $users = [];
    /** @var array<int, int> */
    public array $failed = [];
    /** @var array<int, true> */
    public array $locked = [];

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $u) {
            if ($u->email === $email) {
                return $this->withLock($u);
            }
        }
        return null;
    }

    public function findById(int $id): ?User
    {
        return isset($this->users[$id]) ? $this->withLock($this->users[$id]) : null;
    }

    public function create(string $email, string $name, string $passwordHash, Role $role): User
    {
        if ($this->findByEmail($email) !== null) {
            throw new DuplicateEmailException();
        }
        $id = count($this->users) + 1;
        return $this->users[$id] = new User($id, $email, $name, $role, $passwordHash);
    }

    public function recordFailedLogin(int $id, int $maxAttempts, int $lockSeconds): void
    {
        $this->failed[$id] = ($this->failed[$id] ?? 0) + 1;
        if ($this->failed[$id] >= $maxAttempts) {
            $this->locked[$id] = true;
            $this->failed[$id] = 0;
        }
    }

    public function resetFailedLogins(int $id): void
    {
        $this->failed[$id] = 0;
        unset($this->locked[$id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $u = $this->users[$id];
        $this->users[$id] = new User($u->id, $u->email, $u->name, $u->role, $hash);
    }

    private function withLock(User $u): User
    {
        return new User($u->id, $u->email, $u->name, $u->role, $u->passwordHash, isset($this->locked[$u->id]));
    }
}

final class ArrayAudit implements AuditLog
{
    /** @var list<array{action: string, userId: ?int, meta: array}> */
    public array $events = [];

    public function record(string $action, ?int $userId, string $ip, array $metadata = []): void
    {
        $this->events[] = ['action' => $action, 'userId' => $userId, 'meta' => $metadata];
    }

    public function actions(): array
    {
        return array_column($this->events, 'action');
    }
}

/** Construye una app aislada (sesión y rate limit propios) con rutas de prueba. */
function makeApp(?ArraySession &$session = null, ?InMemoryUsers &$users = null, ?ArrayAudit &$audit = null): NovaTech\Core\App
{
    $session = new ArraySession();
    $users = new InMemoryUsers();
    $audit = new ArrayAudit();
    $store = new FileRateLimitStore(sys_get_temp_dir() . '/nt_test_' . bin2hex(random_bytes(4)));
    $factory = require __DIR__ . '/../bootstrap/app.php';

    return $factory([
        Session::class => $session,
        RateLimitStore::class => $store,
        UserRepository::class => $users,
        AuditLog::class => $audit,
    ], static function (Router $r): void {
        $r->get('/api/admin-only', static fn () => Response::json(['ok' => true]), [NovaTech\Http\Middleware\Authenticate::class, 'role:admin']);
        $r->post('/api/echo', static fn (Request $req) => Response::json(['got' => $req->json()]));
        $r->get('/api/boom', static fn () => throw new RuntimeException('secreto-interno'));
        $r->post('/api/limited', static fn () => Response::json(['ok' => true]), ['throttle:2,60']);
        $r->get('/api/items/{id:\d+}', static fn (Request $req) => Response::json(['id' => (int) $req->param('id')]));
    });
}

function req(string $method, string $path, array $headers = [], string $body = ''): Request
{
    return new Request($method, $path, [], $headers, $body, ['REMOTE_ADDR' => '127.0.0.1']);
}

function jsonBody(Response $r): array
{
    return json_decode($r->body, true, 512, JSON_THROW_ON_ERROR);
}


/** Envía un POST JSON con el token CSRF vigente de la sesión. */
function postJson(NovaTech\Core\App $app, string $path, array $body): Response
{
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    return $app->handle(req('POST', $path, ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token], json_encode($body)));
}

const PASS = 'clave-segura-123';
function register(NovaTech\Core\App $app, string $email = 'ana@example.com'): Response
{
    return postJson($app, '/api/auth/register', ['email' => $email, 'name' => 'Ana Pérez', 'password' => PASS]);
}

echo "\nValidator\n";

test('acepta datos válidos y coacciona enteros en texto', function () {
    $out = Validator::validate(['email' => 'a@b.com', 'qty' => '3'], ['email' => 'required|email', 'qty' => 'required|int|between:1,10']);
    same(['email' => 'a@b.com', 'qty' => 3], $out);
});

test('descarta campos no declarados (anti mass assignment)', function () {
    $out = Validator::validate(['name' => 'Ana', 'role' => 'admin'], ['name' => 'required|string|max:50']);
    same(['name' => 'Ana'], $out);
});

test('rejectUnknown rechaza campos extra', function () {
    $e = fails(fn () => Validator::validate(['name' => 'Ana', 'role' => 'admin'], ['name' => 'required|string'], true), ValidationException::class);
    check(isset($e->fields['role']));
});

test('reporta campos obligatorios, correo inválido y contraseña corta', function () {
    $e = fails(fn () => Validator::validate(['email' => 'no-es-correo', 'password' => 'corta'], [
        'email' => 'required|email', 'password' => 'required|password', 'name' => 'required|string',
    ]), ValidationException::class);
    check(isset($e->fields['email'], $e->fields['password'], $e->fields['name']));
    same(422, $e->status);
});

test('rechaza tipos incorrectos (float/bool como int, número como string)', function () {
    $e = fails(fn () => Validator::validate(['a' => 1.5, 'b' => true, 'c' => 5], ['a' => 'int', 'b' => 'int', 'c' => 'string']), ValidationException::class);
    check(isset($e->fields['a'], $e->fields['b'], $e->fields['c']));
});

test('rechaza caracteres de control y NUL', function () {
    fails(fn () => Validator::validate(['n' => "ab\x00c"], ['n' => 'string']), ValidationException::class);
});

test('in: solo valores de la lista blanca', function () {
    Validator::validate(['r' => 'customer'], ['r' => 'in:customer,admin']);
    fails(fn () => Validator::validate(['r' => 'root'], ['r' => 'in:customer,admin']), ValidationException::class);
});

test('regex en formato array admite "|" en el patrón', function () {
    Validator::validate(['s' => 'ab'], ['s' => ['regex:/^(ab|cd)$/']]);
    fails(fn () => Validator::validate(['s' => 'xx'], ['s' => ['regex:/^(ab|cd)$/']]), ValidationException::class);
});

test('password: máximo 128 para evitar DoS por hashing', function () {
    fails(fn () => Validator::validate(['p' => str_repeat('a', 129)], ['p' => 'password']), ValidationException::class);
});

echo "\nContainer y Router\n";

test('autowiring resuelve dependencias por constructor', function () {
    $c = new Container();
    $c->instance(Session::class, new ArraySession());
    $csrf = $c->get(NovaTech\Core\Security\CsrfTokenManager::class);
    check(strlen($csrf->token()) === 64);
});

test('el router distingue 404 y 405 (con cabecera Allow)', function () {
    $app = makeApp();
    same(404, $app->handle(req('GET', '/api/no-existe'))->status);
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $r = $app->handle(req('PUT', '/api/health', ['X-CSRF-Token' => $token]));
    same(405, $r->status);
    same('GET', $r->header('Allow'));
});

test('parámetros con restricción regex: /items/{id:\d+}', function () {
    $app = makeApp();
    same(['id' => 42], jsonBody($app->handle(req('GET', '/api/items/42'))));
    same(404, $app->handle(req('GET', '/api/items/abc'))->status);
});

echo "\nPipeline HTTP y controles de seguridad\n";

test('GET /api/health responde 200 con cabeceras de seguridad y X-Request-Id', function () {
    $r = makeApp()->handle(req('GET', '/api/health'));
    same(200, $r->status);
    same('nosniff', $r->header('X-Content-Type-Options'));
    same('DENY', $r->header('X-Frame-Options'));
    same('no-store', $r->header('Cache-Control'));
    check(str_contains((string) $r->header('Content-Security-Policy'), "default-src 'none'"));
    check($r->header('X-Request-Id') !== null);
    check($r->header('Strict-Transport-Security') === null, 'HSTS no debe enviarse sobre HTTP');
});

test('HSTS solo bajo HTTPS', function () {
    $r = makeApp()->handle(new Request('GET', '/api/health', [], [], '', ['REMOTE_ADDR' => '127.0.0.1', 'HTTPS' => 'on']));
    check($r->header('Strict-Transport-Security') !== null);
});

test('X-Request-Id malicioso se reemplaza (anti log injection)', function () {
    $r = makeApp()->handle(req('GET', '/api/health', ['X-Request-Id' => "x\nFAKE-LOG"]));
    check(preg_match('/^[a-f0-9]{16}$/', (string) $r->header('X-Request-Id')) === 1);
});

test('POST sin token CSRF -> 403 csrf_mismatch', function () {
    $r = makeApp()->handle(req('POST', '/api/echo', ['Content-Type' => 'application/json'], '{}'));
    same(403, $r->status);
    same('csrf_mismatch', jsonBody($r)['error']['code']);
});

test('POST con token CSRF válido pasa', function () {
    $app = makeApp($session);
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $r = $app->handle(req('POST', '/api/echo', ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token], '{"a":1}'));
    same(200, $r->status);
    same(['got' => ['a' => 1]], jsonBody($r));
});

test('POST con token inválido o de otra sesión -> 403', function () {
    $app = makeApp();
    $app->handle(req('GET', '/api/csrf'));
    $r = $app->handle(req('POST', '/api/echo', ['Content-Type' => 'application/json', 'X-CSRF-Token' => str_repeat('0', 64)], '{}'));
    same(403, $r->status);
});

test('Origin no permitido -> 403 aunque el token sea válido', function () {
    $app = makeApp();
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $r = $app->handle(req('POST', '/api/echo', ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token, 'Origin' => 'https://evil.example'], '{}'));
    same(403, $r->status);
    same('origin_not_allowed', jsonBody($r)['error']['code']);
});

test('Origin permitido (APP_URL) pasa', function () {
    $app = makeApp();
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $r = $app->handle(req('POST', '/api/echo', ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token, 'Origin' => 'http://localhost:8080'], '{}'));
    same(200, $r->status);
});

test('JSON inválido -> 400 y Content-Type incorrecto -> 415', function () {
    $app = makeApp();
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $r = $app->handle(req('POST', '/api/echo', ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token], '{no-json'));
    same(400, $r->status);
    $r = $app->handle(req('POST', '/api/echo', ['Content-Type' => 'text/plain', 'X-CSRF-Token' => $token], '{"a":1}'));
    same(415, $r->status);
});

test('cuerpo demasiado grande -> 413', function () {
    $app = makeApp();
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $big = new Request('POST', '/api/echo', [], ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token], '', ['REMOTE_ADDR' => '127.0.0.1'], true);
    same(413, $app->handle($big)->status);
});

test('excepción interna -> 500 genérico sin filtrar detalles', function () {
    $r = makeApp()->handle(req('GET', '/api/boom'));
    same(500, $r->status);
    check(!str_contains($r->body, 'secreto-interno'), 'No debe filtrar el mensaje interno');
    same('server_error', jsonBody($r)['error']['code']);
});

test('rate limit por ruta: tercera petición -> 429 con Retry-After', function () {
    $app = makeApp($session);
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $h = ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token];
    same(200, $app->handle(req('POST', '/api/limited', $h, '{}'))->status);
    same(200, $app->handle(req('POST', '/api/limited', $h, '{}'))->status);
    $r = $app->handle(req('POST', '/api/limited', $h, '{}'));
    same(429, $r->status);
    check($r->header('Retry-After') !== null);
    same('rate_limited', jsonBody($r)['error']['code']);
});

test('guardarraíl: APP_DEBUG=true en producción aborta el arranque', function () {
    putenv('APP_ENV=production');
    putenv('APP_DEBUG=true');
    try {
        fails(fn () => (require __DIR__ . '/../bootstrap/config.php')(), RuntimeException::class);
    } finally {
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=false');
    }
});


echo "\nAutenticación y autorización\n";

test('Argon2id: hash, verificación, rehash y parámetros del hash señuelo', function () {
    $h = new PasswordHasher();
    $hash = $h->hash('otra-clave-larga-1');
    check(str_starts_with($hash, '$argon2id$'));
    check($h->verify('otra-clave-larga-1', $hash) && !$h->verify('mala', $hash));
    check(!$h->needsRehash($hash));
    check(!$h->needsRehash(PasswordHasher::dummyHash()), 'El hash señuelo debe usar los parámetros vigentes');
    check(password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]));
});

test('registro: 201, rol customer, sin hash en la respuesta y sesión iniciada', function () {
    $app = makeApp($session, $users);
    $r = register($app);
    same(201, $r->status);
    $user = jsonBody($r)['user'];
    same('customer', $user['role']);
    check(!str_contains($r->body, 'password') && !str_contains($r->body, 'argon2'));
    same(1, $session->get(AuthService::SESSION_KEY));
    same(1, $session->regenerations, 'La sesión debe regenerarse');
    check(str_starts_with($users->users[1]->passwordHash, '$argon2id$'));
});

test('registro rechaza el campo "role" (anti escalada por mass assignment)', function () {
    $app = makeApp($s, $users);
    $r = postJson($app, '/api/auth/register', ['email' => 'x@example.com', 'name' => 'Eve', 'password' => PASS, 'role' => 'admin']);
    same(422, $r->status);
    check(isset(jsonBody($r)['error']['fields']['role']));
    same([], $users->users);
});

test('registro: contraseña débil o correo duplicado -> 422', function () {
    $app = makeApp();
    same(422, postJson($app, '/api/auth/register', ['email' => 'a@example.com', 'name' => 'Ana', 'password' => 'corta'])->status);
    same(201, register($app, 'dup@example.com')->status);
    $r = register($app, 'DUP@example.com');
    same(422, $r->status);
    check(isset(jsonBody($r)['error']['fields']['email']));
});

test('login correcto: 200, regenera sesión y rota el token CSRF', function () {
    $app = makeApp($session);
    register($app);
    $session->destroy();
    $before = $session->regenerations;
    $oldToken = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $r = postJson($app, '/api/auth/login', ['email' => 'ANA@example.com', 'password' => PASS]);
    same(200, $r->status);
    same($before + 1, $session->regenerations);
    check($oldToken !== jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'], 'El token CSRF debe rotar');
});

test('login: usuario inexistente y clave errónea devuelven la misma respuesta', function () {
    $app = makeApp($s, $u, $audit);
    register($app);
    $bad = postJson($app, '/api/auth/login', ['email' => 'ana@example.com', 'password' => 'incorrecta-123']);
    $none = postJson($app, '/api/auth/login', ['email' => 'nadie@example.com', 'password' => 'incorrecta-123']);
    same(401, $bad->status);
    same(401, $none->status);
    same($bad->body, $none->body);
    same('invalid_credentials', jsonBody($bad)['error']['code']);
    check(in_array('auth.login_failed', $audit->actions(), true));
});

test('bloqueo tras 5 intentos fallidos: ni la clave correcta entra', function () {
    $app = makeApp($s, $u, $audit);
    register($app);
    for ($i = 0; $i < AuthService::MAX_ATTEMPTS; $i++) {
        same(401, postJson($app, '/api/auth/login', ['email' => 'ana@example.com', 'password' => 'incorrecta-123'])->status);
    }
    $r = postJson($app, '/api/auth/login', ['email' => 'ana@example.com', 'password' => PASS]);
    same(401, $r->status);
    $reasons = array_column(array_column($audit->events, 'meta'), 'reason');
    check(in_array('locked', $reasons, true));
});

test('login rechaza campos extra y cuerpos mal formados', function () {
    $app = makeApp();
    same(422, postJson($app, '/api/auth/login', ['email' => 'a@b.com', 'password' => 'x', 'admin' => true])->status);
    same(422, postJson($app, '/api/auth/login', ['email' => 'a@b.com'])->status);
});

test('/auth/me: 401 sin sesión, 200 con sesión y 401 tras logout', function () {
    $app = makeApp();
    same(401, $app->handle(req('GET', '/api/auth/me'))->status);
    register($app);
    $me = $app->handle(req('GET', '/api/auth/me'));
    same(200, $me->status);
    same('ana@example.com', jsonBody($me)['user']['email']);
    same(204, postJson($app, '/api/auth/logout', [])->status);
    same(401, $app->handle(req('GET', '/api/auth/me'))->status);
});

test('logout exige CSRF y sesión', function () {
    $app = makeApp();
    register($app);
    same(403, $app->handle(req('POST', '/api/auth/logout'))->status);
});

test('autorización por rol: customer -> 403, admin -> 200, anónimo -> 401', function () {
    $app = makeApp($session, $users);
    same(401, $app->handle(req('GET', '/api/admin-only'))->status);
    register($app);
    same(403, $app->handle(req('GET', '/api/admin-only'))->status);
    $users->users[1] = new User(1, 'ana@example.com', 'Ana', Role::Admin, $users->users[1]->passwordHash);
    same(200, $app->handle(req('GET', '/api/admin-only'))->status);
});

test('sesión de un usuario eliminado deja de ser válida', function () {
    $app = makeApp($session, $users);
    register($app);
    $users->users = [];
    same(401, $app->handle(req('GET', '/api/auth/me'))->status);
    same(null, $session->get(AuthService::SESSION_KEY));
});

test('auditoría registra registro, login y logout', function () {
    $app = makeApp($s, $u, $audit);
    register($app);
    postJson($app, '/api/auth/logout', []);
    postJson($app, '/api/auth/login', ['email' => 'ana@example.com', 'password' => PASS]);
    foreach (['user.registered', 'auth.logout', 'auth.login_success'] as $a) {
        check(in_array($a, $audit->actions(), true), "falta {$a}");
    }
});

echo "\n";
echo $failed === 0 ? "\033[32mTodo en verde: {$passed} pruebas.\033[0m\n" : "\033[31m{$failed} fallaron, {$passed} pasaron.\033[0m\n";
exit($failed === 0 ? 0 : 1);
