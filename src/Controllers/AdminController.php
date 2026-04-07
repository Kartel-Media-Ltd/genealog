<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdminRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;

class AdminController
{
    public function __construct(
        private readonly Request          $request,
        private readonly Response         $response,
        private readonly AdminRepository  $adminRepo,
        private readonly UserRepository   $userRepo,
        private readonly AdminService     $adminService,
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
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
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
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
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
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
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
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
        }
    }

    /** POST /admin/users/{uid}/impersonate */
    public function impersonate(): never
    {
        $this->request->verifyCsrf();
        $uid     = (string)$this->request->getRouteParam('uid');
        $adminId = (string)Session::get('user_id');

        try {
            $target = $this->adminService->impersonate($adminId, $uid);

            // Save admin identity
            Session::set('_admin_user_id',    $adminId);
            Session::set('_admin_user_name',  Session::get('user_name'));
            Session::set('_admin_user_email', Session::get('user_email'));

            // Switch to target user session
            Session::set('user_id',    $target->id);
            Session::set('user_name',  $target->name);
            Session::set('user_email', $target->email);
            Session::set('is_admin',   false);

            // K1: Regenerate session ID after context switch
            Session::regenerate(true);

            $this->response->withFlash('info', 'Impersonujesz konto: ' . $target->name)->redirect('/dashboard');
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/admin/users/' . $uid);
        }
    }

    /** POST /admin/impersonate/exit (POZA grupą /admin — wymaga tylko AuthMiddleware) */
    public function exitImpersonate(): never
    {
        $this->request->verifyCsrf();

        $adminId        = (string)Session::get('_admin_user_id');
        $impersonatedId = (string)Session::get('user_id');

        if ($adminId === '') {
            $this->response->withFlash('error', 'Nie trwa żadna impersonacja.')->redirect('/dashboard');
        }

        try {
            $admin = $this->adminService->exitImpersonate($adminId, $impersonatedId);

            // Restore admin session — is_admin from DB, not hardcoded true
            Session::set('user_id',    $admin->id);
            Session::set('user_name',  $admin->name);
            Session::set('user_email', $admin->email);
            Session::set('is_admin',   $admin->isAdmin);
            Session::delete('_admin_user_id');
            Session::delete('_admin_user_name');
            Session::delete('_admin_user_email');

            // Regenerate session ID after context switch
            Session::regenerate(true);

            $this->response->withFlash('success', 'Zakończyłeś impersonację.')->redirect('/admin');
        } catch (\Throwable $e) {
            // Admin lost privileges during impersonation OR critical error → destroy session
            Session::destroy();
            $this->response->withFlash('error',
                'Sesja administratora wygasła: ' . $e->getMessage() . ' Zaloguj się ponownie.'
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

        $logs  = $this->adminRepo->findLogs($limit, $offset);
        $total = $this->adminRepo->countLogs();

        $this->response->view('pages/admin/logs', [
            'title'       => 'Logi administracyjne',
            'currentUser' => $this->currentUser(),
            'logs'        => $logs,
            'page'        => $page,
            'total'       => $total,
            'limit'       => $limit,
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
