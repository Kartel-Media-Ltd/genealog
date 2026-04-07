<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

class SettingsController
{
    public function __construct(
        private readonly Request        $request,
        private readonly Response       $response,
        private readonly UserRepository $userRepo,
    ) {}

    /** GET /settings */
    public function show(): never
    {
        $userId = Session::get('user_id');
        $user   = $this->userRepo->findById($userId);

        $this->response->view('pages/settings', [
            'title'       => 'Ustawienia',
            'currentUser' => $this->currentUser(),
            'user'        => $user,
        ]);
    }

    /** POST /settings/notifications */
    public function updateNotifications(): never
    {
        $this->request->verifyCsrf();
        $userId = Session::get('user_id');

        $enabled = $this->request->getParam('email_notifications') === '1';
        $this->userRepo->updateEmailNotifications($userId, $enabled);

        $this->response->withFlash('success', 'Ustawienia powiadomień zostały zapisane.')
            ->redirect('/settings');
    }

    /** POST /settings/locale */
    public function updateLocale(): never
    {
        $this->request->verifyCsrf();
        $userId = Session::get('user_id');

        $locale = (string)$this->request->getParam('locale', 'pl');
        $allowed = ['pl', 'en', 'de', 'uk'];
        if (!in_array($locale, $allowed, true)) {
            $locale = 'pl';
        }

        $this->userRepo->updateLocale($userId, $locale);
        $this->response->withFlash('success', 'Język interfejsu został zmieniony.')
            ->redirect('/settings');
    }

    /** POST /settings/delete */
    public function deleteAccount(): never
    {
        $this->request->verifyCsrf();
        $userId = Session::get('user_id');

        $password    = (string)$this->request->getParam('password', '');
        $confirmation = (string)$this->request->getParam('confirmation', '');

        if ($confirmation !== 'USUŃ KONTO') {
            $this->response->withFlash('error', 'Wpisz dokładnie "USUŃ KONTO" aby potwierdzić.')
                ->redirect('/settings?tab=danger');
        }

        $row = $this->userRepo->findByIdWithHash($userId);
        if ($row === null || !password_verify($password, $row['password_hash'])) {
            $this->response->withFlash('error', 'Hasło jest nieprawidłowe.')
                ->redirect('/settings?tab=danger');
        }

        $this->userRepo->deactivate($userId);
        Session::destroy();

        $this->response->withFlash('success', 'Twoje konto zostało usunięte.')->redirect('/login');
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
