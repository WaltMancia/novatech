<?php
declare(strict_types=1);

use NovaTech\Core\Env;

return [
    // Protección en tiempo de ejecución: umbrales por IP dentro de una ventana de tiempo.
    'ban_seconds' => Env::int('SECURITY_BAN_SECONDS', 600),
    'error_burst' => ['threshold' => Env::int('SECURITY_ERROR_BURST', 30), 'window' => 60],
    'idor' => ['threshold' => Env::int('SECURITY_IDOR_THRESHOLD', 8), 'window' => 300],
    'csrf' => ['threshold' => Env::int('SECURITY_CSRF_THRESHOLD', 5), 'window' => 300],
];
