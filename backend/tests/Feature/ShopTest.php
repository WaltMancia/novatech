<?php
declare(strict_types=1);

// Se incluye desde tests/run.php (comparte test(), check(), same(), makeApp(), register()...).

use NovaTech\Core\App;
use NovaTech\Core\{Request, Response};
use NovaTech\Domain\OrderStatus;

function send(App $app, string $method, string $path, ?array $body = null): Response
{
    $token = jsonBody($app->handle(req('GET', '/api/csrf')))['csrfToken'];
    $headers = ['X-CSRF-Token' => $token];
    if ($body !== null) {
        $headers['Content-Type'] = 'application/json';
    }
    return $app->handle(req($method, $path, $headers, $body === null ? '' : json_encode($body)));
}

function addToCart(App $app, int $productId, int $quantity): Response
{
    return send($app, 'POST', '/api/cart/items', ['productId' => $productId, 'quantity' => $quantity]);
}

function checkout(App $app, string $method = 'simulated_success'): Response
{
    return send($app, 'POST', '/api/orders', ['paymentMethod' => $method]);
}

echo "\nCatálogo\n";

test('el catálogo es público y oculta productos inactivos', function () {
    $r = makeApp()->handle(req('GET', '/api/products'));
    same(200, $r->status);
    $ids = array_column(jsonBody($r)['products'], 'id');
    same([1, 2], $ids);
});

test('detalle de producto: 200 si está activo, 404 si no existe o está inactivo', function () {
    $app = makeApp();
    same(200, $app->handle(req('GET', '/api/products/1'))->status);
    same(404, $app->handle(req('GET', '/api/products/3'))->status);
    same(404, $app->handle(req('GET', '/api/products/999'))->status);
    same(404, $app->handle(req('GET', '/api/products/1abc'))->status);
});

echo "\nCarrito\n";

test('el carrito exige sesión', function () {
    $app = makeApp();
    same(401, $app->handle(req('GET', '/api/cart'))->status);
    same(401, send($app, 'POST', '/api/cart/items', ['productId' => 1, 'quantity' => 1])->status);
});

test('agregar suma cantidades y calcula el total con precios del servidor', function () {
    $app = makeApp();
    register($app);
    same(201, addToCart($app, 1, 1)->status);
    addToCart($app, 2, 2);
    $cart = jsonBody(addToCart($app, 2, 1));
    same(749900 + 3 * 29900, $cart['totalCents']);
    same(2, count($cart['items']));
});

test('el cliente no puede imponer precios: campo "price" rechazado', function () {
    $app = makeApp();
    register($app);
    $r = send($app, 'POST', '/api/cart/items', ['productId' => 1, 'quantity' => 1, 'priceCents' => 1]);
    same(422, $r->status);
    check(isset(jsonBody($r)['error']['fields']['priceCents']));
});

test('cantidades inválidas, productos inactivos o inexistentes se rechazan', function () {
    $app = makeApp();
    register($app);
    same(422, addToCart($app, 1, 0)->status);
    same(422, addToCart($app, 1, -3)->status);
    same(422, addToCart($app, 1, 11)->status);
    same(422, send($app, 'POST', '/api/cart/items', ['productId' => 1, 'quantity' => 1.5])->status);
    same(404, addToCart($app, 3, 1)->status);
    same(404, addToCart($app, 999, 1)->status);
});

test('no se puede superar el stock disponible, ni acumulando', function () {
    $app = makeApp();
    register($app);
    same(201, addToCart($app, 2, 3)->status);
    same(422, addToCart($app, 2, 1)->status);
});

test('actualizar, quitar y vaciar el carrito', function () {
    $app = makeApp();
    register($app);
    addToCart($app, 1, 1);
    addToCart($app, 2, 1);
    same(3, jsonBody(send($app, 'PUT', '/api/cart/items/2', ['quantity' => 3]))['items'][1]['quantity']);
    same(1, count(jsonBody(send($app, 'DELETE', '/api/cart/items/2'))['items']));
    same([], jsonBody(send($app, 'DELETE', '/api/cart'))['items']);
});

