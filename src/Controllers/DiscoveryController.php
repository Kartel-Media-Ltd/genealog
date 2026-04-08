<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\EventDispatcher;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DiscoveryRepository;
use App\Repositories\TreeRepository;
use App\Services\Discovery\DTO\SearchContext;
use App\Services\Discovery\DTO\SearchCriteria;
use App\Services\Discovery\GlobalIndexService;
use App\Services\Discovery\MatchingService;
use App\Services\Discovery\PersonImportService;

/**
 * Endpointy API dla Person Discovery:
 *   GET  /api/discovery/search           — autosuggest (3 sekcje)
 *   GET  /api/discovery/match/{id}       — szczegóły propozycji
 *   POST /api/discovery/match/{id}/import — import do drzewa  (Faza 6)
 *   POST /api/discovery/match/{id}/reject — odrzuć propozycję (Faza 6)
 *
 * Wszystkie endpointy match/{id} weryfikują WHERE created_for_user = currentUserId
 * (IDOR prevention — K2 z audytu).
 */
final class DiscoveryController
{
    public function __construct(
        private readonly Request             $request,
        private readonly Response            $response,
        private readonly TreeRepository      $treeRepo,
        private readonly MatchingService     $matchingService,
        private readonly DiscoveryRepository $repo,
        private readonly GlobalIndexService  $globalIndex,
        private readonly Database            $db,
        private readonly PersonImportService $importService,
        private readonly RateLimiter         $rateLimiter,
        private readonly ?\Redis             $redis = null,
    ) {}

    /**
     * GET /api/discovery/search
     *
     * Query params: treeId, firstName, lastName, birthYear?, birthPlace?
     */
    public function search(): never
    {
        $userId = (string)Session::get('user_id');
        // I8: guard clause dla wygasłej sesji — AuthMiddleware powinien to złapać,
        // ale defense in depth: nie pozwólmy pustemu user_id dojść do SQL.
        if ($userId === '') {
            $this->response->json(['error' => 'unauthorized'], 401);
        }

        $treeId = trim((string)$this->request->getParam('treeId', ''));
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limit — max 30 req/min per IP (audyt P1, enumeration attack protection)
        // Redis: atomowe INCR+EXPIRE (szybsze niż DB, brak race condition)
        // Fallback: DB-based RateLimiter gdy Redis niezdefiniowany w .env.local
        if ($this->redis !== null) {
            $rlKey = 'genealog:rl:discovery:' . $ip;
            $count = $this->redis->incr($rlKey);
            if ($count === 1) {
                $this->redis->expire($rlKey, 60); // okno 60s
            }
            if ($count > 30) {
                header('Retry-After: 60');
                $this->response->json(['error' => 'too_many_requests'], 429);
            }
        } else {
            if ($this->rateLimiter->isLimited($ip, 'discovery_search', 30, 60)) {
                header('Retry-After: 60');
                $this->response->json(['error' => 'too_many_requests'], 429);
            }
            $this->rateLimiter->record($ip, 'discovery_search');
        }

        // IDOR: weryfikacja dostępu editor/owner do drzewa
        $role = $this->treeRepo->getUserRole($treeId, $userId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            $this->response->json(['error' => 'forbidden'], 403);
        }

        $criteria = SearchCriteria::fromArray([
            'firstName'  => $this->request->getParam('firstName', ''),
            'lastName'   => $this->request->getParam('lastName', ''),
            'birthYear'  => $this->request->getParam('birthYear'),
            'birthPlace' => $this->request->getParam('birthPlace', ''),
            'deathPlace' => $this->request->getParam('deathPlace', ''),
            'gender'     => $this->request->getParam('gender', ''),
        ]);

        if (!$criteria->isSearchable()) {
            $this->response->json([
                'local'     => [],
                'crossTree' => [],
                'external'  => [],
            ]);
        }

        $accessibleTreeIds = $this->treeRepo->findAccessibleIdsForUser($userId);

        $context = new SearchContext(
            currentUserId:     $userId,
            currentTreeId:     $treeId,
            accessibleTreeIds: $accessibleTreeIds,
            includeLocal:     true,
            includeCrossTree: true,
            includeExternal:  false, // Faza 7
        );

        $candidates = $this->matchingService->findCandidates($criteria, $context);

        // Suggestion: log AUDIT dla każdego cross_tree_query (nawet 0 wyników).
        // RODO Art. 30 — pełna audytalność użycia endpointu, nie tylko sukcesów.
        // Wcześniej logowano tylko gdy były wyniki — przez co audyt był niekompletny.
        $this->repo->logAudit(
            userId:         $userId,
            action:         'cross_tree_query',
            sourceType:     'cross_tree',
            sourceId:       null,
            targetPersonId: null,
            targetTreeId:   $treeId,
            ip:             $_SERVER['REMOTE_ADDR'] ?? null,
        );

        $this->response->json($candidates);
    }

