<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Uuid;
use App\Models\Tree;
use App\Repositories\TreeRepository;

class TreeService
{
    public function __construct(private readonly TreeRepository $treeRepo) {}

    public function create(string $ownerId, string $name, ?string $description, bool $isPublic): Tree
    {
        $name = trim($name);

        if (strlen($name) < 2 || strlen($name) > 150) {
            throw new \InvalidArgumentException('Nazwa drzewa musi mieć od 2 do 150 znaków.');
        }

        $id = Uuid::generate();
        $this->treeRepo->create($id, $ownerId, $name, $description ?: null, $isPublic);

        return $this->treeRepo->findById($id)
            ?? throw new \RuntimeException('Błąd tworzenia drzewa.');
    }

    public function update(string $treeId, string $userId, string $name, ?string $description, bool $isPublic): Tree
    {
        if (!$this->treeRepo->isOwner($treeId, $userId)) {
            throw new \RuntimeException('Brak uprawnień do edycji tego drzewa.');
        }

        $name = trim($name);
        if (strlen($name) < 2 || strlen($name) > 150) {
            throw new \InvalidArgumentException('Nazwa drzewa musi mieć od 2 do 150 znaków.');
        }

        $this->treeRepo->update($treeId, $name, $description ?: null, $isPublic);

        return $this->treeRepo->findById($treeId)
            ?? throw new \RuntimeException('Drzewo nie istnieje.');
    }

    public function getForUser(string $treeId, string $userId): Tree
    {
        $tree = $this->treeRepo->findForUser($treeId, $userId);
        if ($tree === null) {
            throw new \RuntimeException('Drzewo nie istnieje lub brak dostępu.');
        }

        return $tree;
    }

}
