<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Uuid;
use App\Models\CrossTreeLink;
use App\Repositories\CrossTreeLinkRepository;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;

/**
 * Serwis powiązań cross-tree (Opcja C).
 *
 * Przepływ:
 *   1. createRequest()   — właściciel drzewa A wysyła prośbę o połączenie osoby X z osobą Y (z drzewa B)
 *   2. accept()          — właściciel drzewa B akceptuje prośbę
 *   3. reject()          — właściciel drzewa B odrzuca prośbę
 *   4. cancel()          — właściciel drzewa A anuluje oczekującą prośbę
 *   5. remove()          — każda ze stron usuwa zaakceptowane połączenie (soft: status = broken)
 *   6. getMergedViewData() — dane do merged D3.js tree view
 */
class CrossTreeLinkService
{
    public function __construct(
        private readonly CrossTreeLinkRepository $linkRepo,
        private readonly PersonRepository        $personRepo,
        private readonly TreeRepository          $treeRepo,
        private readonly NotificationService     $notifSvc,
    ) {}

    // -------------------------------------------------------------------------
    // Tworzenie / modyfikacja
    // -------------------------------------------------------------------------

    /**
     * Wyślij prośbę o połączenie dwóch osób.
     *
     * @throws \InvalidArgumentException gdy osoby są w tym samym drzewie,
     *                                   lub połączenie już istnieje,
     *                                   lub requester nie jest właścicielem requesterPerson
     */
    public function createRequest(
        string  $requesterPersonId,
        string  $targetPersonId,
        string  $requesterUserId,
        string  $visibilityLevel = CrossTreeLink::VISIBILITY_BASIC,
        ?string $note = null,
        bool    $adminOverride = false,
    ): CrossTreeLink {
        $reqPerson = $this->personRepo->findByIdGlobal($requesterPersonId);
        $tgtPerson = $this->personRepo->findByIdGlobal($targetPersonId);

        if ($reqPerson === null || $tgtPerson === null) {
            throw new \InvalidArgumentException('Nie znaleziono osoby.');
        }

        if ($reqPerson->treeId === $tgtPerson->treeId) {
            throw new \InvalidArgumentException('Obie osoby są w tym samym drzewie — połączenie cross-tree wymaga różnych drzew.');
        }

        // Sprawdź czy requester ma dostęp do swojej osoby
        $reqTree = $this->treeRepo->findById($reqPerson->treeId);
        if ($reqTree === null || $reqTree->ownerId !== $requesterUserId) {
            $role = $this->treeRepo->getUserRole($reqPerson->treeId, $requesterUserId);
            if (!in_array($role, ['owner', 'editor'], true)) {
                throw new \InvalidArgumentException('Brak uprawnień do osoby z Twojego drzewa.');
            }
        }

        // Znajdź właściciela docelowego drzewa
        $tgtTree = $this->treeRepo->findById($tgtPerson->treeId);
        if ($tgtTree === null) {
            throw new \InvalidArgumentException('Drzewo docelowe nie istnieje.');
        }
        $targetUserId = $tgtTree->ownerId;

        // Nie można wysłać prośby do samego siebie
        if ($targetUserId === $requesterUserId) {
            throw new \InvalidArgumentException('Nie możesz połączyć osób ze swoich własnych drzew. Użyj relacji w ramach jednego drzewa.');
        }

        // Sprawdź czy połączenie już istnieje
        $existing = $this->linkRepo->findByPersonPair($requesterPersonId, $targetPersonId);
        if ($existing !== null) {
            $resettableStatuses = [CrossTreeLink::STATUS_REJECTED, CrossTreeLink::STATUS_CANCELLED];
            if ($adminOverride && in_array($existing->status, $resettableStatuses, true)) {
                // Admin może nadpisać odrzuconą/anulowaną prośbę — usuń starą i utwórz nową
                $this->linkRepo->deleteById($existing->id);
            } else {
                $statusLabel = match($existing->status) {
                    CrossTreeLink::STATUS_PENDING   => 'oczekujące',
                    CrossTreeLink::STATUS_ACCEPTED  => 'zaakceptowane',
                    CrossTreeLink::STATUS_REJECTED  => 'odrzucone',
                    CrossTreeLink::STATUS_CANCELLED => 'anulowane',
                    CrossTreeLink::STATUS_BROKEN    => 'zerwane',
                    default => $existing->status,
                };
                throw new \InvalidArgumentException("Połączenie między tymi osobami już istnieje (status: {$statusLabel}).");
            }
        }

        $id = Uuid::generate();
        $this->linkRepo->create($id, $requesterPersonId, $targetPersonId, $requesterUserId, $targetUserId, $visibilityLevel, $note);

        // Powiadomienie dla właściciela docelowego drzewa
        $reqName = $reqPerson->firstName . ' ' . $reqPerson->lastName;
        $tgtName = $tgtPerson->firstName . ' ' . $tgtPerson->lastName;
        $this->notifSvc->dispatch(
            userId: $targetUserId,
            type:   'cross_tree_request',
            title:  'Prośba o powiązanie osób',
            body:   "Użytkownik prosi o połączenie osoby \"{$reqName}\" ze swoim drzewa z Twoją osobą \"{$tgtName}\".",
            link:   '/connections',
        );

        return CrossTreeLink::fromArray([
            'id'                  => $id,
            'requester_person_id' => $requesterPersonId,
            'target_person_id'    => $targetPersonId,
            'requester_user_id'   => $requesterUserId,
            'target_user_id'      => $targetUserId,
            'status'              => CrossTreeLink::STATUS_PENDING,
            'visibility_level'    => $visibilityLevel,
            'note'                => $note,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Zaakceptuj oczekującą prośbę (tylko właściciel docelowego drzewa).
     */
    public function accept(string $linkId, string $currentUserId): CrossTreeLink
    {
        $link = $this->requireLink($linkId, $currentUserId);

        if (!$link->isTargetUser($currentUserId)) {
            throw new \InvalidArgumentException('Tylko adresat prośby może ją zaakceptować.');
        }
        if (!$link->isPending()) {
            throw new \InvalidArgumentException('Prośba nie jest w stanie oczekującym.');
        }

        $this->linkRepo->updateStatusIfEquals($linkId, CrossTreeLink::STATUS_PENDING, CrossTreeLink::STATUS_ACCEPTED);

        // Powiadom requesterа
        $tgtPerson = $this->personRepo->findByIdGlobal($link->targetPersonId);
        $reqPerson = $this->personRepo->findByIdGlobal($link->requesterPersonId);
        if ($tgtPerson && $reqPerson) {
            $tgtName = $tgtPerson->firstName . ' ' . $tgtPerson->lastName;
            $reqName = $reqPerson->firstName . ' ' . $reqPerson->lastName;
            $this->notifSvc->dispatch(
                userId: $link->requesterUserId,
                type:   'cross_tree_accepted',
                title:  'Powiązanie zaakceptowane',
                body:   "Twoja prośba o połączenie \"{$reqName}\" z \"{$tgtName}\" została zaakceptowana.",
                link:   '/trees/' . $reqPerson->treeId . '/persons/' . $reqPerson->id,
            );
        }

        return $this->linkRepo->findById($linkId) ?? throw new \RuntimeException('Link not found after update.');
    }

    /**
     * Odrzuć prośbę (tylko adresat).
     */
    public function reject(string $linkId, string $currentUserId): void
    {
        $link = $this->requireLink($linkId, $currentUserId);
        if (!$link->isTargetUser($currentUserId)) {
            throw new \InvalidArgumentException('Tylko adresat prośby może ją odrzucić.');
        }
        if (!$link->isPending()) {
            throw new \InvalidArgumentException('Prośba nie jest w stanie oczekującym.');
        }
        $this->linkRepo->updateStatusIfEquals($linkId, CrossTreeLink::STATUS_PENDING, CrossTreeLink::STATUS_REJECTED);
    }

    /**
     * Anuluj wysłaną prośbę (tylko requester, tylko pending).
     */
    public function cancel(string $linkId, string $currentUserId): void
    {
        $link = $this->requireLink($linkId, $currentUserId);
        if (!$link->isRequesterUser($currentUserId)) {
            throw new \InvalidArgumentException('Tylko nadawca prośby może ją anulować.');
        }
        if (!$link->isPending()) {
            throw new \InvalidArgumentException('Można anulować tylko oczekujące prośby.');
        }
        $this->linkRepo->updateStatusIfEquals($linkId, CrossTreeLink::STATUS_PENDING, CrossTreeLink::STATUS_CANCELLED);
    }

    /**
     * Usuń zaakceptowane połączenie (obie strony mogą usunąć).
     * Soft-delete: status = broken.
     */
    public function remove(string $linkId, string $currentUserId): void
    {
        $link = $this->requireLink($linkId, $currentUserId);
        if (!$link->isAccepted()) {
            throw new \InvalidArgumentException('Można usuwać tylko zaakceptowane połączenia.');
        }
        $this->linkRepo->updateStatusIfEquals($linkId, CrossTreeLink::STATUS_ACCEPTED, CrossTreeLink::STATUS_BROKEN);
    }

    // -------------------------------------------------------------------------
    // Dane do merged D3.js view
    // -------------------------------------------------------------------------

    /**
     * Zbierz dane do wyrenderowania scalonego drzewa (lokalne + osoby z połączonych drzew).
     *
     * @return array{
     *   localPersons: array[],
     *   remotePersons: array[],
     *   localRelationships: array[],
     *   crossLinks: array[],
     * }
     */
    public function getMergedViewData(string $treeId, string $currentUserId): array
    {
        // Lokalne osoby (pełne dane) — jako tablice dla łatwej serializacji do JSON
        $localPersonObjects = $this->personRepo->findByTree($treeId);
        $localPersonIds     = array_map(fn($p) => $p->id, $localPersonObjects);

        // Połączone pary (cross-tree edges)
        $crossPairs = $this->linkRepo->findAcceptedPairsForTree($treeId);

        if (empty($crossPairs)) {
            return [
                'localPersons'  => array_map(fn($p) => $this->personToArray($p), $localPersonObjects),
                'remotePersons' => [],
                'crossLinks'    => [],
            ];
        }
        $localPersons = array_map(fn($p) => $this->personToArray($p), $localPersonObjects);
        $remotePersonIds = [];
        foreach ($crossPairs as $pair) {
            $remoteId = in_array($pair['person_a_id'], $localPersonIds, true)
                ? $pair['person_b_id']
                : $pair['person_a_id'];
            $remotePersonIds[] = $remoteId;
        }
        $remotePersonIds = array_values(array_unique($remotePersonIds));

        // Załaduj zdalne osoby (filtrowane przez visibility_level)
        $remotePersons = $this->loadRemotePersons($remotePersonIds, $crossPairs, $localPersonIds);

        return [
            'localPersons'  => $localPersons,
            'remotePersons' => $remotePersons,
            'crossLinks'    => $crossPairs, // arrays from DB
        ];
    }

    // -------------------------------------------------------------------------
    // Prywatne helpery
    // -------------------------------------------------------------------------

    private function requireLink(string $linkId, string $userId): CrossTreeLink
    {
        $link = $this->linkRepo->findById($linkId);
        if ($link === null) {
            throw new \InvalidArgumentException('Połączenie nie istnieje.');
        }
        if (!$link->involveUser($userId)) {
            throw new \InvalidArgumentException('Brak dostępu do tego połączenia.');
        }
        return $link;
    }

    /** Konwertuje obiekt Person na tablicę dla API/JSON */
    private function personToArray(\App\Models\Person $p): array
    {
        return [
            'id'          => $p->id,
            'tree_id'     => $p->treeId,
            'first_name'  => $p->firstName,
            'last_name'   => $p->lastName,
            'maiden_name' => $p->maidenName,
            'birth_date'  => $p->birthDate,
            'birth_place' => $p->birthPlace,
            'death_date'  => $p->deathDate,
            'death_place' => $p->deathPlace,
            'gender'      => $p->gender,
            'is_living'   => $p->isLiving,
            'is_remote'   => false,
        ];
    }

    /**
     * Ładuje zdalne osoby z uwzględnieniem poziomu prywatności.
     *
     * shared_basic: first_name, last_name, birth_date (tylko rok), gender, tree_id
     * shared_full:  wszystkie pola poza notes (RODO: notatki zostają prywatne)
     */
    private function loadRemotePersons(array $remotePersonIds, array $crossPairs, array $localPersonIds): array
    {
        // Buduj mapę personId → visibility_level
        $visibilityMap = [];
        foreach ($crossPairs as $pair) {
            $remoteId = in_array($pair['person_a_id'], $localPersonIds, true)
                ? $pair['person_b_id']
                : $pair['person_a_id'];
            $visibilityMap[$remoteId] = $pair['visibility_level'];
        }

        $remotePersons = [];
        foreach ($remotePersonIds as $remoteId) {
            $person = $this->personRepo->findByIdGlobal($remoteId);
            if ($person === null) {
                continue;
            }
            $level = $visibilityMap[$remoteId] ?? CrossTreeLink::VISIBILITY_BASIC;

            if ($level === CrossTreeLink::VISIBILITY_FULL) {
                $remotePersons[] = [
                    'id'          => $person->id,
                    'tree_id'     => $person->treeId,
                    'first_name'  => $person->firstName,
                    'last_name'   => $person->lastName,
                    'maiden_name' => $person->maidenName,
                    'birth_date'  => $person->birthDate,
                    'birth_place' => $person->birthPlace,
                    'death_date'  => $person->deathDate,
                    'death_place' => $person->deathPlace,
                    'gender'      => $person->gender,
                    'is_living'   => $person->isLiving,
                    'is_remote'   => true,
                    'visibility_level' => $level,
                ];
            } else {
                // shared_basic: tylko imię/nazwisko/rok urodzenia/płeć
                $birthYear = null;
                if ($person->birthDate !== null) {
                    $birthYear = substr($person->birthDate, 0, 4);
                }
                $remotePersons[] = [
                    'id'               => $person->id,
                    'tree_id'          => $person->treeId,
                    'first_name'       => $person->firstName,
                    'last_name'        => $person->lastName,
                    'maiden_name'      => null,
                    'birth_date'       => $birthYear ? ($birthYear . '-01-01') : null,
                    'birth_place'      => null,
                    'death_date'       => null,
                    'death_place'      => null,
                    'gender'           => $person->gender,
                    'is_living'        => $person->isLiving,
                    'is_remote'        => true,
                    'visibility_level' => $level,
                ];
            }
        }

        return $remotePersons;
    }
}
