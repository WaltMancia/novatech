<?php
declare(strict_types=1);

namespace NovaTech\Domain;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public Role $role,
        public string $passwordHash,
        public bool $locked = false,
    ) {
    }

    /** Representación segura para la API: nunca incluye el hash. */
    public function toPublic(): array
    {
        return ['id' => $this->id, 'email' => $this->email, 'name' => $this->name, 'role' => $this->role->value];
    }
}
