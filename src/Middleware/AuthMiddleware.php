<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthMiddleware
{
    public function __construct(private readonly Response $response) {}

    public function handle(Request $request): bool
    {
        if (!Session::has('user_id')) {
            Session::flash('error', 'Zaloguj się, aby kontynuować.');
            $this->response->redirect('/login');
        }
        return true;
    }
}
