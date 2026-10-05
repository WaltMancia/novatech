<?php
declare(strict_types=1);

namespace NovaTech\Core\Exceptions;

use RuntimeException;

/** Error HTTP "esperado": se convierte en una respuesta JSON controlada. */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }
}
