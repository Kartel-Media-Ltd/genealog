<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TreeRepository;

class HomeController
{
    public function __construct(
        private readonly Request         $request,
        private readonly Response        $response,
        private readonly TreeRepository  $treeRepo,
    ) {}

    public function index(): never
    {
        $userId = Session::get('user_id');
        $trees  = [];

        // TreeRepository::findByOwner — fail-safe (np. brak tabeli przed migracją)
        // ZAD-2.8: nie połykaj cicho — log do error_log dla observability
        try {
            $trees = $this->treeRepo->findByOwner($userId);
        } catch (\Exception $e) {
            error_log('HomeController::index findByOwner failed: ' . $e->getMessage());
            $trees = [];
        }

        $totalPersons = array_sum(array_map(fn($t) => $t->personsCount, $trees));

        $recentActivity = [];
        try {
            $recentActivity = $this->treeRepo->getRecentPersonActivity($userId);
        } catch (\Exception $e) {
            error_log('HomeController::index getRecentPersonActivity failed: ' . $e->getMessage());
            $recentActivity = [];
        }

        $this->response->view('pages/dashboard', [
            'title'       => 'Dashboard',
            'currentUser' => [
                'name'   => Session::get('user_name', 'Użytkownik'),
                'email'  => Session::get('user_email', ''),
                'avatar' => '',
            ],
            'trees'       => $trees,
            'recentTrees' => $recentActivity,
            'stats'       => [
                'total_trees'   => count($trees),
                'total_persons' => $totalPersons,
                'total_events'  => 0,
            ],
        ]);
    }
}
