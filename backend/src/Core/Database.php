<?php
declare(strict_types=1);

namespace NovaTech\Core;

use NovaTech\Core\Contracts\Transactor;
use PDO;
use Throwable;

/**
 * Envoltorio PDO. Regla del proyecto: TODA consulta usa parámetros enlazados;
 * jamás se concatenan datos del usuario en el SQL.
 */
final class Database implements Transactor
{
    private ?PDO $pdo = null;

    /** @param array{host?: string, port?: int, name?: string, user?: string, password?: string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config['host'] ?? 'db', $this->config['port'] ?? 3306, $this->config['name'] ?? ''),
            $this->config['user'] ?? '',
            $this->config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );
    }

    /** @param array<int|string, scalar|null> $params @return list<array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param array<int|string, scalar|null> $params @return array<string, mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string, scalar|null> $params */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work($this);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
