<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $routeParams = [];

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function getPath(): string
    {
        $path    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $trimmed = trim($path, '/');
        return $trimmed === '' ? '/' : '/' . $trimmed;
    }

    public function getBody(): array
    {
        if ($this->isJson()) {
            $data = json_decode(file_get_contents('php://input') ?: '', true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->getBody()[$key] ?? $_GET[$key] ?? $default;
    }

    public function getRouteParam(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Weryfikuje CSRF token z body lub nagłówka X-CSRF-TOKEN.
     *
     * @throws \RuntimeException gdy token jest nieprawidłowy lub brak
     */
    public function verifyCsrf(): void
    {
        $token = $this->getBody()['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (!Csrf::verify((string)$token)) {
            throw new \RuntimeException('403 Forbidden — CSRF token mismatch');
        }
    }

    public function isPost(): bool { return $this->getMethod() === 'POST'; }
    public function isGet(): bool  { return $this->getMethod() === 'GET'; }

    public function isJson(): bool
    {
        return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function getIp(): string
    {
        $remoteAddr    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trustedProxies = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];

        if (in_array($remoteAddr, (array)$trustedProxies, true)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                if (!empty($_SERVER[$key])) {
                    return trim(explode(',', $_SERVER[$key])[0]);
                }
            }
        }
        return $remoteAddr;
    }
}
