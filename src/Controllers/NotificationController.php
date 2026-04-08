<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\NotificationRepository;

class NotificationController
{
    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly NotificationRepository $repo,
    ) {}

    /** GET /api/notifications/count — JSON polling endpoint. Cache 25s żeby polling co 30s nie dobił DB (audit P1). */
    public function count(): never
    {
        $userId = (string)Session::get('user_id');
        header('Cache-Control: private, max-age=25');
        $this->response->json([
            'unread' => $this->repo->countUnread($userId),
        ]);
    }

    /** GET /api/notifications — lista ostatnich 10 */
    public function list(): never
    {
        $userId = (string)Session::get('user_id');
        $items  = $this->repo->findRecent($userId, 10);
        $this->response->json([
            'data'   => $items,
            'unread' => $this->repo->countUnread($userId),
        ]);
    }

    /** POST /api/notifications/{id}/read */
    public function markRead(): never
    {
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');
        $id     = (string)$this->request->getRouteParam('id');
        $this->repo->markAsRead($id, $userId);
        // Csrf::verify rotuje token — zwracamy nowy żeby JS mógł zaktualizować meta tag
        $this->response->json(['ok' => true, 'csrf' => Csrf::getToken()]);
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(): never
    {
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');
        $this->repo->markAllAsRead($userId);
        $this->response->json(['ok' => true, 'csrf' => Csrf::getToken()]);
    }
}
