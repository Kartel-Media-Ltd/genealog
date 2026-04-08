<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TreeRepository;
use App\Services\TreeService;

class TreeController
{
    public function __construct(
        private readonly Request         $request,
        private readonly Response        $response,
        private readonly TreeRepository  $treeRepo,
        private readonly TreeService     $treeService,
    ) {}

    /** GET /trees */
    public function index(): never
    {
        $userId      = Session::get('user_id');
        $trees       = $this->treeRepo->findByOwner($userId);
        $sharedTrees = $this->treeRepo->findByMember($userId);

        $this->response->view('pages/trees/index', [
            'title'       => 'Moje drzewa',
            'currentUser' => [
                'name'   => Session::get('user_name', 'Użytkownik'),
                'email'  => Session::get('user_email', ''),
                'avatar' => '',
            ],
            'trees'       => $trees,
            'sharedTrees' => $sharedTrees,
        ]);
    }

    /** GET /trees/new */
    public function showCreate(): never
    {
        $this->response->view('pages/trees/create', [
            'title'       => 'Nowe drzewo',
            'currentUser' => [
                'name'   => Session::get('user_name', 'Użytkownik'),
                'email'  => Session::get('user_email', ''),
                'avatar' => '',
            ],
        ]);
    }

    /** POST /trees */
    public function processCreate(): never
    {
        $this->request->verifyCsrf();

        $userId      = Session::get('user_id');
        $name        = trim((string)$this->request->getParam('name', ''));
        $description = trim((string)$this->request->getParam('description', ''));
        $isPublic    = (bool)$this->request->getParam('is_public', false);

        try {
            $tree = $this->treeService->create($userId, $name, $description, $isPublic);
            $this->response
                ->withFlash('success', 'Drzewo "' . $tree->name . '" zostało utworzone.')
                ->redirect('/trees/' . $tree->id);
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/trees/new');
        } catch (\Throwable $e) {
            error_log('Tree create failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie udało się utworzyć drzewa. Spróbuj ponownie.')->redirect('/trees/new');
        }
    }

    /** GET /trees/{id} */
    public function show(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        try {
            $tree = $this->treeService->getForUser($treeId, $userId);
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect('/trees');
        } catch (\Throwable $e) {
            error_log('Tree show failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie można otworzyć drzewa.')->redirect('/trees');
        }

        $this->response->view('pages/trees/show', [
            'title'       => $tree->name,
            'currentUser' => [
                'name'   => Session::get('user_name', 'Użytkownik'),
                'email'  => Session::get('user_email', ''),
                'avatar' => '',
            ],
            'tree'     => $tree,
            'userRole' => $this->treeRepo->getUserRole($treeId, $userId),
        ]);
    }

    /** GET /trees/{id}/edit */
    public function showEdit(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        if (!$this->treeRepo->isOwner($treeId, $userId)) {
            $this->response->withFlash('error', 'Tylko właściciel może edytować drzewo.')->redirect('/trees');
        }

        $tree = $this->treeRepo->findById($treeId);
        if ($tree === null) {
            $this->response->withFlash('error', 'Drzewo nie istnieje.')->redirect('/trees');
        }

        $this->response->view('pages/trees/edit', [
            'title'       => 'Edytuj: ' . $tree->name,
            'currentUser' => [
                'name'   => Session::get('user_name', 'Użytkownik'),
                'email'  => Session::get('user_email', ''),
                'avatar' => '',
            ],
            'tree' => $tree,
        ]);
    }

    /** GET /trees/{id}/print */
    public function printView(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        try {
            $tree = $this->treeService->getForUser($treeId, $userId);
        } catch (\Throwable $e) {
            error_log('printView access error (tree=' . $treeId . '): ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie można otworzyć drzewa do druku.')->redirect('/trees');
        }

        $this->response->view('pages/trees/print', [
            'pageTitle' => 'Druk drzewa — ' . $tree->name,
            'pageSize'  => 'A3 landscape',
            'tree'      => $tree,
            'treeId'    => $treeId,
        ], 'templates/PrintLayout');
    }

    /** POST /trees/{id}/edit */
    public function processEdit(): never
    {
        $this->request->verifyCsrf();

        $treeId      = $this->request->getRouteParam('id');
        $userId      = Session::get('user_id');
        $name        = trim((string)$this->request->getParam('name', ''));
        $description = trim((string)$this->request->getParam('description', ''));
        $isPublic    = (bool)$this->request->getParam('is_public', false);

        try {
            $this->treeService->update($treeId, $userId, $name, $description, $isPublic);
            $this->response
                ->withFlash('success', 'Zmiany zostały zapisane.')
                ->redirect('/trees/' . $treeId);
        } catch (\InvalidArgumentException $e) {
            $this->response->withFlash('error', $e->getMessage())->redirect("/trees/{$treeId}/edit");
        } catch (\Throwable $e) {
            error_log('Tree update failed: ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie udało się zapisać zmian.')->redirect("/trees/{$treeId}/edit");
        }
    }
}
