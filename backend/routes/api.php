<?php
declare(strict_types=1);

use NovaTech\Core\Router;
use NovaTech\Http\Controllers\{AuthController, CartController, CsrfController, HealthController, OrderController, ProductController};
use NovaTech\Http\Controllers\Admin\{AuditAdminController, OrderAdminController, ProductAdminController, UserAdminController};
use NovaTech\Http\Middleware\Authenticate;

return static function (Router $router): void {
    $router->group('/api', [], static function (Router $r): void {
        $r->get('/health', [HealthController::class, 'live']);
        $r->get('/health/ready', [HealthController::class, 'ready']);
        $r->get('/csrf', [CsrfController::class, 'show']);

        $r->group('/auth', [], static function (Router $a): void {
            $a->post('/register', [AuthController::class, 'register'], ['throttle:5,600']);
            $a->post('/login', [AuthController::class, 'login'], ['throttle:10,60']);
            $a->post('/logout', [AuthController::class, 'logout'], [Authenticate::class]);
            $a->get('/me', [AuthController::class, 'me'], [Authenticate::class]);
        });

        $r->get('/products', [ProductController::class, 'index']);
        $r->get('/products/{id:\d{1,9}}', [ProductController::class, 'show']);

        $r->group('/cart', [Authenticate::class], static function (Router $c): void {
            $c->get('', [CartController::class, 'show']);
            $c->post('/items', [CartController::class, 'add']);
            $c->put('/items/{productId:\d{1,9}}', [CartController::class, 'update']);
            $c->delete('/items/{productId:\d{1,9}}', [CartController::class, 'remove']);
            $c->delete('', [CartController::class, 'clear']);
        });

        $r->group('/orders', [Authenticate::class], static function (Router $o): void {
            $o->post('', [OrderController::class, 'store'], ['throttle:10,60']);
            $o->get('', [OrderController::class, 'index']);
            $o->get('/{id:\d{1,9}}', [OrderController::class, 'show']);
        });

        // Toda la superficie administrativa exige sesión y rol admin (se evalúan en ese orden).
        $r->group('/admin', [Authenticate::class, 'role:admin'], static function (Router $a): void {
            $a->get('/products', [ProductAdminController::class, 'index']);
            $a->post('/products', [ProductAdminController::class, 'store']);
            $a->get('/products/{id:\d{1,9}}', [ProductAdminController::class, 'show']);
            $a->patch('/products/{id:\d{1,9}}', [ProductAdminController::class, 'update']);
            $a->delete('/products/{id:\d{1,9}}', [ProductAdminController::class, 'destroy']);

            $a->get('/orders', [OrderAdminController::class, 'index']);
            $a->get('/orders/{id:\d{1,9}}', [OrderAdminController::class, 'show']);
            $a->post('/orders/{id:\d{1,9}}/cancel', [OrderAdminController::class, 'cancel']);

            $a->get('/users', [UserAdminController::class, 'index']);
            $a->get('/audit-logs', [AuditAdminController::class, 'index']);
        });
    });
};
