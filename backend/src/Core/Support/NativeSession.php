<?php
declare(strict_types=1);

namespace NovaTech\Core\Support;

use NovaTech\Core\Contracts\Session;

/**
 * Sesión nativa de PHP, iniciada de forma perezosa y con cookie endurecida:
 * HttpOnly, SameSite=Lax, Secure bajo HTTPS (prefijo __Host-), modo estricto,
 * y expiración por inactividad y absoluta.
 */
final class NativeSession implements Session
{
    public function __construct(
        private readonly int $idleTtl = 1800,
        private readonly int $absoluteTtl = 28800,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name($https ? '__Host-nt_sid' : 'nt_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        $created = $_SESSION['_created'] ?? null;
        $seen = $_SESSION['_seen'] ?? null;
        if (is_int($created) && is_int($seen)
            && ($now - $seen > $this->idleTtl || $now - $created > $this->absoluteTtl)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $created = null;
        }
        $_SESSION['_created'] = is_int($created) ? $created : $now;
        $_SESSION['_seen'] = $now;
    }
}
