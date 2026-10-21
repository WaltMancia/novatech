<?php
declare(strict_types=1);

use NovaTech\Core\Router;
use NovaTech\Http\Controllers\{AuthController, CartController, CsrfController, HealthController, OrderController, ProductController};
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
        // Fase 3: /admin/*
    });
};
