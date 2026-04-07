<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\RequiresTreeAccess;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;
use App\Repositories\TreeRepository;
use App\Services\GedcomService;

class GedcomController
{
    use RequiresTreeAccess;

    private const MAX_UPLOAD_BYTES = 50 * 1024 * 1024; // 50 MB
    private const ALLOWED_MIME     = ['text/plain', 'text/x-gedcom', 'application/octet-stream'];

    public function __construct(
        private readonly Request                $request,
        private readonly Response               $response,
        private readonly TreeRepository         $treeRepo,
        private readonly PersonRepository       $personRepo,
        private readonly RelationshipRepository $relRepo,
    ) {}

    /** GET /trees/{id}/gedcom */
    public function page(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId);

        $tree = $this->treeRepo->findById($treeId);
        if ($tree === null) {
            $this->response->withFlash('error', 'Drzewo nie istnieje.')->redirect('/trees');
        }

        $this->response->view('pages/trees/gedcom', [
            'title'       => 'Import / eksport GEDCOM — ' . $tree->name,
            'currentUser' => $this->currentUser(),
            'tree'        => $tree,
        ]);
    }

    /** POST /trees/{id}/gedcom/import */
    public function import(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);
        $this->request->verifyCsrf();

        $file = $_FILES['gedcom_file'] ?? null;

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg  = $code === UPLOAD_ERR_NO_FILE
                ? 'Nie wybrano pliku.'
                : "Błąd podczas przesyłania pliku (kod: {$code}).";
            $this->response->withFlash('error', $msg)
                ->redirect('/trees/' . $treeId . '/gedcom');
        }

        // Verify it was uploaded via HTTP POST
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->response->withFlash('error', 'Nieprawidłowe żądanie.')
                ->redirect('/trees/' . $treeId . '/gedcom');
        }

        // Validate extension
        $originalName = basename((string)($file['name'] ?? ''));
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'ged') {
            $this->response->withFlash('error', 'Dozwolone są tylko pliki z rozszerzeniem .ged.')
                ->redirect('/trees/' . $treeId . '/gedcom');
        }

        // Validate file size
        if ((int)($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES) {
            $this->response->withFlash('error', 'Plik przekracza dozwolony rozmiar 50 MB.')
                ->redirect('/trees/' . $treeId . '/gedcom');
        }

        // Validate MIME type
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            $this->response->withFlash('error', 'Nieprawidłowy typ pliku.')
                ->redirect('/trees/' . $treeId . '/gedcom');
        }

        // Move to temp directory with random name
        $tmpPath = sys_get_temp_dir() . '/' . bin2hex(random_bytes(16)) . '.ged';
        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
            $this->response->withFlash('error', 'Nie udało się przetworzyć pliku.')
                ->redirect('/trees/' . $treeId . '/gedcom');
        }

        // Set conflict strategy from form
        $strategy = $this->request->getParam('conflict_strategy', 'skip');
        if (!in_array($strategy, ['skip', 'update'], true)) {
            $strategy = 'skip';
        }

        try {
            $pdo     = \App\Core\Database::getInstance()->getPdo();
            $gedcom  = new GedcomService($this->personRepo, $this->relRepo, $pdo);
            $gedcom->setConflictStrategy($strategy);

            $result = $gedcom->import($treeId, $tmpPath, $userId);

            $msg = "Zaimportowano {$result->personsCount} " . $this->pluralPerson($result->personsCount)
                . " i {$result->relationshipsCount} relacji.";

            if ($result->skippedCount > 0) {
                $msg .= " Pominięto {$result->skippedCount} istniejących rekordów.";
            }

            // Zapisz pierwsze 10 błędów w sesji żeby UI mogło je wyświetlić
            if (!empty($result->errors)) {
                $errorsCount = count($result->errors);
                $msg .= " Wystąpiło {$errorsCount} ostrzeżeń.";
                Session::set('gedcom_import_errors', array_slice($result->errors, 0, 10));
            } else {
                Session::delete('gedcom_import_errors');
            }

            $this->response->withFlash('success', $msg)
                ->redirect('/trees/' . $treeId . '/persons');
        } catch (\Throwable $e) {
            error_log('GEDCOM import error (tree=' . $treeId . '): ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie udało się zaimportować pliku GEDCOM. Sprawdź czy plik jest prawidłowy i spróbuj ponownie.')
                ->redirect('/trees/' . $treeId . '/gedcom');
        }
    }

    /** GET /trees/{id}/gedcom/export */
    public function export(): never
    {
        $treeId = $this->request->getRouteParam('id');
        $userId = Session::get('user_id');

        $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);

        $tree = $this->treeRepo->findById($treeId);
        if ($tree === null) {
            $this->response->withFlash('error', 'Drzewo nie istnieje.')->redirect('/trees');
        }

        try {
            $pdo    = \App\Core\Database::getInstance()->getPdo();
            $gedcom = new GedcomService($this->personRepo, $this->relRepo, $pdo);
            $output = $gedcom->export($treeId);

            $safeName = preg_replace('/[^a-z0-9\-_]/i', '-', $tree->name) ?: 'drzewo';
            $filename = "drzewo-{$safeName}-{$treeId}.ged";

            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($output));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            echo $output;
            exit;
        } catch (\Throwable $e) {
            error_log('GEDCOM export error (tree=' . $treeId . '): ' . $e->getMessage());
            $this->response->withFlash('error', 'Nie udało się wyeksportować drzewa. Spróbuj ponownie.')
                ->redirect('/trees/' . $treeId . '/gedcom');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function currentUser(): array
    {
        return [
            'name'   => Session::get('user_name', 'Użytkownik'),
            'email'  => Session::get('user_email', ''),
            'avatar' => '',
        ];
    }

    private function pluralPerson(int $count): string
    {
        if ($count === 1) {
            return 'osobę';
        }
        if ($count >= 2 && $count <= 4) {
            return 'osoby';
        }
        return 'osób';
    }
}
