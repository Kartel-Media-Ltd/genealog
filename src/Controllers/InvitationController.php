<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\InvitationRepository;
use App\Repositories\TreeRepository;
use App\Services\InvitationService;

class InvitationController
{
    public function __construct(
        private readonly Request               $request,
        private readonly Response              $response,
        private readonly TreeRepository        $treeRepo,
        private readonly InvitationRepository  $invRepo,
        private readonly InvitationService     $invService,
        private readonly RateLimiter           $rateLimiter,
    ) {}

    /** GET /trees/{id}/members */
    public function members(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $this->requireOwner($treeId);

        $tree               = $this->treeRepo->findById($treeId);
        $members            = $this->invRepo->getMembers($treeId);
        $pendingInvitations = $this->invRepo->findActiveByTree($treeId);

        $this->response->view('pages/trees/members', [
            'title'              => 'Zarządzaj dostępem — ' . ($tree?->name ?? ''),
            'currentUser'        => $this->currentUser(),
            'tree'               => $tree,
            'members'            => $members,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    /** POST /trees/{id}/invite */
    public function invite(): never
    {
        $this->request->verifyCsrf();

        $treeId  = $this->request->getRouteParam('id');
        $userId  = Session::get('user_id');
        $this->requireOwner($treeId);

        $email = trim((string)$this->request->getParam('email', ''));
        $role  = trim((string)$this->request->getParam('role', ''));

        try {
            $this->invService->invite($treeId, $email, $role, $userId);
            $this->response
                ->withFlash('success', 'Zaproszenie zostało wysłane.')
                ->redirect('/trees/' . $treeId . '/members');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->response
                ->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/members');
        }
    }

    /** GET /invitations — lista oczekujących zaproszeń dla zalogowanego użytkownika */
    public function pendingList(): never
    {
        $email       = Session::get('user_email', '');
        $invitations = $this->invRepo->findAllActiveByEmail($email);

        $this->response->view('pages/invite/pending', [
            'title'       => 'Oczekujące zaproszenia',
            'currentUser' => $this->currentUser(),
            'invitations' => $invitations,
        ]);
    }

    /** GET /invite/{token} */
    public function showAccept(): never
    {
        $token = $this->request->getRouteParam('token');
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limit — chroni przed brute-force enumeracji tokenów (audyt R3/STRIDE Spoofing)
        if ($this->rateLimiter->isLimited($ip, 'invite_token', 20, 3600)) {
            $this->response->withFlash('error', 'Zbyt wiele prób. Spróbuj ponownie za godzinę.')
                ->redirect('/login');
        }
        $this->rateLimiter->record($ip, 'invite_token');

        $inv = $this->invService->findValidByToken($token);
        if ($inv === null) {
            $this->response
                ->withFlash('error', 'Link zaproszenia wygasł lub jest nieprawidłowy.')
                ->redirect('/login');
        }

        $tree        = $this->treeRepo->findById($inv['tree_id']);
        $isLoggedIn  = Session::has('user_id');

        // Save token in session for post-login redirect
        // ZAD-4.2 (D2): walidacja formatu tokenu przed zapisem — defense-in-depth,
        // zapobiega zanieczyszczeniu sesji wartościami spoza oczekiwanego formatu.
        if (!$isLoggedIn && $this->isValidTokenFormat($token)) {
            Session::set('pending_invitation', $token);
        }

        $this->response->view('pages/invite/accept', [
            'title'       => 'Zaproszenie do drzewa',
            'currentUser' => $this->currentUser(),
            'invitation'  => $inv,
            'treeName'    => $tree?->name ?? 'drzewo genealogiczne',
            'token'       => $token,
            'isLoggedIn'  => $isLoggedIn,
        ]);
    }

    /** POST /invite/{token}/accept */
    public function processAccept(): never
    {
        $this->request->verifyCsrf();

        $token  = $this->request->getRouteParam('token');
        $userId = Session::get('user_id');

        if (!$userId) {
            if ($this->isValidTokenFormat($token)) {
                Session::set('pending_invitation', $token);
            }
            $this->response->redirect('/login');
        }

        try {
            $treeId = $this->invService->accept($token, $userId);
            $this->response
                ->withFlash('success', 'Dołączyłeś do drzewa. Witaj!')
                ->redirect('/trees/' . $treeId);
        } catch (\RuntimeException $e) {
            $this->response
                ->withFlash('error', $e->getMessage())
                ->redirect('/login');
        }
    }

    /** POST /trees/{id}/members/{uid}/remove */
    public function removeMember(): never
    {
        $this->request->verifyCsrf();

        $treeId       = $this->request->getRouteParam('id');
        $targetUserId = $this->request->getRouteParam('uid');
        $requesterId  = Session::get('user_id');

        $this->requireOwner($treeId);

        try {
            $this->invService->removeMember($treeId, $targetUserId, $requesterId);
            $this->response
                ->withFlash('success', 'Członek został usunięty.')
                ->redirect('/trees/' . $treeId . '/members');
        } catch (\RuntimeException $e) {
            $this->response
                ->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/members');
        }
    }

    /** POST /trees/{id}/members/{uid}/role */
    public function changeRole(): never
    {
        $this->request->verifyCsrf();

        $treeId       = $this->request->getRouteParam('id');
        $targetUserId = $this->request->getRouteParam('uid');
        $requesterId  = Session::get('user_id');
        $newRole      = trim((string)$this->request->getParam('role', ''));

        $this->requireOwner($treeId);

        try {
            $this->invService->changeRole($treeId, $targetUserId, $newRole, $requesterId);
            $this->response
                ->withFlash('success', 'Rola została zmieniona.')
                ->redirect('/trees/' . $treeId . '/members');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $this->response
                ->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/members');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function requireOwner(string $treeId): void
    {
        $userId = Session::get('user_id');
        if (!$this->treeRepo->isOwner($treeId, $userId)) {
            $this->response->withFlash('error', 'Brak uprawnień.')->redirect('/trees');
        }
    }

    private function currentUser(): array
    {
        return [
            'name'   => Session::get('user_name', 'Użytkownik'),
            'email'  => Session::get('user_email', ''),
            'avatar' => '',
        ];
    }

    /**
     * ZAD-4.2 (D2): walidacja formatu tokena zaproszenia (64 hex chars).
     * Token jest generowany przez bin2hex(random_bytes(32)) — zawsze 64 hex chars.
     * Defense-in-depth: chroni przed zapisem do sesji wartości craftowanych przez usera.
     */
    private function isValidTokenFormat(?string $token): bool
    {
        return $token !== null && strlen($token) === 64 && ctype_xdigit($token);
    }
}
