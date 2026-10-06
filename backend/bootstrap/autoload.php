<?php
declare(strict_types=1);

// Usa el autoload de Composer si existe; si no, un cargador PSR-4 mínimo (sin dependencias).
$vendor = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'NovaTech\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
