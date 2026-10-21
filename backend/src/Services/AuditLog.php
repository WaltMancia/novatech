<?php
declare(strict_types=1);

namespace NovaTech\Services;

interface AuditLog
{
    /** @param array<string, scalar|null> $metadata */
    public function record(string $action, ?int $userId, string $ip, array $metadata = []): void;
}
