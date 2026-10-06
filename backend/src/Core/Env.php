<?php
declare(strict_types=1);

namespace NovaTech\Core;

/** Acceso tipado a variables de entorno. */
final class Env
{
    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = getenv($key);
        return $value !== false && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $default;
    }
}
