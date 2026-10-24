<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

interface AuditLogReader
{
    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(?string $action, ?int $userId, int $limit, int $offset): array;
}
