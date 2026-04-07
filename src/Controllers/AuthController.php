<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\InvitationRepository;
use App\Services\AuthService;

class AuthController
{
    public function __construct(
        private readonly Request               $request,
        private readonly Response              $response,
        private readonly AuthService           $authService,
        private readonly ?InvitationRepository $invRepo = null,
    ) {}

    public function showLogin(): never
    {
        if (Session::has('user_id')) {
            $this->response->redirect('/dashboard');
        }
        $this->response->view('pages/auth/login', ['title' => 'Logowanie'], 'templates/AuthLayout');
    }

    public function processLogin(): never
    {
        $this->request->verifyCsrf();

        $email    = trim((string)$this->request->getParam('email', ''));
        $password = (string)$this->request->getParam('password', '');

        try {
            $user = $this->authService->login($email, $password, $this->request->getIp());

            if ($user->isBlocked) {
                $this->response->withFlash('error', 'Twoje konto zostało zablokowane. Skontaktuj się z administratorem.')->redirect('/login');
            }

            Session::regenerate(true);
            Session::set('user_id',    $user->id);
            Session::set('user_name',  $user->name);
            Session::set('user_email', $user->email);
            Session::set('is_admin',   $user->isAdmin);

            if ($this->hasPendingInvitations($user->email)) {
                $this->response->redirect('/invitations');
            }

            $this->response->redirect('/dashboard');
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/login');
        }
    }

    public function showRegister(): never
    {
        if (Session::has('user_id')) {
            $this->response->redirect('/dashboard');
        }
        $this->response->view('pages/auth/register', ['title' => 'Rejestracja'], 'templates/AuthLayout');
    }

    public function processRegister(): never
    {
        $this->request->verifyCsrf();

        $name     = trim((string)$this->request->getParam('name', ''));
        $email    = trim((string)$this->request->getParam('email', ''));
        $password = (string)$this->request->getParam('password', '');
        $confirm  = (string)$this->request->getParam('password_confirm', '');

        if ($password !== $confirm) {
            $this->response->withFlash('error', 'Hasła nie są identyczne.')->redirect('/register');
        }

        try {
            $user = $this->authService->register($name, $email, $password, $this->request->getIp());
            Session::regenerate(true);
            Session::set('user_id', $user->id);
            Session::set('user_name', $user->name);
            Session::set('user_email', $user->email);

            if ($this->hasPendingInvitations($user->email)) {
                $this->response->withFlash('success', 'Konto zostało utworzone. Masz oczekujące zaproszenia!')->redirect('/invitations');
            }

            $this->response->withFlash('success', 'Konto zostało utworzone. Witaj!')->redirect('/dashboard');
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/register');
        }
    }

    public function logout(): never
    {
        $this->request->verifyCsrf();
        Session::destroy();
        $this->response->redirect('/login');
    }

    private function hasPendingInvitations(string $email): bool
    {
        if ($this->invRepo === null) {
            return false;
        }
        return count($this->invRepo->findAllActiveByEmail($email)) > 0;
    }
}
