<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;
use App\Repositories\TreeRepository;
use App\Services\RelationshipService;

class SuggestionController
{
    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly TreeRepository         $treeRepo,
        private readonly PersonRepository       $personRepo,
        private readonly RelationshipRepository $relRepo,
        private readonly RelationshipService    $relService,
    ) {}

    /**
     * POST /trees/{id}/persons/{pid}/suggestions
     * Bulk-accept selected relationship suggestions.
     */
    public function apply(): never
    {
        $this->request->verifyCsrf();

        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireEditorAccess($treeId, $userId);

        // Verify person exists in this tree (IDOR prevention)
        $person = $this->personRepo->findById($personId, $treeId);
        if ($person === null) {
            $this->response->withFlash('error', 'Osoba nie istnieje.')
                ->redirect('/trees/' . $treeId . '/persons');
        }

        $raw = $this->request->getParam('suggestions', []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $added = 0;
        foreach ($raw as $item) {
            $decoded = json_decode((string)$item, true);
            if (!is_array($decoded)) {
                continue;
            }

            $type           = trim((string)($decoded['type']           ?? ''));
            $targetPersonId = trim((string)($decoded['targetPersonId'] ?? ''));

            if ($type === '' || $targetPersonId === '') {
                continue;
            }

            // IDOR: verify target person belongs to same tree
            $target = $this->personRepo->findById($targetPersonId, $treeId);
            if ($target === null) {
                continue;
            }

            try {
                $this->relService->create($treeId, $personId, $targetPersonId, $type);
                $added++;
            } catch (\InvalidArgumentException) {
                // Already exists or invalid — skip silently
            }
        }

        $msg = $added > 0
            ? 'Dodano ' . $added . ' ' . $this->nounRelacje($added) . '.'
            : 'Nie dodano żadnych relacji.';

        $this->response
            ->withFlash($added > 0 ? 'success' : 'info', $msg)
            ->redirect('/trees/' . $treeId . '/persons/' . $personId);
    }

    private function requireEditorAccess(string $treeId, string $userId): void
    {
        $role = $this->treeRepo->getUserRole($treeId, $userId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            $this->response->withFlash('error', 'Brak uprawnień do edycji.')->redirect('/trees');
        }
    }

    private function nounRelacje(int $n): string
    {
        if ($n === 1) return 'relację';
        if ($n >= 2 && $n <= 4) return 'relacje';
        return 'relacji';
    }
}
