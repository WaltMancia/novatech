<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Core\Database;
use NovaTech\Core\Support\Dates;

final class PdoAuditLogReader implements AuditLogReader
{
    public function __construct(private readonly Database $db)
    {
    }

    public function paginate(?string $action, ?int $userId, int $limit, int $offset): array
    {
        // Los fragmentos del WHERE son constantes; los valores siempre van enlazados.
        $where = [];
        $params = [];
        if ($action !== null) {
            $where[] = 'action = ?';
            $params[] = $action;
        }
        if ($userId !== null) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        // nosemgrep: novatech-php-sql-interpolation - $clause se compone de fragmentos constantes; los valores van enlazados.
        $total = (int) ($this->db->fetchOne("SELECT COUNT(*) AS n FROM audit_logs{$clause}", $params)['n'] ?? 0);
        // $limit y $offset son int tipados: se interpolan como literales.
        // nosemgrep: novatech-php-sql-interpolation - $clause es constante; $limit y $offset son int tipados.
        $rows = $this->db->fetchAll(
            "SELECT id, user_id, action, ip, metadata, created_at FROM audit_logs{$clause} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        return ['items' => array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'userId' => $r['user_id'] === null ? null : (int) $r['user_id'],
            'action' => (string) $r['action'],
            'ip' => (string) $r['ip'],
            'metadata' => $r['metadata'] === null ? null : json_decode((string) $r['metadata'], true),
            'createdAt' => Dates::toIso((string) $r['created_at']),
        ], $rows), 'total' => $total];
    }
}
