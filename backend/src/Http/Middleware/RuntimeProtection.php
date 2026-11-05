<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\{Middleware, RateLimitStore};
use NovaTech\Core\{Config, Logger, Request, Response};
use NovaTech\Http\ErrorResponse;
use NovaTech\Services\AuditLog;

/**
 * RASP ligero: observa las respuestas en tiempo de ejecución, detecta comportamientos anómalos por IP
 * y reacciona (evento estructurado para el SIEM + bloqueo temporal). Complementa al WAF, que no entiende
 * la lógica de negocio (p. ej. enumeración de pedidos ajenos).
 */
final class RuntimeProtection implements Middleware
{
    private const CLIENT_ERRORS = [400, 401, 403, 404, 405, 422];
    /** El SPA consulta /auth/me al cargar; un 401 anónimo ahí es normal y no debe contar. */
    private const IGNORED_ROUTES = ['/api/auth/me'];

    public function __construct(
        private readonly RateLimitStore $store,
        private readonly Logger $logger,
        private readonly AuditLog $audit,
        private readonly Config $config,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $ip = $request->ip();
        $ban = $this->store->peek($this->banKey($ip));
        if ($ban['count'] > 0) {
            return Response::json(
                ['error' => ['code' => 'temporarily_blocked', 'message' => 'Actividad sospechosa detectada. Intenta de nuevo más tarde.']],
                429,
                ['Retry-After' => (string) max(1, $ban['resetAt'] - time())],
            );
        }

        $response = $next($request);
        $this->inspect($request, $response);
        return $response;
    }

    private function inspect(Request $request, Response $response): void
    {
        if ($response->status < 400 || in_array($request->context['route'] ?? null, self::IGNORED_ROUTES, true)) {
            return;
        }
        $code = ErrorResponse::code($response);

        if (in_array($response->status, self::CLIENT_ERRORS, true)) {
            $this->count($request, 'error_burst', 'anomaly.error_burst', ['last_status' => $response->status]);
        }
        if ($code === 'order_not_found') {
            $this->count($request, 'idor', 'anomaly.idor_enumeration');
        }
        if ($code === 'csrf_mismatch' || $code === 'origin_not_allowed') {
            $this->count($request, 'csrf', 'anomaly.csrf_burst');
        }
    }

    /** @param array<string, scalar|null> $details */
    private function count(Request $request, string $rule, string $event, array $details = []): void
    {
        $ip = $request->ip();
        $threshold = (int) $this->config->get("security.{$rule}.threshold", 0);
        $window = (int) $this->config->get("security.{$rule}.window", 60);
        if ($threshold <= 0) {
            return;
        }

        $state = $this->store->hit("anomaly|{$rule}|{$ip}", $window);
        if ($state['count'] !== $threshold) {
            return; // el evento se emite una sola vez por ventana
        }

        $banSeconds = (int) $this->config->get('security.ban_seconds', 600);
        $this->store->hit($this->banKey($ip), $banSeconds);

        $userId = $request->context['user_id'] ?? null;
        $context = [
            'event' => $event,
            'srcip' => $ip,
            'user_id' => is_int($userId) ? $userId : null,
            'request_id' => $request->attribute('request_id'),
            'threshold' => $threshold,
            'window_s' => $window,
            'ban_s' => $banSeconds,
            ...$details,
        ];
        $this->logger->warning('security_event', $context);
        $this->audit->record('security.anomaly', is_int($userId) ? $userId : null, $ip, [
            'type' => $event, 'threshold' => $threshold, 'window_s' => $window, 'ban_s' => $banSeconds,
        ]);
    }

    private function banKey(string $ip): string
    {
        return "ban|{$ip}";
    }
}
