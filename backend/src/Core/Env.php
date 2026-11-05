<?php
declare(strict_types=1);

namespace NovaTech\Core;

final class Env
{
    /**
     * Si existe KEY_FILE, el valor se lee de ese archivo (convención de Docker/Kubernetes para secretos):
     * así la contraseña no aparece en el entorno del proceso ni en `docker inspect`.
     */
    public static function get(string $key, string $default = ''): string
    {
        $file = getenv($key . '_FILE');
        if ($file !== false && $file !== '') {
            $content = @file_get_contents($file);
            if ($content === false) {
                throw new \RuntimeException("No se pudo leer el secreto indicado en {$key}_FILE.");
            }
            return rtrim($content, "\r\n");
        }
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
