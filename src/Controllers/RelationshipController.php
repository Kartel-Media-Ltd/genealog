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
use App\Services\SuggestionService;

class RelationshipController
{
    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly TreeRepository         $treeRepo,
        private readonly PersonRepository       $personRepo,
        private readonly RelationshipRepository $relRepo,
        private readonly RelationshipService    $relService,
        private readonly SuggestionService      $suggestionService,
    ) {}

    /** GET /trees/{id}/persons/{pid}/relationships/new */
    public function showCreate(): never
    {
        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireEditorAccess($treeId, $userId);

        $person  = $this->personRepo->findById($personId, $treeId);
        if ($person === null) {
            $this->response->withFlash('error', 'Osoba nie istnieje.')->redirect('/trees/' . $treeId . '/persons');
        }

        // Other persons in tree (exclude current), sorted alphabetically by first+last name
        $others = array_filter(
            $this->personRepo->findByTree($treeId),
            fn($p) => $p->id !== $personId
        );
        usort($others, fn($a, $b) =>
            strcmp(mb_strtolower($a->firstName . ' ' . $a->lastName), mb_strtolower($b->firstName . ' ' . $b->lastName))
        );

        $suggestions = $this->suggestionService->compute($personId, $treeId);

        $this->response->view('pages/trees/persons/relationship-create', [
            'title'       => 'Dodaj relację',
            'currentUser' => $this->currentUser(),
            'tree'        => $this->treeRepo->findById($treeId),
            'person'      => $person,
            'others'      => array_values($others),
            'suggestions' => $suggestions,
        ]);
    }

    /** POST /trees/{id}/persons/{pid}/relationships */
    public function processCreate(): never
    {
        $this->request->verifyCsrf();

        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireEditorAccess($treeId, $userId);

        $personBId = trim((string)$this->request->getParam('person_b_id', ''));
        $type      = trim((string)$this->request->getParam('type', ''));

        try {
            $this->relService->create($treeId, $personId, $personBId, $type);
            $this->response
                ->withFlash('success', 'Relacja została dodana.')
                ->redirect('/trees/' . $treeId . '/persons/' . $personId . '?suggest=1');
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/persons/' . $personId . '/relationships/new');
        }
    }

    /** POST /trees/{id}/relationships/{rid}/delete */
    public function delete(): never
    {
        $this->request->verifyCsrf();

        $treeId = $this->request->getRouteParam('id');
        $relId  = $this->request->getRouteParam('rid');
        $userId = Session::get('user_id');

        $this->requireEditorAccess($treeId, $userId);

        $rel = $this->relRepo->findById($relId, $treeId);
        if ($rel === null) {
            $this->response->withFlash('error', 'Relacja nie istnieje.')->redirect('/trees/' . $treeId . '/persons');
        }
        $personId = $rel->personAId;

        try {
            $this->relService->delete($relId, $treeId);
            $this->response
                ->withFlash('success', 'Relacja została usunięta.')
                ->redirect('/trees/' . $treeId . '/persons/' . $personId);
        } catch (\Exception $e) {
            $this->response->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/persons/' . $personId);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function requireEditorAccess(string $treeId, string $userId): void
    {
        $role = $this->treeRepo->getUserRole($treeId, $userId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            $this->response->withFlash('error', 'Brak uprawnień do edycji.')->redirect('/trees');
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
}
