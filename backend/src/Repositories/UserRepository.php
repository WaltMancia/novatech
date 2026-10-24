<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Domain\{DuplicateEmailException, Role, User};

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /** @throws DuplicateEmailException */
    public function create(string $email, string $name, string $passwordHash, Role $role): User;

    /** Incrementa el contador y bloquea la cuenta al alcanzar $maxAttempts (de forma atómica). */
    public function recordFailedLogin(int $id, int $maxAttempts, int $lockSeconds): void;

    public function resetFailedLogins(int $id): void;

    public function updatePasswordHash(int $id, string $hash): void;

    /** @return array{items: list<User>, total: int} */
    public function paginate(int $limit, int $offset): array;
}
