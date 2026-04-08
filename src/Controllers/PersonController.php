<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Person;
use App\Repositories\DiscoveryRepository;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;
use App\Repositories\TreeRepository;
use App\Services\MediaService;
use App\Services\PersonService;
use App\Services\RegisterService;
use App\Services\SuggestionService;

class PersonController
{
    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly TreeRepository         $treeRepo,
        private readonly PersonRepository       $personRepo,
        private readonly PersonService          $personService,
        private readonly MediaService           $mediaService,
        private readonly RelationshipRepository $relRepo,
        private readonly SuggestionService      $suggestionService,
        private readonly RegisterService        $registerService,
        private readonly ?DiscoveryRepository   $discoveryRepo = null,
    ) {}

    /** GET /trees/{id}/persons */
    public function index(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId);

        $tree          = $this->treeRepo->findById($treeId);
        $persons       = $this->personService->getForTree($treeId, 'first_name');
        $relationships = $this->relRepo->findByTree($treeId);
        $personsTree   = $this->buildPersonHierarchy($persons, $relationships);

        $this->response->view('pages/trees/persons/index', [
            'title'       => 'Osoby — ' . ($tree?->name ?? ''),
            'currentUser' => $this->currentUser(),
            'tree'        => $tree,
            'persons'     => $persons,
            'personsTree' => $personsTree,
            'userRole'    => $this->treeRepo->getUserRole($treeId, $userId),
        ]);
    }

    /**
     * Builds a flat list with depth info for hierarchical display.
     * Persons sorted by first_name ASC; children grouped under their parents.
     *
     * @param  Person[]        $persons
     * @param  \App\Models\Relationship[] $relationships
     * @return array{person: Person, depth: int}[]
     */
    private function buildPersonHierarchy(array $persons, array $relationships): array
    {
        $byId = [];
        foreach ($persons as $p) {
            $byId[$p->id] = $p;
        }

        // 'child' type: person_a_id IS the parent, person_b_id IS the child
        // ('parent' type means person_a HAS person_b as their parent — opposite direction)
        $parentToChildren = [];
        $hasParent        = [];
        foreach ($relationships as $rel) {
            if ($rel->type === 'child') {
                $parentToChildren[$rel->personAId][] = $rel->personBId;
                $hasParent[$rel->personBId]           = true;
            }
        }

        // Sort each group of siblings by first_name ASC
        foreach ($parentToChildren as &$children) {
            usort($children, fn($a, $b) =>
                strcmp($byId[$a]->firstName ?? '', $byId[$b]->firstName ?? ''));
        }
        unset($children);

        // Roots = persons with no parent in this tree, sorted by first_name ASC
        $roots = array_values(array_filter($persons, fn($p) => !isset($hasParent[$p->id])));
        usort($roots, fn($a, $b) => strcmp($a->firstName, $b->firstName));

        $result = [];
        $shown  = []; // tracks persons shown at least once (for disconnected fallback)

        $flatten = null;
        // $path = ancestors in current DFS path — prevents cycles, NOT duplicates.
        // A child with two parents will appear once under each parent (correct behaviour).
        $flatten = function (string $pid, int $depth, array $path = []) use (
            &$flatten, &$result, &$shown, &$byId, &$parentToChildren
        ): void {
            if (!isset($byId[$pid]) || isset($path[$pid])) {
                return; // unknown person or cycle in current branch
            }
            $shown[$pid] = true;
            $result[]    = ['person' => $byId[$pid], 'depth' => $depth];
            $path[$pid]  = true;
            foreach ($parentToChildren[$pid] ?? [] as $childId) {
                $flatten($childId, $depth + 1, $path);
            }
        };

        foreach ($roots as $root) {
            $flatten($root->id, 0);
        }
        // Append any disconnected persons (no parent/child rels) at depth 0
        foreach ($persons as $p) {
            if (!isset($shown[$p->id])) {
                $flatten($p->id, 0);
            }
        }

        return $result;
    }

    /** GET /trees/{id}/persons/new */
    public function showCreate(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);

        $tree = $this->treeRepo->findById($treeId);

        $this->response->view('pages/trees/persons/create', [
            'title'       => 'Dodaj osobę',
            'currentUser' => $this->currentUser(),
            'tree'        => $tree,
        ]);
    }

    /** POST /trees/{id}/persons */
    public function processCreate(): never
    {
        $this->request->verifyCsrf();

        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);

        try {
            $person = $this->personService->create($treeId, $userId, $this->request->getBody());
            $this->response
                ->withFlash('success', 'Osoba "' . $person->fullName() . '" została dodana.')
                ->redirect('/trees/' . $treeId . '/persons/' . $person->id);
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/persons/new');
        }
    }

    /** GET /trees/{id}/persons/{pid} */
    public function show(): never
    {
        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $role = $this->requireTreeAccess($treeId, $userId);

        $person        = $this->requirePersonInTree($personId, $treeId);
        $relationships = $this->relRepo->findByPerson($personId, $treeId);
        $canEdit       = in_array($role, ['owner', 'editor'], true);

        $suggestions = [];
        if ($canEdit && $this->request->getParam('suggest') === '1') {
            $suggestions = $this->suggestionService->compute($personId, $treeId);
        }

        // Discovery match suggestions (cross-tree, external sources, local fingerprint)
        // Tylko dla editor/owner — viewer nie powinien widzieć potencjalnych dopasowań
        $matchSuggestions = [];
        if ($canEdit && $this->discoveryRepo !== null) {
            try {
                $matchSuggestions = $this->discoveryRepo->findPendingForPerson($personId, $userId);
            } catch (\Throwable $e) {
                error_log('Failed to load match suggestions: ' . $e->getMessage());
            }
        }

        $this->response->view('pages/trees/persons/show', [
            'title'            => $person->fullName(),
            'currentUser'      => $this->currentUser(),
            'tree'             => $this->treeRepo->findById($treeId),
            'person'           => $person,
            'userRole'         => $role,
            'canEdit'          => $canEdit,
            'relationships'    => $relationships,
            'suggestions'      => $suggestions,
            'matchSuggestions' => $matchSuggestions,
        ]);
    }

    /** GET /trees/{id}/persons/{pid}/edit */
    public function showEdit(): never
    {
        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);

        $person = $this->requirePersonInTree($personId, $treeId);

        $this->response->view('pages/trees/persons/edit', [
            'title'       => 'Edytuj: ' . $person->fullName(),
            'currentUser' => $this->currentUser(),
            'tree'        => $this->treeRepo->findById($treeId),
            'person'      => $person,
        ]);
    }

    /** POST /trees/{id}/persons/{pid}/edit */
    public function processEdit(): never
    {
        $this->request->verifyCsrf();

        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);
        $this->requirePersonInTree($personId, $treeId);

        try {
            $person = $this->personService->update($personId, $treeId, $userId, $this->request->getBody());
            $this->response
                ->withFlash('success', 'Dane zostały zaktualizowane.')
                ->redirect('/trees/' . $treeId . '/persons/' . $person->id);
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/persons/' . $personId . '/edit');
        }
    }

    /** POST /trees/{id}/persons/{pid}/photo */
    public function uploadPhoto(): never
    {
        $this->request->verifyCsrf();

        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);
        $this->requirePersonInTree($personId, $treeId);

        $file = $_FILES['photo'] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->response->withFlash('error', 'Nie wybrano pliku.')
                ->redirect('/trees/' . $treeId . '/persons/' . $personId . '/edit');
        }

        try {
            $this->mediaService->uploadPhoto($personId, $treeId, $file);
            $this->response
                ->withFlash('success', 'Zdjęcie zostało zaktualizowane.')
                ->redirect('/trees/' . $treeId . '/persons/' . $personId . '/edit');
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/persons/' . $personId . '/edit');
        } catch (\Throwable $e) {
            error_log('Photo upload failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie udało się przesłać zdjęcia.')
                ->redirect('/trees/' . $treeId . '/persons/' . $personId . '/edit');
        }
    }

    /** POST /trees/{id}/persons/{pid}/delete */
    public function delete(): never
    {
        $this->request->verifyCsrf();

        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);
        $person = $this->requirePersonInTree($personId, $treeId);

        try {
            $this->personService->delete($personId, $treeId);
            $this->response
                ->withFlash('success', 'Osoba "' . $person->fullName() . '" została usunięta.')
                ->redirect('/trees/' . $treeId . '/persons');
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())
                ->redirect('/trees/' . $treeId . '/persons/' . $personId);
        } catch (\Throwable $e) {
            error_log('Person delete failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie udało się usunąć osoby.')
                ->redirect('/trees/' . $treeId . '/persons/' . $personId);
        }
    }

    /** GET /trees/{id}/persons/{pid}/register */
    public function showRegister(): never
    {
        $treeId   = $this->request->getRouteParam('id');
        $personId = $this->request->getRouteParam('pid');
        $userId   = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId);
        $person = $this->requirePersonInTree($personId, $treeId);

        $this->response->view('pages/trees/persons/register', [
            'title'           => 'Rejestr potomków — ' . $person->fullName(),
            'currentUser'     => $this->currentUser(),
            'tree'            => $this->treeRepo->findById($treeId),
            'person'          => $person,
            'cognaticList'    => $this->registerService->build($personId, $treeId, 'cognatic'),
            'patrilinearList' => $this->registerService->build($personId, $treeId, 'patrilinear'),
        ]);
    }

    /** GET /trees/{id}/persons/print */
    public function printList(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        // Konsystentnie z TreeController::printView — pojedyncze sprawdzenie
        // dostępu przez findForUser (zwraca null gdy brak access lub drzewo nie istnieje).
        $tree = $this->treeRepo->findForUser($treeId, $userId);
        if ($tree === null) {
            error_log('printList access denied (tree=' . $treeId . ', user=' . $userId . ')');
            $this->response->withFlash('error', 'Brak dostępu do drzewa.')->redirect('/trees');
        }

        $mode    = $this->request->getParam('mode', 'list');
        // findByTree(..., 'last_name') sortuje po last_name, first_name (z whitelist)
        $persons = $this->personRepo->findByTree($treeId, 'last_name');

        $personsTree = null;
        if ($mode === 'hierarchy') {
            $relationships = $this->relRepo->findByTree($treeId);
            $personsTree   = $this->buildPersonHierarchy($persons, $relationships);

            // Compute decimal numbers for the hierarchy
            $counters = [];
            foreach ($personsTree as &$entry) {
                $d = $entry['depth'];
                foreach (array_keys($counters) as $k) {
                    if ($k > $d) unset($counters[$k]);
                }
                $counters[$d] = ($counters[$d] ?? 0) + 1;
                $parts = [];
                for ($i = 0; $i <= $d; $i++) {
                    $parts[] = $counters[$i] ?? 1;
                }
                $entry['number'] = implode('.', $parts);
            }
            unset($entry);
        }

        $this->response->view('pages/trees/persons-print', [
            'pageTitle'   => 'Lista osób — ' . $tree->name,
            'pageSize'    => 'A4 portrait',
            'tree'        => $tree,
            'treeId'      => $treeId,
            'persons'     => $persons,
            'mode'        => $mode,
            'personsTree' => $personsTree,
        ], 'templates/PrintLayout');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function requireTreeAccess(
        string $treeId,
        string $userId,
        array $roles = ['owner', 'editor', 'viewer'],
    ): string {
        $role = $this->treeRepo->getUserRole($treeId, $userId);
        if (!in_array($role, $roles, true)) {
            $this->response->withFlash('error', 'Brak dostępu do drzewa.')->redirect('/trees');
        }
        return (string)$role;
    }

    private function requirePersonInTree(string $personId, string $treeId): Person
    {
        $person = $this->personRepo->findById($personId, $treeId);
        if ($person === null) {
            $this->response->withFlash('error', 'Osoba nie istnieje.')->redirect('/trees/' . $treeId . '/persons');
        }
        return $person;
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