    /**
     * POST /api/discovery/match/{id}/import
     * Importuje propozycję do drzewa. IDOR: WHERE created_for_user = currentUserId.
     *
     * B2: response zawiera nowy CSRF token (rotacja per-request) — frontend
     * aktualizuje meta[name=csrf-token] żeby drugi click nie dawał 403.
     * B3: blokujemy `source_type='local'` — local match powinien tworzyć
     * relację, nie duplikat osoby (żeby nie zaśmiecać drzewa).
     */
    public function importMatch(): never
    {
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');
        $id     = (string)$this->request->getRouteParam('id');

        $suggestion = $this->repo->findById($id, $userId);
        if ($suggestion === null) {
            $this->response->json(['error' => 'not_found'], 404);
        }

        // B3: local match nie tworzy duplikatu — zwracamy 422 z czytelnym komunikatem
        if (($suggestion['source_type'] ?? '') === 'local') {
            $this->response->json([
                'error'   => 'invalid_source',
                'message' => 'Dopasowanie z Twojego drzewa nie może być importowane jako nowa osoba — '
                           . 'użyj funkcji "Dodaj relację" żeby połączyć istniejące osoby.',
            ], 422);
        }

        // Weryfikacja edytora dla treeId źródłowej osoby
        $person = $this->db->fetchOne(
            'SELECT tree_id FROM persons WHERE id = ?',
            [(string)$suggestion['person_id']]
        );
        if ($person === null) {
            $this->response->json(['error' => 'not_found'], 404);
        }
        $treeId = (string)$person['tree_id'];

        $role = $this->treeRepo->getUserRole($treeId, $userId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            $this->response->json(['error' => 'forbidden'], 403);
        }

        try {
            $newPersonId = $this->importService->importFromMatch(
                suggestion: $suggestion,
                treeId:     $treeId,
                userId:     $userId,
                ip:         $_SERVER['REMOTE_ADDR'] ?? null,
            );
        } catch (\RuntimeException $e) {
            $this->response->json(['error' => 'conflict', 'message' => $e->getMessage(), 'csrf' => Csrf::getToken()], 409);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['error' => 'invalid', 'message' => $e->getMessage(), 'csrf' => Csrf::getToken()], 400);
        }

