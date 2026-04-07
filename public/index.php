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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; frame-ancestors 'none'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// HSTS — wymuś HTTPS dla powracających użytkowników (1 rok, włącznie z subdomenami).
// Tylko gdy aktualne żądanie idzie przez HTTPS — ustawianie nagłówka po HTTP jest no-op.
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

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
use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\InvitationController;
use App\Controllers\PersonController;
use App\Controllers\ProfileController;
use App\Controllers\RelationshipController;
use App\Controllers\SettingsController;
use App\Controllers\SuggestionController;
use App\Controllers\GedcomController;
use App\Controllers\TreeController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\AdminService;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\InvitationService;
use App\Services\MediaService;
use App\Services\PersonService;
use App\Services\RegisterService;
use App\Services\RelationshipService;
use App\Services\SuggestionService;
use App\Services\TreeService;
use App\Repositories\AdminRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;
use App\Repositories\TreeRepository;
use App\Repositories\UserRepository;

$db          = Database::getInstance();
$userRepo    = new UserRepository($db);
$treeRepo    = new TreeRepository($db);
$personRepo  = new PersonRepository($db);
$relRepo     = new RelationshipRepository($db);
$adminRepo   = new AdminRepository($db);
$authSvc     = new AuthService($userRepo, $db);
$treeSvc     = new TreeService($treeRepo);
$personSvc   = new PersonService($personRepo, $treeRepo);
$relSvc      = new RelationshipService($relRepo, $personRepo);
$suggSvc     = new SuggestionService($relRepo, $personRepo);
$registerSvc = new RegisterService($personRepo, $relRepo);
$mediaSvc    = new MediaService($personRepo);
$adminSvc    = new AdminService($userRepo, $adminRepo);
$invRepo     = new InvitationRepository($db);
$emailSvc    = new EmailService();
$invSvc      = new InvitationService($treeRepo, $invRepo, $userRepo, $emailSvc);
$authMw      = new AuthMiddleware($response);
$adminMw     = new AdminMiddleware($response);

// 8. Router
$router = new Router();

// Publiczne trasy
$router->get('/',                  fn() => $response->redirect('/login'));
$authCtrl = new AuthController($request, $response, $authSvc, $invRepo);
$router->get('/login',             [$authCtrl, 'showLogin']);
$router->post('/login',            [$authCtrl, 'processLogin']);
$router->get('/register',          [$authCtrl, 'showRegister']);
$router->post('/register',         [$authCtrl, 'processRegister']);
$router->post('/logout',           [$authCtrl, 'logout']);
$router->get('/forgot-password',   fn() => $response->view('pages/auth/forgot-password', ['title' => 'Resetuj hasło'], 'templates/AuthLayout'));
$router->post('/forgot-password',  fn() => $response->withFlash('success', 'Jeśli konto istnieje, wyślemy link resetujący.')->redirect('/login'));

// Publiczne trasy zaproszeń
$invCtrlPublic = new InvitationController($request, $response, $treeRepo, $invRepo, $invSvc);
$router->get('/invite/{token}',         [$invCtrlPublic, 'showAccept']);
$router->post('/invite/{token}/accept', [$invCtrlPublic, 'processAccept']);

// Chronione trasy
$mw = [[$authMw, 'handle']];

// Lista oczekujących zaproszeń (chroniona)
$router->group('/invitations', $mw, function (Router $r) use ($request, $response, $treeRepo, $invRepo, $invSvc) {
    $ctrl = new InvitationController($request, $response, $treeRepo, $invRepo, $invSvc);
    $r->get('', [$ctrl, 'pendingList']);
});

$router->group('/dashboard', $mw, function (Router $r) use ($request, $response, $treeRepo) {
    $r->get('', [new HomeController($request, $response, $treeRepo), 'index']);
});

// Profile
$router->group('/profile', $mw, function (Router $r) use ($request, $response, $userRepo) {
    $ctrl = new ProfileController($request, $response, $userRepo);
    $r->get('',          [$ctrl, 'show']);
    $r->post('',         [$ctrl, 'updateProfile']);
    $r->post('/password', [$ctrl, 'changePassword']);
    $r->post('/email',    [$ctrl, 'changeEmail']);
});

// Settings
$router->group('/settings', $mw, function (Router $r) use ($request, $response, $userRepo) {
    $ctrl = new SettingsController($request, $response, $userRepo);
    $r->get('',                  [$ctrl, 'show']);
    $r->post('/notifications',   [$ctrl, 'updateNotifications']);
    $r->post('/locale',          [$ctrl, 'updateLocale']);
    $r->post('/delete',          [$ctrl, 'deleteAccount']);
});

