<?php
declare(strict_types=1);

namespace NovaTech\Core\Exceptions;

final class ValidationException extends HttpException
{
    /** @param array<string, list<string>> $fields */
    public function __construct(public readonly array $fields)
    {
        parent::__construct(422, 'validation_failed', 'Los datos enviados no son válidos.');
    }
}
