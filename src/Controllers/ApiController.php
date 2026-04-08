<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;
use App\Repositories\TreeRepository;

class ApiController
{
    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly TreeRepository         $treeRepo,
        private readonly PersonRepository       $personRepo,
        private readonly RelationshipRepository $relRepo,
    ) {}

    /**
     * GET /api/trees/{id}/persons
     * Returns JSON for D3.js visualization: { nodes, links }
     */
    public function personsForTree(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        $role = $this->treeRepo->getUserRole($treeId, $userId);
        if (!in_array($role, ['owner', 'editor', 'viewer'], true)) {
            $this->jsonError(403, 'Brak dostępu.');
        }

        $persons       = $this->personRepo->findByTree($treeId);
        $relationships = $this->relRepo->findByTree($treeId);

        // Build parentId map. Convention used everywhere in this app:
        //   ('child', A, B)  = "A has B as child"  → A is the parent, B is the child
        //   ('parent', A, B) = "A has B as parent" → B is the parent, A is the child
        // (matches the form labels "Rodzic/Dziecko (tej osoby)" and SuggestionService.)
        // RelationshipService stores both forward + inverse rows for every pair, so we
        // collect into a set keyed by parentId to dedupe automatically.
        $parentSet = []; // childId => [parentId => true, ...]
        foreach ($relationships as $rel) {
            if ($rel->type === 'child') {
                $parentSet[$rel->personBId][$rel->personAId] = true;
            } elseif ($rel->type === 'parent') {
                $parentSet[$rel->personAId][$rel->personBId] = true;
            }
        }
        $allParents = [];
        foreach ($parentSet as $childId => $parents) {
            $allParents[$childId] = array_keys($parents);
        }
        $parentMap        = [];
        $extraParentLinks = [];
        foreach ($allParents as $childId => $parentIds) {
            $parentMap[$childId] = $parentIds[0];
            for ($i = 1; $i < count($parentIds); $i++) {
                $extraParentLinks[] = [
                    'source' => $parentIds[$i],
                    'target' => $childId,
                    'type'   => 'parent',
                ];
            }
        }

        $nodes = [];
        foreach ($persons as $p) {
            $photoUrl = null;
            if ($p->photoPath !== null) {
                $photoUrl = '/media.php?path=' . urlencode($p->photoPath);
            }

            $nodes[] = [
                'id'       => $p->id,
                'parentId' => $parentMap[$p->id] ?? null,
                'data'     => [
                    'firstName'  => $p->firstName,
                    'lastName'   => $p->lastName,
                    'birthYear'  => $p->birthYear(),
                    'gender'     => $p->gender,
                    'isLiving'   => $p->isLiving,
                    'photoUrl'   => $photoUrl,
                    'profileUrl' => '/trees/' . $treeId . '/persons/' . $p->id,
                ],
            ];
        }

        // Non-tree links: spouse/sibling/partner + extra parents (2nd+ parent per child).
        // RelationshipService stores both forward + inverse for symmetric relations,
        // so we dedupe by canonical pair to avoid drawing two D3 lines per couple/sibling.
        $links = $extraParentLinks;
        $seenSymmetric = []; // "type|minId|maxId" => true
        foreach ($relationships as $rel) {
            if (!in_array($rel->type, ['spouse', 'sibling', 'partner'], true)) {
                continue;
            }
            $a   = $rel->personAId;
            $b   = $rel->personBId;
            $key = $rel->type . '|' . ($a < $b ? "$a|$b" : "$b|$a");
            if (isset($seenSymmetric[$key])) {
                continue;
            }
            $seenSymmetric[$key] = true;
            $links[] = [
                'source' => $a,
                'target' => $b,
                'type'   => $rel->type,
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['nodes' => $nodes, 'links' => $links], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * GET /health — ZAD-4.7 (D8) health-check endpoint.
     *
     * Zwraca JSON z aktualnym statusem systemu: DB reachability + timestamp.
     * Używany przez load-balancery, Kubernetes liveness/readiness probes,
     * monitoring (Grafana, UptimeRobot, etc.). Brak auth — public endpoint.
     *
     * Status HTTP:
     *   200 → wszystko OK
     *   503 → DB nieosiągalne
     */
    public function health(): never
    {
        $status = ['status' => 'ok', 'ts' => date('c')];
        $code   = 200;

        try {
            Database::getInstance()->fetchOne('SELECT 1 AS ping');
            $status['db'] = 'ok';
        } catch (\Throwable $e) {
            error_log('Health check DB fail: ' . $e->getMessage());
            $status = ['status' => 'fail', 'db' => 'fail', 'ts' => date('c')];
            $code   = 503;
        }

        http_response_code($code);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, max-age=0');
        echo json_encode($status, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonError(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
