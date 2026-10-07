<?php
declare(strict_types=1);

/** Aplica database/migrations/*.sql en orden, una sola vez cada una.  Uso: php bin/migrate.php */

require __DIR__ . '/../bootstrap/autoload.php';

use NovaTech\Core\Database;

$config = (require __DIR__ . '/../bootstrap/config.php')();
$db = new Database((array) $config->get('database'));

// Espera a la base de datos (útil en el arranque de contenedores).
for ($attempt = 1; ; $attempt++) {
    try {
        $db->pdo();
        break;
    } catch (Throwable $e) {
        if ($attempt >= 30) {
            fwrite(STDERR, "No se pudo conectar a la base de datos: {$e->getMessage()}\n");
            exit(1);
        }
        sleep(1);
    }
}

$db->execute('CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');

$applied = array_column($db->fetchAll('SELECT version FROM schema_migrations'), 'version');
$files = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
sort($files);

$count = 0;
foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $applied, true)) {
        continue;
    }
    // Un statement por archivo: MySQL/MariaDB hacen commit implícito en DDL.
    $db->pdo()->exec((string) file_get_contents($file));
    $db->execute('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
    echo "  aplicada: {$version}\n";
    $count++;
}
echo $count === 0 ? "Base de datos al día.\n" : "{$count} migración(es) aplicada(s).\n";
