<?php
declare(strict_types=1);

/**
 * Carga el catálogo inicial (idempotente: actualiza datos por SKU).
 *   php bin/seed.php                 conserva el stock existente
 *   php bin/seed.php --reset-stock   restablece el stock a los valores iniciales
 */

require __DIR__ . '/../bootstrap/autoload.php';

use NovaTech\Core\Database;

$config = (require __DIR__ . '/../bootstrap/config.php')();
$db = new Database((array) $config->get('database'));
$resetStock = in_array('--reset-stock', $argv, true);

$stockClause = $resetStock ? 'stock = VALUES(stock)' : 'stock = stock';
foreach (require __DIR__ . '/../database/seeds/products.php' as [$sku, $name, $description, $price, $stock]) {
    $image = '/images/' . strtolower($sku) . '.webp';
    $db->execute(
        "INSERT INTO products (sku, name, description, price_cents, stock, image_path) VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                                 price_cents = VALUES(price_cents), image_path = VALUES(image_path), {$stockClause}",
        [$sku, $name, $description, $price, $stock, $image],
    );
}
echo 'Catálogo cargado: ' . count(require __DIR__ . '/../database/seeds/products.php') . ' productos' . ($resetStock ? ' (stock restablecido)' : '') . ".\n";
