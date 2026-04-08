<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdminRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;
use App\Services\AuthService;
use App\Services\NotificationService;

class AdminController
{
    /** Limit prób impersonacji per admin per godzinę */
    private const IMPERSONATE_RATE_LIMIT = 10;

    public function __construct(
        private readonly Request              $request,
        private readonly Response             $response,
        private readonly AdminRepository      $adminRepo,
        private readonly UserRepository       $userRepo,
        private readonly AdminService         $adminService,
        private readonly ?AuthService         $authService = null,
        private readonly ?NotificationService $notifications = null,
    ) {}

    /** GET /admin */
    public function dashboard(): never
    {
        $stats   = $this->adminRepo->getStats();
        $logs    = $this->adminRepo->findLogs(10, 0);

        $this->response->view('pages/admin/dashboard', [
            'title'       => 'Panel Administratora',
            'currentUser' => $this->currentUser(),
            'stats'       => $stats,
            'logs'        => $logs,
        ], 'templates/AdminLayout');
    }

    /** GET /admin/users */
    public function users(): never
    {
        $search = trim((string)$this->request->getParam('q', ''));
        $page   = max(1, (int)$this->request->getParam('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $users = $this->adminRepo->findAllUsers($search, $limit, $offset);
        $total = $this->adminRepo->countUsers($search);

        $this->response->view('pages/admin/users', [
            'title'       => 'Użytkownicy',
            'currentUser' => $this->currentUser(),
            'users'       => $users,
            'search'      => $search,
            'page'        => $page,
            'total'       => $total,
            'limit'       => $limit,
        ], 'templates/AdminLayout');
    }

    /** GET /admin/users/{uid} */
    public function userDetail(): never
    {
        $uid  = (string)$this->request->getRouteParam('uid');
        $user = $this->adminRepo->findUserById($uid);

        if ($user === null) {
            $this->response->withFlash('error', 'Użytkownik nie istnieje.')->redirect('/admin/users');
        }

        // RODO: log który admin czytał dane którego użytkownika
        $adminId = (string)Session::get('user_id');
        if ($adminId !== '' && $adminId !== $uid) {
            try {
                $this->adminService->logAction($adminId, 'view_user', 'user', $uid, [
                    'target_email' => $user['email'] ?? '',
                ]);
            } catch (\Throwable $e) {
                error_log('Admin view audit log failed: ' . $e->getMessage());
            }
        }

        $trees = $this->adminRepo->findUserTrees($uid);

        // Layout escapes $title, so don't pre-escape here (would cause double-escape)
        $this->response->view('pages/admin/user-detail', [
            'title'       => 'Użytkownik: ' . $user['name'],
            'currentUser' => $this->currentUser(),
            'user'        => $user,
            'trees'       => $trees,
        ], 'templates/AdminLayout');
    }

    /** POST /admin/users/{uid}/block */
    public function block(): never
    {
        $this->request->verifyCsrf();
        $uid     = (string)$this->request->getRouteParam('uid');
        $adminId = (string)Session::get('user_id');

        try {
            $this->adminService->block($adminId, $uid);
            $this->response->withFlash('success', 'Konto zostało zablokowane.')->redirect('/admin/users/' . $uid);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // Domenowe wyjątki — komunikat bezpieczny dla użytkownika (Polish, walidacja stanu)
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
        } catch (\Throwable $e) {
            // ZAD-1.6: nie ujawniaj szczegółów SQL/systemowych w UI — log + generic message
            error_log('AdminController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->response->withFlash('error', 'Wystąpił błąd serwera. Spróbuj ponownie.')->redirect('/admin/users/' . $uid);
        }
    }

    /** POST /admin/users/{uid}/unblock */
    public function unblock(): never
    {
        $this->request->verifyCsrf();
        $uid     = (string)$this->request->getRouteParam('uid');
        $adminId = (string)Session::get('user_id');

        try {
            $this->adminService->unblock($adminId, $uid);
            $this->response->withFlash('success', 'Konto zostało odblokowane.')->redirect('/admin/users/' . $uid);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // Domenowe wyjątki — komunikat bezpieczny dla użytkownika (Polish, walidacja stanu)
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
        } catch (\Throwable $e) {
            // ZAD-1.6: nie ujawniaj szczegółów SQL/systemowych w UI — log + generic message
            error_log('AdminController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->response->withFlash('error', 'Wystąpił błąd serwera. Spróbuj ponownie.')->redirect('/admin/users/' . $uid);
        }
    }

    /** POST /admin/users/{uid}/promote */
    public function promote(): never
    {
        $this->request->verifyCsrf();
        $uid     = (string)$this->request->getRouteParam('uid');
        $adminId = (string)Session::get('user_id');

        try {
            $this->adminService->promote($adminId, $uid);
            $this->response->withFlash('success', 'Użytkownik został mianowany administratorem.')->redirect('/admin/users/' . $uid);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // Domenowe wyjątki — komunikat bezpieczny dla użytkownika (Polish, walidacja stanu)
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
        } catch (\Throwable $e) {
            // ZAD-1.6: nie ujawniaj szczegółów SQL/systemowych w UI — log + generic message
            error_log('AdminController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->response->withFlash('error', 'Wystąpił błąd serwera. Spróbuj ponownie.')->redirect('/admin/users/' . $uid);
        }
    }

    /** POST /admin/users/{uid}/demote */
    public function demote(): never
    {
        $this->request->verifyCsrf();
        $uid     = (string)$this->request->getRouteParam('uid');
        $adminId = (string)Session::get('user_id');

        try {
            $this->adminService->demote($adminId, $uid);
            $this->response->withFlash('success', 'Uprawnienia administratora zostały cofnięte.')->redirect('/admin/users/' . $uid);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // Domenowe wyjątki — komunikat bezpieczny dla użytkownika (Polish, walidacja stanu)
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
        } catch (\Throwable $e) {
            // ZAD-1.6: nie ujawniaj szczegółów SQL/systemowych w UI — log + generic message
            error_log('AdminController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->response->withFlash('error', 'Wystąpił błąd serwera. Spróbuj ponownie.')->redirect('/admin/users/' . $uid);
        }
    }

    /** POST /admin/users/{uid}/impersonate */
    public function impersonate(): never
    {
        $this->request->verifyCsrf();
        $uid     = (string)$this->request->getRouteParam('uid');
        $adminId = (string)Session::get('user_id');

        // Rate limit: max 10 impersonacji/godzinę per admin (chroni przed nadużyciem)
        if ($this->authService !== null) {
            $endpoint = 'impersonate:' . $adminId;
            if ($this->authService->isRateLimited($this->request->getIp(), $endpoint)) {
                $this->response->withFlash('error',
                    'Przekroczono limit impersonacji. Spróbuj za godzinę.'
                )->redirect('/admin/users/' . $uid);
            }
            $this->authService->recordAttempt($this->request->getIp(), $endpoint);
        }

        try {
            $target = $this->adminService->impersonate($adminId, $uid);

            // Save admin identity + start time (dla powiadomienia po exit)
            Session::set('_admin_user_id',    $adminId);
            Session::set('_admin_user_name',  Session::get('user_name'));
            Session::set('_admin_user_email', Session::get('user_email'));
            Session::set('_impersonate_started_at', date('c'));

            // Switch to target user session — pobieramy session_version z DB
            $targetSessionVersion = $this->userRepo->getSessionVersion($target->id) ?? 0;
            Session::set('user_id',         $target->id);
            Session::set('user_name',       $target->name);
            Session::set('user_email',      $target->email);
            Session::set('is_admin',        false);
            Session::set('session_version', $targetSessionVersion);

            Session::regenerate(true);

            $this->response->withFlash('info', 'Impersonujesz konto: ' . $target->name)->redirect('/dashboard');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // Domenowe wyjątki — komunikat bezpieczny dla użytkownika (Polish, walidacja stanu)
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
        } catch (\Throwable $e) {
            // ZAD-1.6: nie ujawniaj szczegółów SQL/systemowych w UI — log + generic message
            error_log('AdminController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->response->withFlash('error', 'Wystąpił błąd serwera. Spróbuj ponownie.')->redirect('/admin/users/' . $uid);
        }
    }

    /** POST /admin/impersonate/exit (POZA grupą /admin — wymaga tylko AuthMiddleware) */
    public function exitImpersonate(): never
    {
        $this->request->verifyCsrf();

        $adminId        = (string)Session::get('_admin_user_id');
        $impersonatedId = (string)Session::get('user_id');
        $startedAtStr   = (string)Session::get('_impersonate_started_at', '');

        if ($adminId === '') {
            $this->response->withFlash('error', 'Nie trwa żadna impersonacja.')->redirect('/dashboard');
        }

        try {
            $admin = $this->adminService->exitImpersonate($adminId, $impersonatedId);

            // Powiadomienie RODO: użytkownik dostaje informację że admin używał jego konta
            if ($this->notifications !== null && $impersonatedId !== '') {
                try {
                    $startedAt = $startedAtStr !== ''
                        ? new \DateTimeImmutable($startedAtStr)
                        : new \DateTimeImmutable();
                    $this->notifications->notifyImpersonationEnded($impersonatedId, $admin->name, $startedAt);
                } catch (\Throwable $e) {
                    error_log('Notification dispatch failed (impersonation_ended): ' . $e->getMessage());
                }
            }

            // Restore admin session — is_admin from DB, not hardcoded true
            $adminSessionVersion = $this->userRepo->getSessionVersion($admin->id) ?? 0;
            Session::set('user_id',         $admin->id);
            Session::set('user_name',       $admin->name);
            Session::set('user_email',      $admin->email);
            Session::set('is_admin',        $admin->isAdmin);
            Session::set('session_version', $adminSessionVersion);
            Session::delete('_admin_user_id');
            Session::delete('_admin_user_name');
            Session::delete('_admin_user_email');
            Session::delete('_impersonate_started_at');

            Session::regenerate(true);

            $this->response->withFlash('success', 'Zakończyłeś impersonację.')->redirect('/admin');
        } catch (\Throwable $e) {
            // Admin lost privileges during impersonation OR critical error → destroy session
            error_log('Admin exitImpersonate failed: ' . $e->getMessage());
            Session::destroy();
            $this->response->withFlash('error',
                'Sesja administratora wygasła. Zaloguj się ponownie.'
            )->redirect('/login');
        }
    }

    /** GET /admin/trees */
    public function trees(): never
    {
        $page   = max(1, (int)$this->request->getParam('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $trees = $this->adminRepo->findAllTrees($limit, $offset);
        $total = $this->adminRepo->countTrees();

        $this->response->view('pages/admin/trees', [
            'title'       => 'Drzewa',
            'currentUser' => $this->currentUser(),
            'trees'       => $trees,
            'page'        => $page,
            'total'       => $total,
            'limit'       => $limit,
        ], 'templates/AdminLayout');
    }

    /** GET /admin/logs */
    public function logs(): never
    {
        $page   = max(1, (int)$this->request->getParam('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $filters = [
            'action'    => trim((string)$this->request->getParam('action', '')),
            'admin_id'  => trim((string)$this->request->getParam('admin_id', '')),
            'target_id' => trim((string)$this->request->getParam('target_id', '')),
            'date_from' => trim((string)$this->request->getParam('date_from', '')),
            'date_to'   => trim((string)$this->request->getParam('date_to', '')),
        ];

        $logs           = $this->adminRepo->findLogs($limit, $offset, $filters);
        $total          = $this->adminRepo->countLogs($filters);
        $availableActions = $this->adminRepo->getLogActions();

        $this->response->view('pages/admin/logs', [
            'title'            => 'Logi administracyjne',
            'currentUser'      => $this->currentUser(),
            'logs'             => $logs,
            'page'             => $page,
            'total'            => $total,
            'limit'            => $limit,
            'filters'          => $filters,
            'availableActions' => $availableActions,
        ], 'templates/AdminLayout');
    }

    private function currentUser(): array
    {
        return [
            'name'   => Session::get('user_name', 'Admin'),
            'email'  => Session::get('user_email', ''),
            'avatar' => '',
        ];
    }
}
