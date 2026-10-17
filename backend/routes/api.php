<?php
declare(strict_types=1);

use NovaTech\Core\Router;
use NovaTech\Http\Controllers\{AuthController, CsrfController, HealthController};
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
        // Fase 2: /products, /cart, /orders   Fase 3: /admin/*
    });
};
