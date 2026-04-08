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
use App\Services\AccountDeletionService;
use App\Services\DataExportService;
use App\Services\PasswordResetService;
use App\Services\PersonService;
use App\Services\RegisterService;
use App\Services\RelationshipService;
use App\Services\SuggestionService;
use App\Services\TreeService;
use App\Controllers\DiscoveryController;
use App\Controllers\NotificationController;
use App\Services\Discovery\FingerprintService;
use App\Services\Discovery\GlobalIndexService;
use App\Services\Discovery\MatchingService;
use App\Services\Discovery\MatchSourceRegistry;
use App\Services\Discovery\PersonImportService;
use App\Services\Discovery\Sources\LocalTreeMatchSource;
use App\Services\Discovery\Sources\CrossTreeMatchSource;
use App\Services\Discovery\Sources\FamilySearchMatchSource;
use App\Services\Discovery\Sources\GenetykaMatchSource;
use App\Services\NotificationService;
use App\Repositories\DiscoveryRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\AdminRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;
use App\Repositories\TreeRepository;
use App\Repositories\UserRepository;
use App\Core\EventDispatcher;
use App\Core\RateLimiter;

$db          = Database::getInstance();
$userRepo    = new UserRepository($db);
$treeRepo    = new TreeRepository($db);
$personRepo  = new PersonRepository($db);
$relRepo     = new RelationshipRepository($db);
$adminRepo   = new AdminRepository($db);
$rateLimiter = new RateLimiter($db);
$authSvc     = new AuthService($userRepo, $db, $rateLimiter);
$treeSvc     = new TreeService($treeRepo);
$personSvc   = new PersonService($personRepo, $treeRepo);
$relSvc      = new RelationshipService($relRepo, $personRepo);
$suggSvc     = new SuggestionService($relRepo, $personRepo);
$registerSvc = new RegisterService($personRepo, $relRepo);
$mediaSvc    = new MediaService($personRepo);
$adminSvc    = new AdminService($userRepo, $adminRepo);
$accountDelSvc = new AccountDeletionService($db, $userRepo, $treeRepo);
$dataExportSvc = new DataExportService($db, $userRepo, $treeRepo, $personRepo, $relRepo);
$invRepo     = new InvitationRepository($db);
$emailSvc    = new EmailService();
$passwordResetSvc = new PasswordResetService($db, $userRepo, $emailSvc);
$invSvc      = new InvitationService($treeRepo, $invRepo, $userRepo, $emailSvc);
$authMw      = new AuthMiddleware($response, $userRepo);
$adminMw     = new AdminMiddleware($response, $userRepo);

// Discovery — fingerprint + global index + MatchSourceRegistry + EventDispatcher hooks
$fingerprintSvc  = new FingerprintService();
$globalIndexSvc  = new GlobalIndexService($db, $fingerprintSvc, $personRepo, $treeRepo, $userRepo);
$discoveryRepo   = new DiscoveryRepository($db);
$notifRepo       = new NotificationRepository($db);
$notifSvc        = new NotificationService($notifRepo);
$matchRegistry   = new MatchSourceRegistry();
$matchRegistry->register(new LocalTreeMatchSource($db, $fingerprintSvc));
$matchRegistry->register(new CrossTreeMatchSource($db, $fingerprintSvc));
// External sources — rejestrowane warunkowo, isAvailable() pilnuje samo wyłączenia
$matchRegistry->register(new FamilySearchMatchSource(getenv('FAMILYSEARCH_CLIENT_ID') ?: null));
$matchRegistry->register(new GenetykaMatchSource(getenv('GENETEKA_LOCAL_DB') ?: null));
$matchingSvc     = new MatchingService($matchRegistry, $notifSvc, $treeRepo, $discoveryRepo);
$personImportSvc = new PersonImportService($db, $discoveryRepo, $personSvc);

EventDispatcher::on('person.created', static function ($person, $userId) use ($globalIndexSvc, $matchingSvc) {
    $globalIndexSvc->indexPerson($person);
    $matchingSvc->findAndNotifyMatches($person, $userId);
});
EventDispatcher::on('person.updated', static function ($person, $userId) use ($globalIndexSvc) {
    $globalIndexSvc->indexPerson($person);
});
EventDispatcher::on('person.deleted', static function ($personId) use ($globalIndexSvc) {
    $globalIndexSvc->unindexPerson($personId);
});
EventDispatcher::on('tree.indexed_globally.disabled', static function ($treeId) use ($globalIndexSvc) {
    $globalIndexSvc->unindexTree($treeId);
});

// 8. Router
$router = new Router();

// Publiczne trasy
$router->get('/',                  fn() => $response->redirect('/login'));

// ZAD-4.7 (D8): health-check endpoint — load balancer / k8s probes
$router->get('/health', function () use ($request, $response, $treeRepo, $personRepo, $relRepo) {
    $apiCtrl = new ApiController($request, $response, $treeRepo, $personRepo, $relRepo);
    $apiCtrl->health();
});

$authCtrl = new AuthController($request, $response, $authSvc, $invRepo, $passwordResetSvc);
$router->get('/login',             [$authCtrl, 'showLogin']);
$router->post('/login',            [$authCtrl, 'processLogin']);
$router->get('/register',          [$authCtrl, 'showRegister']);
$router->post('/register',         [$authCtrl, 'processRegister']);
$router->post('/logout',           [$authCtrl, 'logout']);
$router->get('/forgot-password',   [$authCtrl, 'showForgot']);
$router->post('/forgot-password',  [$authCtrl, 'processForgot']);
$router->get('/reset-password/{token}',  [$authCtrl, 'showReset']);
$router->post('/reset-password/{token}', [$authCtrl, 'processReset']);

