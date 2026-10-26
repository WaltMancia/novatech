<?php
declare(strict_types=1);

// Se incluye desde tests/run.php (usa send(), addToCart(), checkout(), register() y los dobles de prueba).

use NovaTech\Core\{App, Request, Response};
use NovaTech\Core\Support\ArraySession;
use NovaTech\Domain\{OrderStatus, Role, User};

/** Registra un usuario y lo promueve a administrador. */
function loginAdmin(App $app, InMemoryUsers $users): void
{
    register($app, 'admin@example.com');
    $u = $users->users[array_key_last($users->users)];
    $users->users[$u->id] = new User($u->id, $u->email, $u->name, Role::Admin, $u->passwordHash);
}

/** GET con parámetros de consulta. */
function getQ(App $app, string $path, array $query): Response
{
    return $app->handle(new Request('GET', $path, $query, [], '', ['REMOTE_ADDR' => '127.0.0.1']));
}

const NEW_PRODUCT = ['sku' => 'NV-TST-01', 'name' => 'Producto de prueba', 'description' => 'Descripción', 'priceCents' => 10000, 'stock' => 7];

echo "\nAdministración: control de acceso\n";

test('toda ruta /api/admin exige sesión (401) y rol admin (403)', function () {
    $routes = [
        ['GET', '/api/admin/products'], ['POST', '/api/admin/products', NEW_PRODUCT],
        ['GET', '/api/admin/products/1'], ['PATCH', '/api/admin/products/1', ['name' => 'Hackeado']],
        ['DELETE', '/api/admin/products/1'], ['GET', '/api/admin/orders'], ['GET', '/api/admin/orders/1'],
        ['POST', '/api/admin/orders/1/cancel', []], ['GET', '/api/admin/users'], ['GET', '/api/admin/audit-logs'],
    ];
    $app = makeApp($session, $users);
    foreach ($routes as $route) {
        [$method, $path] = $route;
        $r = send($app, $method, $path, $route[2] ?? null);
        same(401, $r->status, "anónimo {$method} {$path}");
    }
    register($app, 'cliente@example.com');
    foreach ($routes as $route) {
        [$method, $path] = $route;
        $r = send($app, $method, $path, $route[2] ?? null);
        same(403, $r->status, "cliente {$method} {$path}");
    }
    same([1, 2], array_column(jsonBody($app->handle(req('GET', '/api/products')))['products'], 'id'), 'el cliente no modificó el catálogo');
});

echo "\nAdministración: productos\n";

test('crear producto: 201, auditoría y visible en el catálogo público', function () {
    $app = makeApp($s, $users, $audit);
    loginAdmin($app, $users);
    $r = send($app, 'POST', '/api/admin/products', NEW_PRODUCT + ['imagePath' => '/images/prueba.webp']);
    same(201, $r->status);
    same(true, jsonBody($r)['product']['active']);
    check(in_array('admin.product_created', $audit->actions(), true));
    same(3, count(jsonBody($app->handle(req('GET', '/api/products')))['products']));
});

test('crear producto: validación estricta de cada campo', function () {
    $app = makeApp($s, $users);
    loginAdmin($app, $users);
    $bad = [
        'sku minúscula' => ['sku' => 'nv-bad'], 'sku con espacios' => ['sku' => 'NV BAD 1'],
        'precio cero' => ['priceCents' => 0], 'precio negativo' => ['priceCents' => -5],
        'precio decimal' => ['priceCents' => 10.5], 'stock negativo' => ['stock' => -1],
        'nombre corto' => ['name' => 'A'], 'imagen javascript:' => ['imagePath' => 'javascript:alert(1)'],
        'imagen URL externa' => ['imagePath' => 'https://evil.example/x.png'],
        'imagen con ..' => ['imagePath' => '/images/../../etc/passwd'], 'imagen .php' => ['imagePath' => '/images/shell.php'],
        'campo extra id' => ['id' => 99], 'campo extra active' => ['active' => false],
    ];
    foreach ($bad as $label => $override) {
        same(422, send($app, 'POST', '/api/admin/products', $override + NEW_PRODUCT)->status, $label);
    }
});

test('crear producto: SKU duplicado -> 422', function () {
    $app = makeApp($s, $users);
    loginAdmin($app, $users);
    $r = send($app, 'POST', '/api/admin/products', ['sku' => 'NV-LAP-14'] + NEW_PRODUCT);
    same(422, $r->status);
    check(isset(jsonBody($r)['error']['fields']['sku']));
});

test('PATCH modifica solo los campos enviados y registra cuáles', function () {
    $app = makeApp($s, $users, $audit, $products);
    loginAdmin($app, $users);
    $r = send($app, 'PATCH', '/api/admin/products/2', ['priceCents' => 35000, 'stock' => 9]);
    same(200, $r->status);
    $p = jsonBody($r)['product'];
    same([35000, 9, 'Ratón'], [$p['priceCents'], $p['stock'], $p['name']]);
    same('priceCents,stock', end($audit->events)['meta']['fields']);
});

