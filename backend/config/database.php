<?php
declare(strict_types=1);

use NovaTech\Core\Env;

return [
    'host' => Env::get('DB_HOST', 'db'),
    'port' => Env::int('DB_PORT', 3306),
    'name' => Env::get('DB_NAME', 'novatech'),
    'user' => Env::get('DB_USER', 'novatech'),
    'password' => Env::get('DB_PASSWORD', ''),
];
