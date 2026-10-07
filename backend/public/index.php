<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap/autoload.php';

try {
    $app = (require __DIR__ . '/../bootstrap/app.php')();
    $app->run();
} catch (Throwable $e) {
    // Último recurso: nunca filtrar detalles al cliente.
    error_log(sprintf('[fatal] %s: %s', $e::class, $e->getMessage()));
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo '{"error":{"code":"server_error","message":"Error interno del servidor."}}';
}
