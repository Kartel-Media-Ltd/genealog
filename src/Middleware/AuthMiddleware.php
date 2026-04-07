<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

class AuthMiddleware
{
    public function __construct(
        private readonly Response       $response,
        private readonly ?UserRepository $userRepo = null,
    ) {}

    public function handle(Request $request): bool
    {
        if (!Session::has('user_id')) {
            Session::flash('error', 'Zaloguj się, aby kontynuować.');
            $this->response->redirect('/login');
        }

        // Session invalidation: jeśli session_version w sesji != session_version w DB,
        // to znaczy że konto zostało zdegradowane/zablokowane przez innego admina.
        // Niszczymy sesję i wymuszamy ponowne logowanie.
        if ($this->userRepo !== null) {
            $userId          = (string)Session::get('user_id');
            $sessionVersion  = (int)Session::get('session_version', 0);
            $dbVersion       = $this->userRepo->getSessionVersion($userId);

            if ($dbVersion === null || $dbVersion !== $sessionVersion) {
                Session::destroy();
                Session::flash('error', 'Twoja sesja wygasła. Zaloguj się ponownie.');
                $this->response->redirect('/login');
            }
        }

        return true;
    }
}
