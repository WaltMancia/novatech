<?php
declare(strict_types=1);

namespace NovaTech\Http;

use NovaTech\Core\Response;

final class ErrorResponse
{
    /** Código de error del formato uniforme { "error": { "code": ... } }, o null si no es una respuesta de error. */
    public static function code(Response $response): ?string
    {
        if ($response->status < 400) {
            return null;
        }
        $data = json_decode($response->body, true);
        if (!is_array($data) || !is_array($data['error'] ?? null)) {
            return null;
        }
        $code = $data['error']['code'] ?? null;
        return is_string($code) ? $code : null;
    }
}
