<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

class ProfileController
{
    public function __construct(
        private readonly Request        $request,
        private readonly Response       $response,
        private readonly UserRepository $userRepo,
    ) {}

    /** GET /profile */
    public function show(): never
    {
        $userId = Session::get('user_id');
        $user   = $this->userRepo->findById($userId);

        $this->response->view('pages/profile', [
            'title'       => 'Mój profil',
            'currentUser' => $this->currentUser(),
            'user'        => $user,
        ]);
    }

    /** POST /profile */
    public function updateProfile(): never
    {
        $this->request->verifyCsrf();
        $userId = Session::get('user_id');

        $name = trim((string)$this->request->getParam('name', ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $this->response->withFlash('error', 'Imię i nazwisko musi mieć od 2 do 100 znaków.')
                ->redirect('/profile');
        }

        $this->userRepo->updateName($userId, $name);
        Session::set('user_name', $name);

        $this->response->withFlash('success', 'Dane profilu zostały zaktualizowane.')
            ->redirect('/profile');
    }

    /** POST /profile/password */
    public function changePassword(): never
    {
        $this->request->verifyCsrf();
        $userId = Session::get('user_id');

        $currentPassword = (string)$this->request->getParam('current_password', '');
        $newPassword     = (string)$this->request->getParam('new_password', '');
        $confirmPassword = (string)$this->request->getParam('confirm_password', '');

        if ($newPassword !== $confirmPassword) {
            $this->response->withFlash('error', 'Nowe hasła nie są identyczne.')
                ->redirect('/profile?tab=password');
        }

        if (mb_strlen($newPassword) < 8) {
            $this->response->withFlash('error', 'Hasło musi mieć co najmniej 8 znaków.')
                ->redirect('/profile?tab=password');
        }

        $row = $this->userRepo->findByIdWithHash($userId);
        if ($row === null || !password_verify($currentPassword, $row['password_hash'])) {
            $this->response->withFlash('error', 'Aktualne hasło jest nieprawidłowe.')
                ->redirect('/profile?tab=password');
        }

        $this->userRepo->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]));
        $this->response->withFlash('success', 'Hasło zostało zmienione.')->redirect('/profile');
    }

    /** POST /profile/email */
    public function changeEmail(): never
    {
        $this->request->verifyCsrf();
        $userId = Session::get('user_id');

        $newEmail = trim(strtolower((string)$this->request->getParam('email', '')));
        $password = (string)$this->request->getParam('password', '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->response->withFlash('error', 'Podany adres email jest nieprawidłowy.')
                ->redirect('/profile?tab=email');
        }

        $row = $this->userRepo->findByIdWithHash($userId);
        if ($row === null || !password_verify($password, $row['password_hash'])) {
            $this->response->withFlash('error', 'Hasło jest nieprawidłowe.')
                ->redirect('/profile?tab=email');
        }

        if (strtolower($row['email']) !== $newEmail && $this->userRepo->emailExists($newEmail)) {
            $this->response->withFlash('error', 'Ten adres email jest już zajęty.')
                ->redirect('/profile?tab=email');
        }

        $this->userRepo->updateEmail($userId, $newEmail);
        Session::set('user_email', $newEmail);

        $this->response->withFlash('success', 'Adres email został zaktualizowany.')->redirect('/profile');
    }

    private function currentUser(): array
    {
        return [
            'name'   => Session::get('user_name', 'Użytkownik'),
            'email'  => Session::get('user_email', ''),
            'avatar' => '',
        ];
    }
}
