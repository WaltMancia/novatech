<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers;

use NovaTech\Core\{Database, Logger, Request, Response};
use Throwable;

final class HealthController
{
    public function __construct(private readonly Database $db, private readonly Logger $logger)
    {
    }

    public function live(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'service' => 'novatech-api']);
    }

    /** Readiness: dependencias disponibles. No expone el motivo del fallo. */
    public function ready(Request $request): Response
    {
        try {
            $this->db->fetchOne('SELECT 1 AS ok');
            return Response::json(['status' => 'ready']);
        } catch (Throwable $e) {
            $this->logger->error('readiness_failed', ['class' => $e::class]);
            return Response::json(['status' => 'unavailable'], 503);
        }
    }
}
