<?php
declare(strict_types=1);

use NovaTech\Core\Config;

// Carga todos los config/*.php (el nombre del archivo es el prefijo: app.debug, database.host...).
return static function (): Config {
    $items = [];
    foreach (glob(__DIR__ . '/../config/*.php') ?: [] as $file) {
        $items[basename($file, '.php')] = require $file;
    }
    $config = new Config($items);

    // Guardarraíl: nunca debug en producción.
    if ($config->get('app.env') === 'production' && $config->get('app.debug') === true) {
        throw new RuntimeException('APP_DEBUG debe ser false cuando APP_ENV=production.');
    }
    return $config;
};
