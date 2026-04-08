<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\RateLimiter;
use App\Repositories\UserRepository;
use App\Services\AccountDeletionService;
use App\Services\DataExportService;

class SettingsController
{
    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly UserRepository         $userRepo,
        private readonly AccountDeletionService $accountDeletion,
        private readonly DataExportService      $dataExport,
        private readonly RateLimiter            $rateLimiter,
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

        // RODO Art. 17 — pełna anonimizacja PII + cascade delete sole-owned trees
        try {
            $this->accountDeletion->deleteAccount($userId);
        } catch (\RuntimeException $e) {
            error_log('Account deletion failed for user ' . $userId . ': ' . $e->getMessage());
            $this->response->withFlash('error', 'Wystąpił błąd podczas usuwania konta. Skontaktuj się z administratorem.')
                ->redirect('/settings?tab=danger');
        }

        Session::destroy();

        $this->response->withFlash('success', 'Twoje konto zostało usunięte.')->redirect('/login');
    }

    /** POST /settings/export-data — RODO Art. 20 */
    public function exportData(): never
    {
        // ZAD-2.1: state-changing/cost-bearing operacja → CSRF mandatory + POST only
        $this->request->verifyCsrf();

        $userId = (string)Session::get('user_id');

        // ZAD-2.2 (F-03): rate limit per user_id, NIE per IP — IP może się zmieniać
        // (mobile, NAT, VPN), a użytkownik nadal jeden. Anti-abuse jest per-account.
        $rateLimitKey = 'data_export_' . $userId;
        if ($this->rateLimiter->isLimited($userId, $rateLimitKey, 1, 86400)) {
            $this->response->withFlash('error', 'Eksport danych można wykonać raz na 24h. Spróbuj później.')
                ->redirect('/settings');
        }
        $this->rateLimiter->record($userId, $rateLimitKey);

        try {
            $zipPath = $this->dataExport->generateExport($userId);
        } catch (\Throwable $e) {
            error_log('Data export failed for user ' . $userId . ': ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie udało się wygenerować eksportu. Skontaktuj się z administratorem.')
                ->redirect('/settings');
        }

        $filename = 'genealog-data-export-' . date('Y-m-d') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    /** POST /settings/restrict — RODO Art. 18 (ZAD-3.2 P11) */
    public function restrictAccount(): never
    {
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');

        $password = (string)$this->request->getParam('password', '');
        if ($password === '') {
            $this->response->withFlash('error', 'Wymagane podanie hasła.')
                ->redirect('/settings?tab=danger');
        }

        $row = $this->userRepo->findByIdWithHash($userId);
        if ($row === null || !password_verify($password, $row['password_hash'])) {
            $this->response->withFlash('error', 'Hasło jest nieprawidłowe.')
                ->redirect('/settings?tab=danger');
        }

        $this->userRepo->setRestricted($userId, true);
        Session::destroy();

        $this->response->withFlash(
            'success',
            'Twoje konto zostało zawieszone. Możesz je przywrócić logując się ponownie.'
        )->redirect('/login');
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
