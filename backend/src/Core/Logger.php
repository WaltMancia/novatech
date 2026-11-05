<?php
declare(strict_types=1);

namespace NovaTech\Core;

/** Logger JSON-lines con redacción automática de datos sensibles. */
final class Logger
{
    private const REDACT = ['password', 'password_hash', 'token', 'csrf', 'authorization', 'cookie', 'secret', 'card'];

    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $stream = 'php://stderr')
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode(
            ['ts' => gmdate('c'), 'level' => $level, 'service' => 'novatech-api', 'msg' => $message, ...$this->redact($context)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
        $this->handle ??= @fopen($this->stream, 'ab') ?: null;
        if ($this->handle !== null) {
            fwrite($this->handle, $line . "\n");
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            foreach (self::REDACT as $needle) {
                if (str_contains($lower, $needle)) {
                    $data[$key] = '[REDACTED]';
                    continue 2;
                }
            }
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }
        return $data;
    }
}
