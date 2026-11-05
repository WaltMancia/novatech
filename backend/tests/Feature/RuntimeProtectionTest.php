<?php
declare(strict_types=1);

// Se incluye desde tests/run.php (usa makeApp(), req(), register(), send(), jsonBody() y los dobles de prueba).

use NovaTech\Core\{App, Request, Response};
use NovaTech\Core\Support\FileRateLimitStore;
use NovaTech\Http\ErrorResponse;

function fromIp(App $app, string $ip, string $method, string $path, array $headers = [], string $body = ''): Response
{
    return $app->handle(new Request($method, $path, [], $headers, $body, ['REMOTE_ADDR' => $ip]));
}

/** @return list<array{action: string, userId: ?int, meta: array}> */
function anomalies(ArrayAudit $audit): array
{
    return array_values(array_filter($audit->events, static fn (array $e) => $e['action'] === 'security.anomaly'));
}

echo "\nProtección en tiempo de ejecución (RASP ligero)\n";

test('ráfaga de errores (escáner): al llegar al umbral se emite un evento y se bloquea la IP', function () {
    $app = makeApp($s, $u, $audit);
    for ($i = 1; $i <= 30; $i++) {
        same(404, $app->handle(req('GET', "/api/ruta-inexistente-{$i}"))->status, "petición {$i}");
    }
    $blocked = $app->handle(req('GET', '/api/health'));
    same(429, $blocked->status);
    same('temporarily_blocked', jsonBody($blocked)['error']['code']);
    check((int) $blocked->header('Retry-After') > 0, 'Debe indicar cuándo reintentar');

    $events = anomalies($audit);
    same(1, count($events), 'Un solo evento por ventana');
    same('anomaly.error_burst', $events[0]['meta']['type']);
});

test('el bloqueo es por IP: otra IP sigue operando con normalidad', function () {
    $app = makeApp();
    for ($i = 0; $i < 30; $i++) {
        $app->handle(req('GET', "/api/x{$i}"));
    }
    same(429, $app->handle(req('GET', '/api/health'))->status);
    same(200, fromIp($app, '10.9.9.9', 'GET', '/api/health')->status);
});

test('el 401 anónimo de /auth/me no cuenta (el SPA lo consulta al cargar)', function () {
    $app = makeApp($s, $u, $audit);
    for ($i = 0; $i < 60; $i++) {
        same(401, $app->handle(req('GET', '/api/auth/me'))->status);
    }
    same(200, $app->handle(req('GET', '/api/health'))->status);
    same([], anomalies($audit));
});

test('el tráfico legítimo no dispara ninguna alerta', function () {
    $app = makeApp($s, $u, $audit);
    register($app);
    for ($i = 0; $i < 40; $i++) {
        same(200, $app->handle(req('GET', '/api/products'))->status);
    }
    same(200, $app->handle(req('GET', '/api/auth/me'))->status);
    same([], anomalies($audit));
});

test('enumeración de pedidos ajenos (BOLA): 8 aciertos fallidos bloquean y se registra el usuario', function () {
    $app = makeApp($s, $u, $audit);
    register($app);
    for ($id = 1; $id <= 8; $id++) {
        same(404, $app->handle(req('GET', "/api/orders/{$id}"))->status);
    }
    same(429, $app->handle(req('GET', '/api/orders/9'))->status);

    $events = anomalies($audit);
    same(1, count($events));
    same('anomaly.idor_enumeration', $events[0]['meta']['type']);
    same(1, $events[0]['userId'], 'Debe identificar al usuario autenticado');
});

test('ráfaga de fallos CSRF: 5 intentos sin token bloquean la IP', function () {
    $app = makeApp($s, $u, $audit);
    for ($i = 0; $i < 5; $i++) {
        same(403, $app->handle(req('POST', '/api/auth/login', ['Content-Type' => 'application/json'], '{}'))->status);
    }
    same(429, $app->handle(req('GET', '/api/health'))->status);
    same('anomaly.csrf_burst', anomalies($audit)[0]['meta']['type']);
});

test('una petición bloqueada no genera eventos adicionales ni afecta a las demás IP', function () {
    $app = makeApp($s, $u, $audit);
    for ($i = 0; $i < 45; $i++) {
        $app->handle(req('GET', "/api/y{$i}"));
    }
    same(1, count(anomalies($audit)));
});

test('FileRateLimitStore::peek no consume intentos y respeta la expiración', function () {
    $store = new FileRateLimitStore(sys_get_temp_dir() . '/nt_peek_' . bin2hex(random_bytes(4)));
    same(0, $store->peek('k')['count']);
    $store->hit('k', 1);
    $store->hit('k', 1);
    same(2, $store->peek('k')['count']);
    same(2, $store->peek('k')['count'], 'peek no debe incrementar');
    sleep(2);
    same(0, $store->peek('k')['count'], 'La ventana vencida cuenta como vacía');
});

test('ErrorResponse::code extrae el código y tolera cuerpos que no son de error', function () {
    same('forbidden', ErrorResponse::code(Response::json(['error' => ['code' => 'forbidden', 'message' => 'x']], 403)));
    same(null, ErrorResponse::code(Response::json(['ok' => true])));
    same(null, ErrorResponse::code(new Response(500, [], '<html>')));
    same(null, ErrorResponse::code(Response::json(['error' => 'texto'], 400)));
});
