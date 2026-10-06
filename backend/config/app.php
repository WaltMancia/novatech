<?php
declare(strict_types=1);

use NovaTech\Core\Env;

$url = rtrim(Env::get('APP_URL', 'http://localhost:8080'), '/');
$extra = array_filter(array_map('trim', explode(',', Env::get('ALLOWED_ORIGINS', ''))));

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    // Orígenes aceptados para peticiones que cambian estado (defensa en profundidad junto al token CSRF).
    'url' => $url,
    'allowed_origins' => array_values(array_unique([$url, ...$extra])),
    'max_body_bytes' => 1_048_576,
    'session_idle_ttl' => Env::int('SESSION_IDLE_TTL', 1800),
    'session_absolute_ttl' => Env::int('SESSION_ABSOLUTE_TTL', 28800),
];
