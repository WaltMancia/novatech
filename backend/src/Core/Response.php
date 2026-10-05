<?php
declare(strict_types=1);

namespace NovaTech\Core;

/** Respuesta HTTP inmutable. */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', ...$headers],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }

    public static function empty(int $status = 204): self
    {
        return new self($status);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = array_filter($this->headers, static fn (string $k) => strcasecmp($k, $name) !== 0, ARRAY_FILTER_USE_KEY);
        return new self($this->status, [...$headers, $name => $value], $this->body);
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $this->body;
    }
}
