<?php
declare(strict_types=1);

// 1. Composer autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 2. Konfiguracja (dotenv + stałe)
require_once dirname(__DIR__) . '/config/config.php';

// 3. Error handling
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 4. HTTP Security Headers (audit K2)
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; frame-ancestors 'none'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// 5. Session start
use App\Core\Session;
Session::start();

// 6. Request + Response
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\NotFoundException;

$request  = new Request();
$response = new Response();

// 7. Zależności
use App\Core\Database;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Repositories\UserRepository;

$db       = Database::getInstance();
$userRepo = new UserRepository($db);
$authSvc  = new AuthService($userRepo, $db);
$authMw   = new AuthMiddleware($response);

// 8. Router
$router = new Router();

// Publiczne trasy
$router->get('/',          fn() => $response->redirect('/login'));
$router->get('/login',     [new AuthController($request, $response, $authSvc), 'showLogin']);
$router->post('/login',    [new AuthController($request, $response, $authSvc), 'processLogin']);
$router->get('/register',  [new AuthController($request, $response, $authSvc), 'showRegister']);
$router->post('/register', [new AuthController($request, $response, $authSvc), 'processRegister']);
$router->post('/logout',   [new AuthController($request, $response, $authSvc), 'logout']);

// Chronione trasy
$router->group('/dashboard', [[$authMw, 'handle']], function (Router $r) use ($request, $response, $db) {
    $treeRepo = new \App\Repositories\TreeRepository($db);
    $r->get('', [new HomeController($request, $response, $treeRepo), 'index']);
});

// 9. Dispatch
try {
    $router->dispatch($request);
} catch (NotFoundException) {
    http_response_code(404);
    echo '<h1>404 — Strona nie istnieje</h1>';
} catch (\Throwable $e) {
    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
    } else {
        echo '<h1>Błąd serwera. Spróbuj ponownie.</h1>';
    }
}
