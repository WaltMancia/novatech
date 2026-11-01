<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Core\Database;
use NovaTech\Domain\{DuplicateEmailException, Role, User};
use PDOException;

final class PdoUserRepository implements UserRepository
{
    private const SELECT = 'SELECT id, email, name, role, password_hash,
        (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked FROM users';

    public function __construct(private readonly Database $db)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE email = ?', [$email]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE id = ?', [$id]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function create(string $email, string $name, string $passwordHash, Role $role): User
    {
        try {
            $this->db->execute(
                'INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, ?)',
                [$email, $name, $passwordHash, $role->value],
            );
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new DuplicateEmailException();
            }
            throw $e;
        }
        return new User($this->db->lastInsertId(), $email, $name, $role, $passwordHash);
    }

    public function recordFailedLogin(int $id, int $maxAttempts, int $lockSeconds): void
    {
        // El orden de las asignaciones importa: locked_until se evalúa con el contador anterior.
        $this->db->execute(
            'UPDATE users SET
                locked_until  = IF(failed_logins + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? SECOND), locked_until),
                failed_logins = IF(failed_logins + 1 >= ?, 0, failed_logins + 1)
             WHERE id = ?',
            [$maxAttempts, $lockSeconds, $maxAttempts, $id],
        );
    }

    public function resetFailedLogins(int $id): void
    {
        $this->db->execute('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?', [$id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public function paginate(int $limit, int $offset): array
    {
        $total = (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM users')['n'] ?? 0);
        // nosemgrep: novatech-php-sql-interpolation - $limit y $offset son int tipados.
        $rows = $this->db->fetchAll(self::SELECT . " ORDER BY id LIMIT {$limit} OFFSET {$offset}");
        return ['items' => array_map($this->hydrate(...), $rows), 'total' => $total];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['name'],
            Role::from((string) $row['role']),
            (string) $row['password_hash'],
            (bool) $row['is_locked'],
        );
    }
}
