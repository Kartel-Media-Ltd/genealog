<?php
declare(strict_types=1);

namespace App\Controllers\Concerns;

/**
 * Trait — sprawdzanie dostępu do drzewa genealogicznego.
 *
 * Wymaga że klasa używająca ma wstrzyknięte:
 *   - Request    $request
 *   - Response   $response
 *   - TreeRepository $treeRepo
 *
 * Zamiast duplikować `requireTreeAccess` w każdym kontrolerze, użyj traita:
 *
 *     use \App\Controllers\Concerns\RequiresTreeAccess;
 *
 * Następnie wywołuj `$this->requireTreeAccess($treeId, $userId)` lub
 * `$this->requireTreeAccess($treeId, $userId, ['owner', 'editor'])`.
 */
trait RequiresTreeAccess
{
    /**
     * Sprawdza czy użytkownik ma dostęp do drzewa z określoną rolą.
     * Przy braku dostępu — flash + redirect do /trees + exit.
     *
     * @param string[] $roles Dozwolone role (domyślnie wszystkie)
     * @return string Aktualna rola użytkownika
     */
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

    /**
     * Skrót — wymaga roli umożliwiającej edycję (owner lub editor).
     */
    private function requireEditorAccess(string $treeId, string $userId): string
    {
        return $this->requireTreeAccess($treeId, $userId, ['owner', 'editor']);
    }
}
