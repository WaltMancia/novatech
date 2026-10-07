<?php
declare(strict_types=1);

namespace NovaTech\Core\Contracts;

interface Session
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** Nuevo ID de sesión (anti session fixation); llamar al iniciar sesión y al elevar privilegios. */
    public function regenerate(): void;

    public function destroy(): void;
}