$router->group('/trees', $mw, function (Router $r) use (
    $request, $response,
    $treeRepo, $treeSvc,
    $personRepo, $personSvc, $mediaSvc,
    $relRepo, $relSvc, $suggSvc, $registerSvc,
    $invRepo, $invSvc,
) {
    $treeCtrl   = new TreeController($request, $response, $treeRepo, $treeSvc);
    $personCtrl = new PersonController($request, $response, $treeRepo, $personRepo, $personSvc, $mediaSvc, $relRepo, $suggSvc, $registerSvc);
    $relCtrl    = new RelationshipController($request, $response, $treeRepo, $personRepo, $relRepo, $relSvc, $suggSvc);
    $suggCtrl   = new SuggestionController($request, $response, $treeRepo, $personRepo, $relSvc);
    $invCtrl    = new InvitationController($request, $response, $treeRepo, $invRepo, $invSvc);

    // Tree CRUD
    $r->get('',             [$treeCtrl, 'index']);
    $r->get('/new',         [$treeCtrl, 'showCreate']);
    $r->post('',            [$treeCtrl, 'processCreate']);
    $r->get('/{id}',        [$treeCtrl, 'show']);
    $r->get('/{id}/edit',   [$treeCtrl, 'showEdit']);
    $r->post('/{id}/edit',  [$treeCtrl, 'processEdit']);
    $r->get('/{id}/print',  [$treeCtrl, 'printView']);

    // Person CRUD — /print and /new MUST be before /{pid}
    $r->get('/{id}/persons',                                    [$personCtrl, 'index']);
    $r->get('/{id}/persons/print',                              [$personCtrl, 'printList']);
    $r->get('/{id}/persons/new',                                [$personCtrl, 'showCreate']);
    $r->post('/{id}/persons',                                   [$personCtrl, 'processCreate']);
    $r->get('/{id}/persons/{pid}',                              [$personCtrl, 'show']);
    $r->get('/{id}/persons/{pid}/edit',                         [$personCtrl, 'showEdit']);
    $r->get('/{id}/persons/{pid}/register',                     [$personCtrl, 'showRegister']);
    $r->post('/{id}/persons/{pid}/edit',                        [$personCtrl, 'processEdit']);
    $r->post('/{id}/persons/{pid}/photo',                       [$personCtrl, 'uploadPhoto']);
    $r->post('/{id}/persons/{pid}/delete',                      [$personCtrl, 'delete']);
    $r->post('/{id}/persons/{pid}/suggestions',                 [$suggCtrl,   'apply']);

    // Relationships
    $r->get('/{id}/persons/{pid}/relationships/new',            [$relCtrl, 'showCreate']);
    $r->post('/{id}/persons/{pid}/relationships',               [$relCtrl, 'processCreate']);
    $r->post('/{id}/relationships/{rid}/delete',                [$relCtrl, 'delete']);

    // GEDCOM Import / Eksport
    $gedcomCtrl = new GedcomController($request, $response, $treeRepo, $personRepo, $relRepo);
    $r->get('/{id}/gedcom',               [$gedcomCtrl, 'page']);
    $r->post('/{id}/gedcom/import',       [$gedcomCtrl, 'import']);
    $r->get('/{id}/gedcom/export',        [$gedcomCtrl, 'export']);

    // Members & Invitations
    $r->get('/{id}/members',               [$invCtrl, 'members']);
    $r->post('/{id}/invite',               [$invCtrl, 'invite']);
    $r->post('/{id}/members/{uid}/remove', [$invCtrl, 'removeMember']);
    $r->post('/{id}/members/{uid}/role',   [$invCtrl, 'changeRole']);
});

// API routes (auth required)
$router->group('/api', $mw, function (Router $r) use ($request, $response, $treeRepo, $personRepo, $relRepo) {
    $apiCtrl = new ApiController($request, $response, $treeRepo, $personRepo, $relRepo);
    $r->get('/trees/{id}/persons', [$apiCtrl, 'personsForTree']);
});

// /admin/impersonate/exit MUSI być POZA grupą /admin (AdminMiddleware blokuje is_admin=false,
// a podczas impersonacji is_admin jest false — wymagamy tylko AuthMiddleware).
$exitImpersonateCtrl = new AdminController($request, $response, $adminRepo, $userRepo, $adminSvc);
$router->post('/admin/impersonate/exit', [$exitImpersonateCtrl, 'exitImpersonate'], $mw);

// Admin routes (AdminMiddleware)
$adminMwArr = [[$adminMw, 'handle']];
$router->group('/admin', $adminMwArr, function (Router $r) use ($request, $response, $adminRepo, $userRepo, $adminSvc) {
    $ctrl = new AdminController($request, $response, $adminRepo, $userRepo, $adminSvc);
    $r->get('',                             [$ctrl, 'dashboard']);
    $r->get('/users',                       [$ctrl, 'users']);
    $r->get('/users/{uid}',                 [$ctrl, 'userDetail']);
    $r->post('/users/{uid}/block',          [$ctrl, 'block']);
    $r->post('/users/{uid}/unblock',        [$ctrl, 'unblock']);
    $r->post('/users/{uid}/promote',        [$ctrl, 'promote']);
    $r->post('/users/{uid}/demote',         [$ctrl, 'demote']);
    $r->post('/users/{uid}/impersonate',    [$ctrl, 'impersonate']);
    $r->get('/trees',                       [$ctrl, 'trees']);
    $r->get('/logs',                        [$ctrl, 'logs']);
});

// 9. Dispatch
try {
    $router->dispatch($request);
} catch (NotFoundException) {
    http_response_code(404);
    echo '<h1>404 — Strona nie istnieje</h1>';
} catch (\RuntimeException $e) {
    // CSRF mismatch i podobne security exceptions
    if (str_contains($e->getMessage(), '403') || str_contains($e->getMessage(), 'CSRF')) {
        http_response_code(403);
        echo '<h1>403 — Brak uprawnień</h1>';
        if (APP_DEBUG) {
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        }
    } else {
        http_response_code(500);
        if (APP_DEBUG) {
            echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
        } else {
            echo '<h1>Błąd serwera. Spróbuj ponownie.</h1>';
        }
    }
} catch (\Throwable $e) {
    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
    } else {
        echo '<h1>Błąd serwera. Spróbuj ponownie.</h1>';
    }
}
