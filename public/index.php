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
use App\Controllers\TreeController;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Services\TreeService;
use App\Repositories\UserRepository;
use App\Repositories\TreeRepository;

$db       = Database::getInstance();
$userRepo = new UserRepository($db);
$treeRepo = new TreeRepository($db);
$authSvc  = new AuthService($userRepo, $db);
$treeSvc  = new TreeService($treeRepo);
$authMw   = new AuthMiddleware($response);

// 8. Router
$router = new Router();

// Publiczne trasy
$router->get('/',                  fn() => $response->redirect('/login'));
$router->get('/login',             [new AuthController($request, $response, $authSvc), 'showLogin']);
$router->post('/login',            [new AuthController($request, $response, $authSvc), 'processLogin']);
$router->get('/register',          [new AuthController($request, $response, $authSvc), 'showRegister']);
$router->post('/register',         [new AuthController($request, $response, $authSvc), 'processRegister']);
$router->post('/logout',           [new AuthController($request, $response, $authSvc), 'logout']);
$router->get('/forgot-password',   fn() => $response->view('pages/auth/forgot-password', ['title' => 'Resetuj hasło'], 'templates/AuthLayout'));
$router->post('/forgot-password',  fn() => $response->withFlash('success', 'Jeśli konto istnieje, wyślemy link resetujący.')->redirect('/login'));

// Chronione trasy
$mw = [[$authMw, 'handle']];

$router->group('/dashboard', $mw, function (Router $r) use ($request, $response, $treeRepo) {
    $r->get('', [new HomeController($request, $response, $treeRepo), 'index']);
});

$router->group('/trees', $mw, function (Router $r) use ($request, $response, $treeRepo, $treeSvc) {
    $ctrl = new TreeController($request, $response, $treeRepo, $treeSvc);
    $r->get('',            [$ctrl, 'index']);
    $r->get('/new',        [$ctrl, 'showCreate']);
    $r->post('',           [$ctrl, 'processCreate']);
    $r->get('/{id}',       [$ctrl, 'show']);
    $r->get('/{id}/edit',  [$ctrl, 'showEdit']);
    $r->post('/{id}/edit', [$ctrl, 'processEdit']);
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
