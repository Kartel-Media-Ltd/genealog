<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AdminMiddleware
{
    public function __construct(private readonly Response $response) {}

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

        return true;
    }
}
