<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\CrossTreeLink;
use App\Repositories\CrossTreeLinkRepository;
use App\Repositories\DiscoveryRepository;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;
use App\Services\CrossTreeLinkService;

/**
 * Kontroler powiązań cross-tree.
 *
 * Widoki:
 *   GET  /connections               — panel: przychodzące / wysłane / zaakceptowane
 *
 * API (JSON):
 *   POST /api/connections/request   — wyślij prośbę {requesterPersonId, targetPersonId, visibilityLevel?, note?}
 *   POST /api/connections/{id}/accept
 *   POST /api/connections/{id}/reject
 *   POST /api/connections/{id}/cancel
 *   POST /api/connections/{id}/remove
 *   GET  /api/trees/{id}/merged-persons — dane do merged D3.js view
 */
final class CrossTreeLinkController
{
    public function __construct(
        private readonly Request                  $request,
        private readonly Response                 $response,
        private readonly CrossTreeLinkService     $linkSvc,
        private readonly CrossTreeLinkRepository  $linkRepo,
        private readonly PersonRepository         $personRepo,
        private readonly TreeRepository           $treeRepo,
        private readonly ?DiscoveryRepository     $discoveryRepo = null,
    ) {}

    // -------------------------------------------------------------------------
    // Widok /connections
    // -------------------------------------------------------------------------

    public function index(): never
    {
        $userId = (string)Session::get('user_id');

        $incoming = $this->linkRepo->findPendingForTargetUser($userId);
        $sent     = $this->linkRepo->findSentByUser($userId);
        $accepted = $this->linkRepo->findAcceptedForUser($userId);

        $this->response->view('pages/connections/index', [
            'title'    => 'Powiązania między drzewami',
            'incoming' => $incoming,
            'sent'     => $sent,
            'accepted' => $accepted,
        ]);
    }

    // -------------------------------------------------------------------------
    // API: wyślij prośbę
    // -------------------------------------------------------------------------

    public function request(): never
    {
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');

        $body              = $this->request->getBody();
        $requesterPersonId = trim((string)($body['requesterPersonId'] ?? ''));
        $targetPersonId    = trim((string)($body['targetPersonId'] ?? ''));
        $targetGpiId       = trim((string)($body['targetGpiId'] ?? ''));
        $visibilityLevel   = (string)($body['visibilityLevel'] ?? CrossTreeLink::VISIBILITY_BASIC);
        $note              = trim((string)($body['note'] ?? '')) ?: null;

        // Wspieraj targetGpiId (z live discovery search) jako alternatywę dla targetPersonId
        if ($targetPersonId === '' && $targetGpiId !== '' && $this->discoveryRepo !== null) {
            $resolvedId = $this->discoveryRepo->findPersonIdByGpiId($targetGpiId);
            if ($resolvedId === null) {
                $this->response->json(['error' => 'Nie znaleziono osoby w globalnym indeksie.'], 404);
            }
            $targetPersonId = $resolvedId;
        }

        if ($requesterPersonId === '' || $targetPersonId === '') {
            $this->response->json(['error' => 'Brakuje requesterPersonId lub targetPersonId.'], 400);
        }

        if (!in_array($visibilityLevel, [CrossTreeLink::VISIBILITY_BASIC, CrossTreeLink::VISIBILITY_FULL], true)) {
            $visibilityLevel = CrossTreeLink::VISIBILITY_BASIC;
        }

        $adminOverride = (bool)Session::get('is_admin') || Session::get('_admin_user_id') !== null;

        try {
            $link = $this->linkSvc->createRequest(
                requesterPersonId: $requesterPersonId,
                targetPersonId:    $targetPersonId,
                requesterUserId:   $userId,
                visibilityLevel:   $visibilityLevel,
                note:              $note,
                adminOverride:     $adminOverride,
            );
            $this->response->json(['success' => true, 'linkId' => $link->id]);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['error' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // API: akcje na istniejącym połączeniu
    // -------------------------------------------------------------------------

    public function accept(): never
    {
        $id = (string)$this->request->getRouteParam('id');
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');

        try {
            $this->linkSvc->accept($id, $userId);
            Session::flash('success', 'Połączenie zaakceptowane.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->response->redirect('/connections');
    }

    public function reject(): never
    {
        $id = (string)$this->request->getRouteParam('id');
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');

        try {
            $this->linkSvc->reject($id, $userId);
            Session::flash('success', 'Prośba odrzucona.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->response->redirect('/connections');
    }

    public function cancel(): never
    {
        $id = (string)$this->request->getRouteParam('id');
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');

        try {
            $this->linkSvc->cancel($id, $userId);
            Session::flash('success', 'Prośba anulowana.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->response->redirect('/connections');
    }

    public function remove(): never
    {
        $id = (string)$this->request->getRouteParam('id');
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');

        try {
            $this->linkSvc->remove($id, $userId);
            Session::flash('success', 'Połączenie usunięte.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->response->redirect('/connections');
    }

    // -------------------------------------------------------------------------
    // API: dane do merged D3.js view
    // -------------------------------------------------------------------------

    /**
     * GET /api/trees/{id}/merged-persons
     *
     * Zwraca JSON z lokalnymi i zdalnymi osobami oraz cross-tree links dla danego drzewa.
     * Używane przez merged D3.js view w widoku drzewa.
     */
    public function mergedPersons(): never
    {
        $treeId = (string)$this->request->getRouteParam('id');
        $userId = (string)Session::get('user_id');

        // Weryfikuj dostęp do drzewa
        $tree = $this->treeRepo->findForUser($treeId, $userId);
        if ($tree === null) {
            $this->response->json(['error' => 'Brak dostępu do drzewa.'], 403);
        }

        try {
            $data = $this->linkSvc->getMergedViewData($treeId, $userId);
            $this->response->json($data);
        } catch (\Throwable $e) {
            error_log('[mergedPersons] ' . $e->getMessage());
            $this->response->json(['error' => 'Błąd wewnętrzny serwera.'], 500);
        }
    }
}
