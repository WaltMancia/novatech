<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Core\{Database, Logger};
use NovaTech\Services\AuditLog;
use Throwable;

/** Escribe en audit_logs y en el log estructurado; un fallo de auditoría no debe tumbar la petición. */
final class DatabaseAuditLog implements AuditLog
{
    public function __construct(private readonly Database $db, private readonly Logger $logger)
    {
    }

    public function record(string $action, ?int $userId, string $ip, array $metadata = []): void
    {
        $this->logger->info('audit', ['action' => $action, 'user_id' => $userId, 'ip' => $ip, 'srcip' => $ip, ...$metadata]);
        try {
            $this->db->execute(
                'INSERT INTO audit_logs (user_id, action, ip, metadata) VALUES (?, ?, ?, ?)',
                [$userId, $action, $ip, $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR)],
            );
        } catch (Throwable $e) {
            $this->logger->error('audit_write_failed', ['action' => $action, 'class' => $e::class]);
        }
    }
}