test('el carrito no se hereda entre usuarios de la misma sesión', function () {
    $app = makeApp($session);
    register($app, 'uno@example.com');
    addToCart($app, 1, 1);
    register($app, 'dos@example.com');
    same([], jsonBody($app->handle(req('GET', '/api/cart')))['items']);
});

echo "\nPedidos y pago simulado\n";

test('checkout exitoso: pagado, stock descontado, snapshot de precio y carrito vacío', function () {
    $app = makeApp($s, $u, $audit, $products);
    register($app);
    addToCart($app, 1, 2);
    $r = checkout($app);
    same(201, $r->status);
    $order = jsonBody($r)['order'];
    same('paid', $order['status']);
    same(2 * 749900, $order['totalCents']);
    check(str_starts_with($order['paymentRef'], 'SIM-'));
    same(3, $products->items[1]->stock);
    same([], jsonBody($app->handle(req('GET', '/api/cart')))['items']);
    check(in_array('order.paid', $audit->actions(), true));
});

test('el pedido conserva el precio histórico si el producto cambia después', function () {
    $app = makeApp($s, $u, $a, $products);
    register($app);
    addToCart($app, 1, 1);
    $id = jsonBody(checkout($app))['order']['id'];
    $products->setPrice(1, 1);
    $order = jsonBody($app->handle(req('GET', "/api/orders/{$id}")))['order'];
    same(749900, $order['items'][0]['unitPriceCents']);
});

test('pago rechazado: 402, pedido fallido, stock restaurado y carrito conservado', function () {
    $app = makeApp($s, $u, $audit, $products, $orders);
    register($app);
    addToCart($app, 1, 2);
    $r = checkout($app, 'simulated_decline');
    same(402, $r->status);
    same('payment_declined', jsonBody($r)['error']['code']);
    same(5, $products->items[1]->stock);
    same(OrderStatus::Failed, $orders->rows[1]['status']);
    same(1, count(jsonBody($app->handle(req('GET', '/api/cart')))['items']));
    check(in_array('order.payment_failed', $audit->actions(), true));
});

test('checkout con carrito vacío -> 422', function () {
    $app = makeApp();
    register($app);
    same(422, checkout($app)->status);
});

test('stock insuficiente al pagar -> 409 y no se crea pedido', function () {
    $app = makeApp($s, $u, $a, $products, $orders);
    register($app);
    addToCart($app, 2, 3);
    $products->items[2] = new NovaTech\Domain\Product(2, 'NV-MOU-ER', 'Ratón', 'x', 29900, 1);
    $r = checkout($app);
    same(409, $r->status);
    same('insufficient_stock', jsonBody($r)['error']['code']);
    same([], $orders->rows);
});

test('método de pago fuera de la lista blanca o campos extra -> 422', function () {
    $app = makeApp();
    register($app);
    addToCart($app, 1, 1);
    same(422, checkout($app, 'visa')->status);
    same(422, send($app, 'POST', '/api/orders', ['paymentMethod' => 'simulated_success', 'totalCents' => 1])->status);
});

test('BOLA/IDOR: otro usuario no puede ver un pedido ajeno (404, no 403)', function () {
    $app = makeApp($session);
    register($app, 'ana@example.com');
    addToCart($app, 1, 1);
    $id = jsonBody(checkout($app))['order']['id'];
    same(200, $app->handle(req('GET', "/api/orders/{$id}"))->status);

    $session->destroy();
    register($app, 'eve@example.com');
    same(404, $app->handle(req('GET', "/api/orders/{$id}"))->status);
    same([], jsonBody($app->handle(req('GET', '/api/orders')))['orders']);
});

test('los pedidos exigen sesión', function () {
    $app = makeApp();
    same(401, $app->handle(req('GET', '/api/orders'))->status);
    same(401, $app->handle(req('GET', '/api/orders/1'))->status);
});
