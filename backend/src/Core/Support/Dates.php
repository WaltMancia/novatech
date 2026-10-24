<?php
declare(strict_types=1);

namespace NovaTech\Core\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Dates
{
    /** Convierte un DATETIME de la base (guardado en UTC) a ISO 8601. */
    public static function toIso(string $utcDatetime): string
    {
        return (new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
