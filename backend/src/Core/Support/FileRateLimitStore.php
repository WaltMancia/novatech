<?php
declare(strict_types=1);

namespace NovaTech\Core\Support;

use NovaTech\Core\Contracts\RateLimitStore;

/**
 * Ventana fija en archivos con flock. Suficiente para una sola instancia;
 * con varias réplicas se sustituye por una implementación Redis (misma interfaz).
 */
final class FileRateLimitStore implements RateLimitStore
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0700, true);
        }
    }

    public function hit(string $key, int $windowSeconds): array
    {
        $now = time();
        $path = $this->directory . '/' . hash('sha256', $key) . '.json';
        $fh = fopen($path, 'c+');
        if ($fh === false) {
            return ['count' => 1, 'resetAt' => $now + $windowSeconds]; // fail-open si el disco falla
        }
        flock($fh, LOCK_EX);
        $state = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($state) || ($state['resetAt'] ?? 0) <= $now) {
            $state = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }
        $state['count']++;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($state, JSON_THROW_ON_ERROR));
        flock($fh, LOCK_UN);
        fclose($fh);

        return ['count' => (int) $state['count'], 'resetAt' => (int) $state['resetAt']];
    }
}
