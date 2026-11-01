<?php
declare(strict_types=1);

namespace NovaTech\Core;

use NovaTech\Core\Exceptions\ValidationException;

/**
 * Validador por lista blanca: solo se devuelven los campos declarados (anti mass assignment)
 * y los tipos son estrictos (un string no pasa por int salvo dígitos puros).
 *
 * Reglas: required, nullable, trim, string, int, bool, email, password, min:n, max:n,
 *         between:a,b, in:a,b,c, regex:/.../  (usar formato array si el patrón contiene "|").
 *
 * @phpstan-type Rules array<string, string|list<string>>
 */
final class Validator
{
    private const CONTROL_CHARS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    /**
     * @param array<string, mixed> $input
     * @param Rules                $rules
     * @return array<string, mixed>
     */
    public static function validate(array $input, array $rules, bool $rejectUnknown = false): array
    {
        $errors = [];
        $out = [];

        if ($rejectUnknown) {
            foreach (array_diff(array_keys($input), array_keys($rules)) as $unknown) {
                $errors[(string) $unknown][] = 'Campo no permitido.';
            }
        }

        foreach ($rules as $field => $spec) {
            $list = is_array($spec) ? $spec : explode('|', $spec);
            $parsed = array_map(self::parse(...), $list);
            $names = array_column($parsed, 0);
            $required = in_array('required', $names, true);
            $nullable = in_array('nullable', $names, true);

            $present = array_key_exists($field, $input);
            $value = $present ? $input[$field] : null;

            if (!$present || $value === null || $value === '') {
                if ($required && !($nullable && $present && $value === null)) {
                    $errors[$field][] = "El campo {$field} es obligatorio.";
                } elseif ($present && $nullable && $value === null) {
                    $out[$field] = null;
                }
                continue;
            }

            foreach ($parsed as [$name, $args]) {
                $error = self::apply($name, $args, $field, $value);
                if ($error !== null) {
                    $errors[$field][] = $error;
                    continue 2;
                }
            }
            $out[$field] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $out;
    }

    /** @return array{0: string, 1: list<string>} */
    private static function parse(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        if (!isset($parts[1])) {
            return [$name, []];
        }
        return [$name, $name === 'regex' ? [$parts[1]] : explode(',', $parts[1])];
    }

    /** @param list<string> $args */
    private static function apply(string $rule, array $args, string $field, mixed &$value): ?string
    {
        switch ($rule) {
            case 'required':
            case 'nullable':
                return null;
            case 'trim':
                if (is_string($value)) {
                    $value = trim($value);
                }
                return null;
            case 'string':
                if (!is_string($value)) {
                    return "{$field} debe ser texto.";
                }
                return preg_match(self::CONTROL_CHARS, $value) === 1 ? "{$field} contiene caracteres no permitidos." : null;
            case 'int':
                if (is_int($value)) {
                    return null;
                }
                if (is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1) {
                    $value = (int) $value;
                    return null;
                }
                return "{$field} debe ser un número entero.";
            case 'bool':
                return is_bool($value) ? null : "{$field} debe ser verdadero o falso.";
            case 'email':
                return is_string($value) && strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                    ? null : "{$field} debe ser un correo válido.";
            case 'password':
                // Longitud sobre composición (NIST 800-63B); tope para evitar DoS por hashing.
                if (!is_string($value) || mb_strlen($value) < 12) {
                    return 'La contraseña debe tener al menos 12 caracteres.';
                }
                return mb_strlen($value) > 128 ? 'La contraseña no puede superar 128 caracteres.' : null;
            case 'min':
                return self::size($value) < (int) $args[0] ? "{$field} es menor al mínimo permitido ({$args[0]})." : null;
            case 'max':
                return self::size($value) > (int) $args[0] ? "{$field} supera el máximo permitido ({$args[0]})." : null;
            case 'between':
                $size = self::size($value);
                return $size < (int) $args[0] || $size > (int) $args[1] ? "{$field} debe estar entre {$args[0]} y {$args[1]}." : null;
            case 'in':
                return in_array((string) $value, $args, true) ? null : "{$field} tiene un valor no permitido.";
            case 'regex':
                return is_string($value) && preg_match($args[0], $value) === 1 ? null : "{$field} tiene un formato inválido.";
            default:
                throw new \InvalidArgumentException("Regla de validación desconocida: {$rule}");
        }
    }

    private static function size(mixed $value): int|float
    {
        return is_string($value) ? mb_strlen($value) : (is_int($value) || is_float($value) ? $value : 0);
    }
}
