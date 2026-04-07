<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $statusCode = 200;

    public function redirect(string $url, int $statusCode = 302): never
    {
        // Zapobiegaj open redirect — akceptuj tylko ścieżki względne LUB pełne URL
        // wskazujące na nasz host. Porównanie hostów eliminuje atak typu
        // "http://localhost:8002.attacker.com/login" który zaczyna się od SITE_URL.
        $isRelative = str_starts_with($url, '/') && !str_starts_with($url, '//');
        $isSameHost = false;
        if (!$isRelative) {
            $urlHost  = parse_url($url, PHP_URL_HOST);
            $siteHost = parse_url(SITE_URL, PHP_URL_HOST);
            $isSameHost = ($urlHost !== null && $urlHost !== false && $urlHost === $siteHost);
        }
        if (!$isRelative && !$isSameHost) {
            $url = '/';
        }
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }

    public function json(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public function view(string $view, array $data = [], ?string $layout = 'templates/AppLayout'): never
    {
        $viewPath = $this->resolveViewPath($view);

        // Render view content
        ob_start();
        extract($data, EXTR_SKIP);
        include $viewPath;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            exit;
        }

        // Render layout. UWAGA: drugi `extract($data, EXTR_SKIP)` byłby
        // no-op (zmienne już zdefiniowane w lokalnym scope przez pierwszy extract).
        // `$content` natomiast jest świeży — potrzebujemy go w layoucie.
        // EXTR_SKIP zachowuje istniejące zmienne, więc kolejność jest bezpieczna.
        $layoutPath = $this->resolveViewPath($layout);
        extract(['content' => $content] + $data, EXTR_SKIP);
        include $layoutPath;
        exit;
    }

    public function withFlash(string $type, string $message): static
    {
        Session::flash($type, $message);
        return $this;
    }

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;
        http_response_code($code);
        return $this;
    }

    public function send(string $content, int $statusCode = 200, string $contentType = 'text/html; charset=UTF-8'): never
    {
        http_response_code($statusCode);
        header('Content-Type: ' . $contentType);
        echo $content;
        exit;
    }

    private function resolveViewPath(string $view): string
    {
        $path = realpath(VIEWS_PATH . '/' . $view . '.php');

        if ($path === false || !str_starts_with($path, realpath(VIEWS_PATH))) {
            throw new \RuntimeException('View not found or path traversal detected: ' . $view);
        }

        return $path;
    }
}
