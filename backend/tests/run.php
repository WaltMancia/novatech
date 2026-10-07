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

/** Construye una app aislada (sesión y rate limit propios) con rutas de prueba. */
function makeApp(?ArraySession &$session = null): NovaTech\Core\App
{
    $session = new ArraySession();
    $store = new FileRateLimitStore(sys_get_temp_dir() . '/nt_test_' . bin2hex(random_bytes(4)));
    $factory = require __DIR__ . '/../bootstrap/app.php';

    return $factory([Session::class => $session, RateLimitStore::class => $store], static function (Router $r): void {
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

echo "\n";
echo $failed === 0 ? "\033[32mTodo en verde: {$passed} pruebas.\033[0m\n" : "\033[31m{$failed} fallaron, {$passed} pasaron.\033[0m\n";
exit($failed === 0 ? 0 : 1);