test('PATCH rechaza cuerpo vacío, campos no editables y productos inexistentes', function () {
    $app = makeApp($s, $users);
    loginAdmin($app, $users);
    same(422, send($app, 'PATCH', '/api/admin/products/1', [])->status);
    same(422, send($app, 'PATCH', '/api/admin/products/1', ['sku' => 'NV-OTRO-1'])->status);
    same(422, send($app, 'PATCH', '/api/admin/products/1', ['priceCents' => 'gratis'])->status);
    same(404, send($app, 'PATCH', '/api/admin/products/999', ['name' => 'Fantasma'])->status);
});

test('DELETE desactiva (baja lógica): sale del catálogo pero el admin lo sigue viendo', function () {
    $app = makeApp($s, $users, $audit);
    loginAdmin($app, $users);
    same(204, send($app, 'DELETE', '/api/admin/products/1')->status);
    same([2], array_column(jsonBody($app->handle(req('GET', '/api/products')))['products'], 'id'));
    $admin = jsonBody($app->handle(req('GET', '/api/admin/products')));
    same(3, $admin['meta']['total']);
    same(false, $admin['products'][0]['active']);
    check(in_array('admin.product_deactivated', $audit->actions(), true));
});

test('paginación: parámetros fuera de rango -> 422', function () {
    $app = makeApp($s, $users);
    loginAdmin($app, $users);
    foreach ([['page' => '0'], ['perPage' => '1000'], ['page' => 'abc'], ['perPage' => '-1']] as $q) {
        same(422, getQ($app, '/api/admin/products', $q)->status, json_encode($q));
    }
    $ok = jsonBody(getQ($app, '/api/admin/products', ['page' => '2', 'perPage' => '1']));
    same(['page' => 2, 'perPage' => 1, 'total' => 3], $ok['meta']);
    same(1, count($ok['products']));
});

echo "\nAdministración: pedidos, usuarios y auditoría\n";

/** Un cliente compra y el administrador queda con sesión (misma sesión simulada). */
function buyThenBecomeAdmin(App $app, ArraySession $session, InMemoryUsers $users): int
{
    register($app, 'cliente@example.com');
    addToCart($app, 1, 2);
    $id = jsonBody(checkout($app))['order']['id'];
    $session->destroy();
    loginAdmin($app, $users);
    return $id;
}

test('el admin lista y filtra pedidos de todos los clientes', function () {
    $app = makeApp($session, $users);
    $id = buyThenBecomeAdmin($app, $session, $users);
    $all = jsonBody($app->handle(req('GET', '/api/admin/orders')));
    same(1, $all['meta']['total']);
    same(1, $all['orders'][0]['userId']);
    same(1, count(jsonBody(getQ($app, '/api/admin/orders', ['status' => 'paid']))['orders']));
    same([], jsonBody(getQ($app, '/api/admin/orders', ['status' => 'cancelled']))['orders']);
    same(422, getQ($app, '/api/admin/orders', ['status' => 'hackeado'])->status);
    same(200, $app->handle(req('GET', "/api/admin/orders/{$id}"))->status);
    same(404, $app->handle(req('GET', '/api/admin/orders/999'))->status);
});

test('cancelar un pedido pagado restaura el stock; cancelarlo otra vez -> 409', function () {
    $app = makeApp($session, $users, $audit, $products);
    $id = buyThenBecomeAdmin($app, $session, $users);
    same(3, $products->items[1]->stock);
    $r = send($app, 'POST', "/api/admin/orders/{$id}/cancel", []);
    same(200, $r->status);
    same('cancelled', jsonBody($r)['order']['status']);
    same(5, $products->items[1]->stock);
    same(409, send($app, 'POST', "/api/admin/orders/{$id}/cancel", [])->status);
    same(5, $products->items[1]->stock, 'La segunda cancelación no debe devolver stock otra vez');
    check(in_array('admin.order_cancelled', $audit->actions(), true));
});

test('no se cancelan pedidos fallidos ni inexistentes', function () {
    $app = makeApp($session, $users, $a, $products, $orders);
    register($app, 'cliente@example.com');
    addToCart($app, 1, 1);
    checkout($app, 'simulated_decline');
    same(OrderStatus::Failed, $orders->rows[1]['status']);
    $session->destroy();
    loginAdmin($app, $users);
    same(409, send($app, 'POST', '/api/admin/orders/1/cancel', [])->status);
    same(404, send($app, 'POST', '/api/admin/orders/999/cancel', [])->status);
    same(5, $products->items[1]->stock);
});

test('listado de usuarios: nunca expone hashes de contraseña', function () {
    $app = makeApp($s, $users);
    loginAdmin($app, $users);
    $r = $app->handle(req('GET', '/api/admin/users'));
    same(200, $r->status);
    check(!str_contains($r->body, 'argon2') && !str_contains($r->body, 'password'), 'No debe exponer hashes');
    same('admin', jsonBody($r)['users'][0]['role']);
});

test('auditoría: lista eventos, filtra por acción y valida el filtro', function () {
    $app = makeApp($s, $users, $audit);
    loginAdmin($app, $users);
    send($app, 'POST', '/api/admin/products', NEW_PRODUCT);
    $all = jsonBody($app->handle(req('GET', '/api/admin/audit-logs')));
    check($all['meta']['total'] >= 2);
    $filtered = jsonBody(getQ($app, '/api/admin/audit-logs', ['action' => 'admin.product_created']));
    same(1, $filtered['meta']['total']);
    same(422, getQ($app, '/api/admin/audit-logs', ['action' => "x' OR 1=1 --"])->status);
});