        // B2: zwracamy nowy CSRF token — frontend aktualizuje meta tag
        $this->response->json([
            'ok'       => true,
            'personId' => $newPersonId,
            'csrf'     => Csrf::getToken(),
        ]);
    }

    /**
     * POST /api/discovery/match/{id}/reject
     *
     * B2: response zawiera nowy CSRF token (rotacja per-request).
     * Important #2: zrezygnowano z dodatkowego SELECT na rolę (findById już
     * filtruje przez created_for_user — IDOR safe).
     */
    public function rejectMatch(): never
    {
        $this->request->verifyCsrf();
        $userId = (string)Session::get('user_id');
        $id     = (string)$this->request->getRouteParam('id');

        $suggestion = $this->repo->findById($id, $userId);
        if ($suggestion === null) {
            $this->response->json(['error' => 'not_found'], 404);
        }

        $this->repo->updateStatusIfPending($id, 'rejected');

        $this->repo->logAudit(
            userId:         $userId,
            action:         'reject',
            sourceType:     (string)$suggestion['source_type'],
            sourceId:       (string)$suggestion['source_id'],
            targetPersonId: (string)$suggestion['person_id'],
            targetTreeId:   null,
            ip:             $_SERVER['REMOTE_ADDR'] ?? null,
        );

        // B2: nowy CSRF token dla frontendu
        $this->response->json(['ok' => true, 'csrf' => Csrf::getToken()]);
    }

    /**
     * GET /trees/{id}/settings/discovery
     * Panel ustawień opt-in/out globalnego indeksu dla drzewa. Tylko owner.
     */
    public function showSettings(): never
    {
        $userId = (string)Session::get('user_id');
        $treeId = (string)$this->request->getRouteParam('id');

        $tree = $this->treeRepo->findById($treeId);
        if ($tree === null || $tree->ownerId !== $userId) {
            $this->response->withFlash('error', 'Brak uprawnień.');
            $this->response->redirect('/trees');
        }

        $owner = $this->db->fetchOne(
            'SELECT discovery_opt_in FROM users WHERE id = ?',
            [$userId]
        );

        $this->response->view('pages/trees/settings/discovery', [
            'title'           => 'Ustawienia odkrywania',
            'currentUser'     => $this->currentUser(),
            'tree'            => $tree,
            'ownerOptIn'      => (bool)($owner['discovery_opt_in'] ?? false),
        ]);
    }

    /**
     * POST /trees/{id}/settings/discovery
     */
    public function updateSettings(): never
    {
        $this->request->verifyCsrf();

        $userId = (string)Session::get('user_id');
        $treeId = (string)$this->request->getRouteParam('id');

        $tree = $this->treeRepo->findById($treeId);
        if ($tree === null || $tree->ownerId !== $userId) {
            $this->response->withFlash('error', 'Brak uprawnień.');
            $this->response->redirect('/trees');
        }

        $treeOptIn  = $this->request->getParam('tree_indexed') === '1';
        $userOptIn  = $this->request->getParam('user_opt_in')  === '1';

        // Poprzedni stan — do porównania przy decyzji czy reindex / unindex
        $previousTree = $tree->isIndexedGlobally;
        $previousUser = (bool)(
            $this->db->fetchOne('SELECT discovery_opt_in FROM users WHERE id = ?', [$userId])['discovery_opt_in'] ?? 0
        );

        $this->db->execute(
            'UPDATE users SET discovery_opt_in = ? WHERE id = ?',
            [$userOptIn ? 1 : 0, $userId]
        );

        $this->db->execute(
            'UPDATE trees
             SET is_indexed_globally = ?, discovery_consent_at = CASE WHEN ? THEN NOW() ELSE discovery_consent_at END
             WHERE id = ? AND owner_id = ?',
            [$treeOptIn ? 1 : 0, $treeOptIn ? 1 : 0, $treeId, $userId]
        );

        // Nowy vs stary stan eligibility (oba flagi muszą być 1 żeby drzewo było w indeksie)
        $wasEligible = $previousTree && $previousUser;
        $nowEligible = $treeOptIn && $userOptIn;

        if (!$wasEligible && $nowEligible) {
            // Włączone od zera → reindex
            // Redis: wrzuć job do kolejki (async) — worker: php bin/reindex-worker.php
            // Fallback: synchronicznie (blokujące, OK dla drzew < ~1000 osób)
            if ($this->redis !== null) {
                $this->redis->lPush('genealog:reindex_queue', (string)json_encode([
                    'treeId'    => $treeId,
                    'queued_at' => time(),
                ]));
                $reindexFlash = 'Reindeksowanie zaplanowane w tle.';
            } else {
                $this->globalIndex->reindexTree($treeId);
                $reindexFlash = null;
            }
        } elseif ($wasEligible && !$nowEligible) {
            // Wyłączone cokolwiek (tree OR user opt-in) → unindex
            // RODO Art. 7(3) — right to withdraw consent (I7)
            EventDispatcher::emit('tree.indexed_globally.disabled', $treeId);
        }
        // Jeśli oba stany były eligible i pozostały eligible — nic nie robimy (no-op reindex niepotrzebny).

        $flashMsg = 'Ustawienia zapisane.';
        if (!empty($reindexFlash)) {
            $flashMsg .= ' ' . $reindexFlash;
        }
        $this->response->withFlash('success', $flashMsg);
        $this->response->redirect('/trees/' . $treeId . '/settings/discovery');
    }

    private function currentUser(): array
    {
        return [
            'name'  => Session::get('user_name', 'Użytkownik'),
            'email' => Session::get('user_email', ''),
        ];
    }
}