// ZAD-1.3 (K3): Privacy Policy + Terms of Service — wymagane RODO Art. 13-14
$router->get('/privacy', function () use ($response) {
    $response->view('pages/privacy', ['title' => 'Polityka prywatności'], 'templates/AuthLayout');
});
$router->get('/terms', function () use ($response) {
    $response->view('pages/terms', ['title' => 'Regulamin'], 'templates/AuthLayout');
});

// Publiczne trasy zaproszeń
// Public invitation controller — needs rateLimiter from outer scope
$invCtrlPublic = new InvitationController($request, $response, $treeRepo, $invRepo, $invSvc, $rateLimiter);
$router->get('/invite/{token}',         [$invCtrlPublic, 'showAccept']);
$router->post('/invite/{token}/accept', [$invCtrlPublic, 'processAccept']);

// Chronione trasy
$mw = [[$authMw, 'handle']];

// Lista oczekujących zaproszeń (chroniona)
$router->group('/invitations', $mw, function (Router $r) use ($request, $response, $treeRepo, $invRepo, $invSvc, $rateLimiter) {
    $ctrl = new InvitationController($request, $response, $treeRepo, $invRepo, $invSvc, $rateLimiter);
    $r->get('', [$ctrl, 'pendingList']);
});

$router->group('/dashboard', $mw, function (Router $r) use ($request, $response, $treeRepo) {
    $r->get('', [new HomeController($request, $response, $treeRepo), 'index']);
});

// Profile
$router->group('/profile', $mw, function (Router $r) use ($request, $response, $userRepo, $authSvc) {
    $ctrl = new ProfileController($request, $response, $userRepo, $authSvc);
    $r->get('',          [$ctrl, 'show']);
    $r->post('',         [$ctrl, 'updateProfile']);
    $r->post('/password', [$ctrl, 'changePassword']);
    $r->post('/email',    [$ctrl, 'changeEmail']);
});

// Settings
$router->group('/settings', $mw, function (Router $r) use (
    $request, $response, $userRepo, $accountDelSvc, $dataExportSvc, $rateLimiter
) {
    $ctrl = new SettingsController($request, $response, $userRepo, $accountDelSvc, $dataExportSvc, $rateLimiter);
    $r->get('',                  [$ctrl, 'show']);
    $r->post('/notifications',   [$ctrl, 'updateNotifications']);
    $r->post('/locale',          [$ctrl, 'updateLocale']);
    $r->post('/delete',          [$ctrl, 'deleteAccount']);
    $r->post('/restrict',        [$ctrl, 'restrictAccount']); // RODO Art. 18 (ZAD-3.2)
    $r->post('/export-data',     [$ctrl, 'exportData']); // RODO Art. 20 — POST + CSRF (ZAD-2.1)
});

$router->group('/trees', $mw, function (Router $r) use (
    $request, $response,
    $treeRepo, $treeSvc,
    $personRepo, $personSvc, $mediaSvc,
    $relRepo, $relSvc, $suggSvc, $registerSvc,
    $invRepo, $invSvc,
    $matchingSvc, $discoveryRepo, $globalIndexSvc, $db, $personImportSvc, $rateLimiter,
) {
    $treeCtrl      = new TreeController($request, $response, $treeRepo, $treeSvc);
    $personCtrl    = new PersonController($request, $response, $treeRepo, $personRepo, $personSvc, $mediaSvc, $relRepo, $suggSvc, $registerSvc, $discoveryRepo);
    $relCtrl       = new RelationshipController($request, $response, $treeRepo, $personRepo, $relRepo, $relSvc, $suggSvc);
    $suggCtrl      = new SuggestionController($request, $response, $treeRepo, $personRepo, $relSvc);
    $invCtrl       = new InvitationController($request, $response, $treeRepo, $invRepo, $invSvc, $rateLimiter);
    $discoveryCtrl = new DiscoveryController($request, $response, $treeRepo, $matchingSvc, $discoveryRepo, $globalIndexSvc, $db, $personImportSvc, $rateLimiter);

    // Discovery — ustawienia drzewa (cross-tree opt-in/out)
    $r->get('/{id}/settings/discovery',  [$discoveryCtrl, 'showSettings']);
    $r->post('/{id}/settings/discovery', [$discoveryCtrl, 'updateSettings']);

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
    $gedcomCtrl = new GedcomController($request, $response, $treeRepo, $personRepo, $relRepo, $rateLimiter, $discoveryRepo);
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
$router->group('/api', $mw, function (Router $r) use (
    $request, $response, $treeRepo, $personRepo, $relRepo,
    $matchingSvc, $discoveryRepo, $globalIndexSvc, $db,
    $notifRepo, $personImportSvc, $rateLimiter
) {
    $apiCtrl       = new ApiController($request, $response, $treeRepo, $personRepo, $relRepo);
    $discoveryCtrl = new DiscoveryController($request, $response, $treeRepo, $matchingSvc, $discoveryRepo, $globalIndexSvc, $db, $personImportSvc, $rateLimiter);
    $notifCtrl     = new NotificationController($request, $response, $notifRepo);

    $r->get('/trees/{id}/persons', [$apiCtrl, 'personsForTree']);

    // Person Discovery
    $r->get('/discovery/search',              [$discoveryCtrl, 'search']);
    $r->post('/discovery/match/{id}/import',  [$discoveryCtrl, 'importMatch']);
    $r->post('/discovery/match/{id}/reject',  [$discoveryCtrl, 'rejectMatch']);

    // Notifications
    $r->get('/notifications',              [$notifCtrl, 'list']);
    $r->get('/notifications/count',        [$notifCtrl, 'count']);
    $r->post('/notifications/read-all',    [$notifCtrl, 'markAllRead']);
    $r->post('/notifications/{id}/read',   [$notifCtrl, 'markRead']);
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
