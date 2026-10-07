<?php
declare(strict_types=1);

use NovaTech\Core\Router;
use NovaTech\Http\Controllers\{CsrfController, HealthController};

return static function (Router $router): void {
    $router->group('/api', [], static function (Router $r): void {
        $r->get('/health', [HealthController::class, 'live']);
        $r->get('/health/ready', [HealthController::class, 'ready']);
        $r->get('/csrf', [CsrfController::class, 'show']);
        // Fase 1: /auth/*   Fase 2: /products, /cart, /orders   Fase 3: /admin/*
    });
};
