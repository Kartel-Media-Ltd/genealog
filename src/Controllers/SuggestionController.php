<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;
use App\Services\RelationshipService;

class SuggestionController
{
    public function __construct(
        private readonly Request             $request,
        private readonly Response            $response,
        private readonly TreeRepository      $treeRepo,
        private readonly PersonRepository    $personRepo,
        private readonly RelationshipService $relService,
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
            $this->response->withFlash('error', 'Osoba nie istnieje.');
            $this->response->redirect('/trees/' . $treeId . '/persons');
        }

        $raw = $this->request->getParam('suggestions', []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $added           = 0;
        $skippedExisting = 0;          // already-existing relationships are not really errors
        $rejected        = [];          // [['name' => string, 'reason' => string], ...]

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
            } catch (\InvalidArgumentException $e) {
                $msg = $e->getMessage();
                // "already exists" duplicates are expected when the user re-applies suggestions
                if (str_contains($msg, 'już istnieje')) {
                    $skippedExisting++;
                    continue;
                }
                $rejected[] = ['name' => $target->fullName(), 'reason' => $msg];
            }
        }

        // Compose flash message
        if ($added > 0 && empty($rejected)) {
            $this->response->withFlash(
                'success',
                'Dodano ' . $added . ' ' . $this->nounRelacje($added) . '.'
            );
        } elseif ($added > 0 && !empty($rejected)) {
            $this->response->withFlash(
                'warning',
                'Dodano ' . $added . ' ' . $this->nounRelacje($added)
                . ', ale ' . count($rejected) . ' zostało odrzuconych: '
                . $this->summariseRejected($rejected)
            );
        } elseif ($added === 0 && !empty($rejected)) {
            $this->response->withFlash(
                'error',
                'Nie dodano żadnych relacji. Powody: ' . $this->summariseRejected($rejected)
            );
        } else {
            // added === 0 && empty($rejected) — all skipped (already-existing or empty input)
            $this->response->withFlash('info', 'Nie dodano żadnych relacji.');
        }

        $this->response->redirect('/trees/' . $treeId . '/persons/' . $personId);
    }

    private function requireEditorAccess(string $treeId, string $userId): void
    {
        $role = $this->treeRepo->getUserRole($treeId, $userId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            $this->response->withFlash('error', 'Brak uprawnień do edycji.');
            $this->response->redirect('/trees');
        }
    }

    private function nounRelacje(int $n): string
    {
        if ($n === 1) return 'relację';
        if ($n >= 2 && $n <= 4) return 'relacje';
        return 'relacji';
    }

    /**
     * @param array<int, array{name: string, reason: string}> $rejected
     */
    private function summariseRejected(array $rejected): string
    {
        // Show up to 3 specific rejections; if more, append "...i N kolejnych"
        $items = array_slice($rejected, 0, 3);
        $lines = array_map(
            fn($r) => $r['name'] . ' (' . $r['reason'] . ')',
            $items,
        );
        $summary = implode('; ', $lines);
        $extra   = count($rejected) - count($items);
        if ($extra > 0) {
            $summary .= '; …i ' . $extra . ' kolejnych';
        }
        return $summary;
    }
}
