<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

class AdminMiddleware
{
    public function __construct(
        private readonly Response        $response,
        private readonly ?UserRepository $userRepo = null,
    ) {}

    public function handle(Request $request): bool
    {
        // Must be logged in
        if (!Session::has('user_id')) {
            $this->response->withFlash('error', 'Musisz być zalogowany.')->redirect('/login');
        }

        // Must be admin AND not currently impersonating
        if (!Session::get('is_admin') || Session::has('_admin_user_id')) {
            $this->response->withFlash('error', 'Brak uprawnień administratora.')->redirect('/dashboard');
        }

        // session_version check — analogiczne do AuthMiddleware. Chroni przed
        // sytuacją gdy admin został zdegradowany przez innego admina, ale jego
        // sesja `is_admin=1` pozostaje aktywna do wygaśnięcia (audit D6).
        if ($this->userRepo !== null) {
            $userId         = (string)Session::get('user_id');
            $sessionVersion = (int)Session::get('session_version', 0);
            $dbVersion      = $this->userRepo->getSessionVersion($userId);

            if ($dbVersion === null || $dbVersion !== $sessionVersion) {
                Session::destroy();
                Session::flash('error', 'Twoja sesja wygasła. Zaloguj się ponownie.');
                $this->response->redirect('/login');
            }
        }

        return true;
    }
}
