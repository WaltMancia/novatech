<?php
declare(strict_types=1);

namespace NovaTech\Core;

use ArrayObject;
use JsonException;
use NovaTech\Core\Exceptions\HttpException;

/** Petición HTTP inmutable. */
final class Request
{
    /** @var array<string, string> */
    private array $headers;

    /** Contexto mutable compartido por todas las copias de la petición (p. ej. la ruta resuelta, para el access log). */
    public readonly ArrayObject $context;

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param array<string, mixed>  $server
     * @param array<string, string> $params
     * @param array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        array $headers = [],
        private readonly string $rawBody = '',
        private readonly array $server = [],
        private readonly bool $bodyTooLarge = false,
        private readonly array $params = [],
        private readonly array $attributes = [],
        ?ArrayObject $context = null,
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->context = $context ?? new ArrayObject();
    }

    public static function fromGlobals(int $maxBodyBytes): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $path = $path === '/' ? '/' : rtrim($path, '/');

        $declared = (int) ($headers['content-length'] ?? 0);
        $body = '';
        $tooLarge = $declared > $maxBodyBytes;
        if (!$tooLarge) {
            $body = (string) file_get_contents('php://input', false, null, 0, $maxBodyBytes + 1);
            $tooLarge = strlen($body) > $maxBodyBytes;
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $headers,
            $tooLarge ? '' : $body,
            $_SERVER,
            $tooLarge,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '';
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr($this->header('user-agent') ?? '', 0, 200);
    }

    public function isHttps(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    /** Cuerpo JSON como objeto asociativo. Lanza 413/415/400 según corresponda. */
    public function json(): array
    {
        if ($this->bodyTooLarge) {
            throw new HttpException(413, 'payload_too_large', 'El cuerpo de la petición es demasiado grande.');
        }
        if ($this->rawBody === '') {
            return [];
        }
        $type = strtolower(trim(explode(';', $this->header('content-type') ?? '')[0]));
        if ($type !== 'application/json') {
            throw new HttpException(415, 'unsupported_media_type', 'Content-Type debe ser application/json.');
        }
        try {
            $data = json_decode($this->rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(400, 'invalid_json', 'El cuerpo no es un JSON válido.');
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new HttpException(400, 'invalid_json', 'El cuerpo debe ser un objeto JSON.');
        }
        return $data;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    /** @param array<string, string> $params */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->rawBody,
            $this->server, $this->bodyTooLarge, $params, $this->attributes, $this->context);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->rawBody,
            $this->server, $this->bodyTooLarge, $this->params, [...$this->attributes, $key => $value], $this->context);
    }
}
