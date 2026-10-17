<?php
declare(strict_types=1);

/**
 * Crea un usuario administrador. La contraseña se toma de la variable ADMIN_PASSWORD
 * para que no quede en el historial del shell.
 *   php bin/create-admin.php correo@dominio.com "Nombre Apellido"
 */

require __DIR__ . '/../bootstrap/autoload.php';

use NovaTech\Core\Exceptions\ValidationException;
use NovaTech\Core\{Database, Validator};
use NovaTech\Domain\{DuplicateEmailException, Role};
use NovaTech\Repositories\PdoUserRepository;
use NovaTech\Services\PasswordHasher;

$config = (require __DIR__ . '/../bootstrap/config.php')();

try {
    $data = Validator::validate(
        ['email' => $argv[1] ?? '', 'name' => $argv[2] ?? '', 'password' => getenv('ADMIN_PASSWORD') ?: ''],
        ['email' => 'required|trim|email', 'name' => 'required|trim|string|between:2,100', 'password' => 'required|password'],
    );
} catch (ValidationException $e) {
    fwrite(STDERR, "Datos inválidos:\n");
    foreach ($e->fields as $field => $messages) {
        fwrite(STDERR, "  - {$messages[0]}\n");
    }
    exit(1);
}

$users = new PdoUserRepository(new Database((array) $config->get('database')));

try {
    $user = $users->create(mb_strtolower($data['email']), $data['name'], (new PasswordHasher())->hash($data['password']), Role::Admin);
    echo "Administrador creado: {$user->email} (id {$user->id})\n";
} catch (DuplicateEmailException) {
    fwrite(STDERR, "Ya existe un usuario con ese correo.\n");
    exit(1);
}
