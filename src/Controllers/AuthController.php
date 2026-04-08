<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\InvitationRepository;
use App\Services\AuthService;
use App\Services\PasswordResetService;

class AuthController
{
    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly AuthService            $authService,
        private readonly ?InvitationRepository  $invRepo = null,
        private readonly ?PasswordResetService  $passwordReset = null,
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

            // ZAD-3.2 (P11): RODO Art. 18 — konto zawieszone przez użytkownika.
            // Automatyczne wycofanie ograniczenia przy udanym logowaniu (udowadnia że to
            // właściciel konta wraca i chce kontynuować). Zgodne z UX intencją użytkownika
            // który zawieszał konto.
            if ($user->isRestricted) {
                $this->authService->clearRestriction($user->id);
            }

            Session::regenerate(true);
            Session::set('user_id',         $user->id);
            Session::set('user_name',       $user->name);
            Session::set('user_email',      $user->email);
            Session::set('is_admin',        $user->isAdmin);
            Session::set('session_version', $user->sessionVersion);

            $pendingToken = Session::get('pending_invitation');
            if ($pendingToken !== null) {
                Session::delete('pending_invitation');
                $this->response->redirect('/invite/' . $pendingToken);
            }

            if ($this->hasPendingInvitations($user->email)) {
                $this->response->redirect('/invitations');
            }

            $this->response->redirect('/dashboard');
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/login');
        } catch (\Throwable $e) {
            error_log('Login failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Wystąpił błąd logowania. Spróbuj ponownie.')->redirect('/login');
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
        $consent  = (string)$this->request->getParam('consent', '');

        // ZAD-1.3 (K3): consent flow — wymagane dla podstawy prawnej przetwarzania
        // (RODO Art. 6(1)(a)/(b) — zgoda lub wykonanie umowy). Brak akceptacji regulaminu
        // oznacza brak podstawy prawnej → rejestracja musi być odrzucona.
        if ($consent !== '1') {
            $this->response->withFlash('error', 'Akceptacja Regulaminu i Polityki prywatności jest wymagana.')
                ->redirect('/register');
        }

        if ($password !== $confirm) {
            $this->response->withFlash('error', 'Hasła nie są identyczne.')->redirect('/register');
        }

        try {
            $user = $this->authService->register($name, $email, $password, $this->request->getIp());
            Session::regenerate(true);
            Session::set('user_id',         $user->id);
            Session::set('user_name',       $user->name);
            Session::set('user_email',      $user->email);
            Session::set('is_admin',        $user->isAdmin);
            Session::set('session_version', $user->sessionVersion);

            $pendingToken = Session::get('pending_invitation');
            if ($pendingToken !== null) {
                Session::delete('pending_invitation');
                $this->response->withFlash('success', 'Konto zostało utworzone. Witaj!')->redirect('/invite/' . $pendingToken);
            }

            if ($this->hasPendingInvitations($user->email)) {
                $this->response->withFlash('success', 'Konto zostało utworzone. Masz oczekujące zaproszenia!')->redirect('/invitations');
            }

            $this->response->withFlash('success', 'Konto zostało utworzone. Witaj!')->redirect('/dashboard');
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/register');
        } catch (\Throwable $e) {
            error_log('Register failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Wystąpił błąd rejestracji. Spróbuj ponownie.')->redirect('/register');
        }
    }

    public function logout(): never
    {
        $this->request->verifyCsrf();
        Session::destroy();
        $this->response->redirect('/login');
    }

    /** GET /forgot-password */
    public function showForgot(): never
    {
        $this->response->view('pages/auth/forgot-password', ['title' => 'Resetuj hasło'], 'templates/AuthLayout');
    }

    /** POST /forgot-password */
    public function processForgot(): never
    {
        $this->request->verifyCsrf();
        $email = trim((string)$this->request->getParam('email', ''));

        if ($this->passwordReset !== null) {
            try {
                $this->passwordReset->initiate($email, $this->request->getIp());
            } catch (\Throwable $e) {
                error_log('Password reset initiate failed: ' . $e->getMessage());
                // Nie ujawniamy błędu — zawsze ten sam komunikat (anti-enumeration)
            }
        }

        $this->response
            ->withFlash('success', 'Jeśli konto istnieje, wysłaliśmy link resetujący na podany email.')
            ->redirect('/login');
    }

    /** GET /reset-password/{token} */
    public function showReset(): never
    {
        $token = (string)$this->request->getRouteParam('token');

        if ($this->passwordReset === null || $this->passwordReset->findValidToken($token) === null) {
            $this->response->withFlash('error', 'Link resetu jest nieprawidłowy lub wygasł.')
                ->redirect('/forgot-password');
        }

        $this->response->view('pages/auth/reset-password', [
            'title' => 'Ustaw nowe hasło',
            'token' => $token,
        ], 'templates/AuthLayout');
    }

    /** POST /reset-password/{token} */
    public function processReset(): never
    {
        $this->request->verifyCsrf();
        $token    = (string)$this->request->getRouteParam('token');
        $password = (string)$this->request->getParam('password', '');
        $confirm  = (string)$this->request->getParam('password_confirm', '');

        if ($password !== $confirm) {
            $this->response->withFlash('error', 'Hasła nie są identyczne.')
                ->redirect('/reset-password/' . $token);
        }

        if ($this->passwordReset === null) {
            $this->response->withFlash('error', 'Reset hasła chwilowo niedostępny.')->redirect('/login');
        }

        try {
            $this->passwordReset->complete($token, $password);
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())
                ->redirect('/reset-password/' . $token);
        } catch (\Throwable $e) {
            error_log('Password reset complete failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Wystąpił błąd. Spróbuj ponownie.')
                ->redirect('/forgot-password');
        }

        $this->response
            ->withFlash('success', 'Hasło zostało zmienione. Możesz się teraz zalogować.')
            ->redirect('/login');
    }

    private function hasPendingInvitations(string $email): bool
    {
        if ($this->invRepo === null) {
            return false;
        }
        return count($this->invRepo->findAllActiveByEmail($email)) > 0;
    }
}
