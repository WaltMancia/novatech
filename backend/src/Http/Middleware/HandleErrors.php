<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\Middleware;
use NovaTech\Core\Exceptions\{HttpException, ValidationException};
use NovaTech\Core\{Config, Logger, Request, Response};
use Throwable;

/** Convierte cualquier excepción en JSON uniforme sin filtrar detalles internos. */
final class HandleErrors implements Middleware
{
    public function __construct(private readonly Logger $logger, private readonly Config $config)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (ValidationException $e) {
            return $this->error($e->status, $e->errorCode, $e->getMessage(), $e->headers, ['fields' => $e->fields]);
        } catch (HttpException $e) {
            return $this->error($e->status, $e->errorCode, $e->getMessage(), $e->headers);
        } catch (Throwable $e) {
            $this->logger->error('unhandled_exception', [
                'class' => $e::class,
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
                'path' => $request->path,
            ]);
            $extra = $this->config->get('app.debug') === true
                ? ['debug' => ['class' => $e::class, 'message' => $e->getMessage()]] : [];
            return $this->error(500, 'server_error', 'Error interno del servidor.', [], $extra);
        }
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $extra
     */
    private function error(int $status, string $code, string $message, array $headers, array $extra = []): Response
    {
        return Response::json(['error' => ['code' => $code, 'message' => $message, ...$extra]], $status, $headers);
    }
}
