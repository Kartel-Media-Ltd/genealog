<?php
declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\Person;
use App\Repositories\DiscoveryRepository;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;
use App\Repositories\UserRepository;
use App\Services\Discovery\DTO\MatchResult;
use App\Services\Discovery\DTO\SearchContext;
use App\Services\Discovery\DTO\SearchCriteria;
use App\Services\NotificationService;

/**
 * Orkiestruje wywołania wszystkich aktywnych źródeł dopasowań i grupuje wyniki
 * po sourceType (`local`, `cross_tree`, `external`).
 *
 * Używany zarówno przez autosuggest (real-time) jak i przez
 * `findAndNotifyMatches()` — SYNCHRONICZNE powiadomienia po dodaniu osoby.
 * ZAD-2.1 (P1): przy bulk GEDCOM import flag `_gedcom_import_in_progress`
 * wyłącza per-person matching (batch event `tree.imported` obsługuje matching
 * po zakończeniu). Docelowo: async job queue (backend-2 Faza 3 odroczone).
 */
final class MatchingService
{
    /** Minimalne confidence żeby zapisać match jako sugestię (filtr noise) */
    private const MIN_CONFIDENCE_TO_PERSIST = 0.5;

    public function __construct(
        private readonly MatchSourceRegistry  $registry,
        private readonly NotificationService  $notifications,
        private readonly TreeRepository       $treeRepo,
        private readonly ?DiscoveryRepository $discoveryRepo = null,
        private readonly ?UserRepository      $userRepo      = null,
        private readonly ?PersonRepository    $personRepo    = null,
    ) {}

    /**
     * @return array{local: list<MatchResult>, crossTree: list<MatchResult>, external: list<MatchResult>}
     */
    public function findCandidates(SearchCriteria $criteria, SearchContext $context): array
    {
        $result = [
            'local'     => [],
            'crossTree' => [],
            'external'  => [],
        ];

        if (!$criteria->isSearchable()) {
            return $result;
        }

        foreach ($this->registry->getEnabled() as $source) {
            $name = $source->getName();

            // Filtry wg kontekstu — ZAD-3.6 (D6): stałe zamiast magic strings.
            if ($name === MatchSourceInterface::SOURCE_LOCAL && !$context->includeLocal) {
                continue;
            }
            if ($name === MatchSourceInterface::SOURCE_CROSS_TREE && !$context->includeCrossTree) {
                continue;
            }
            if (!in_array($name, [MatchSourceInterface::SOURCE_LOCAL, MatchSourceInterface::SOURCE_CROSS_TREE], true)
                && !$context->includeExternal
            ) {
                continue;
            }

            try {
                $matches = $source->search($criteria, $context);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[MatchingService] source "%s" failed: %s',
                    $name,
                    $e->getMessage()
                ));
                continue;
            }

            $bucket = match ($name) {
                MatchSourceInterface::SOURCE_LOCAL      => 'local',
                MatchSourceInterface::SOURCE_CROSS_TREE => 'crossTree',
                default                                  => 'external',
            };
            foreach ($matches as $match) {
                $result[$bucket][] = $match;
            }
        }

        return $result;
    }

    /**
     * Wywoływany synchronicznie przez EventDispatcher po `person.created`.
     * Szuka cross-tree dopasowań dla nowo dodanej osoby i tworzy powiadomienie
     * dla ownera drzewa gdy znajdzie potencjalne powiązanie.
     *
     * RODO: korzysta z CrossTreeMatchSource który zwraca tylko anonimowe dane.
     * Notyfikacja też jest anonimowa — bez nazwy drzewa źródłowego.
     */
    public function findAndNotifyMatches(Person $person, string $userId): void
    {
        // Nie szukaj dla żyjących osób (RODO) ani gdy brak podstawowych danych
        if ($person->isLiving) {
            return;
        }

        // ZAD-2.2 (P2): RODO Art. 7(3) — jeśli właściciel drzewa wycofał zgodę
        // na Discovery, nie wywołuj cross-tree matching. Nawet jeśli osoba jest
        // w `global_person_index` (race condition przy unindexTree), nie generujemy
        // nowych sugestii. Check po pobraniu $tree poniżej.

        $birthYear = null;
        if (!empty($person->birthDate)) {
            $year = (int)substr($person->birthDate, 0, 4);
            if ($year > 0) {
                $birthYear = $year;
            }
        }

        $criteria = new SearchCriteria(
            firstName:  $person->firstName,
            lastName:   $person->lastName,
            birthYear:  $birthYear,
            birthPlace: $person->birthPlace,
        );
        if (!$criteria->isSearchable()) {
            return;
        }

        // Kontekst: tylko cross-tree, bez lokalnych (te user widzi w panelu)
        $accessibleTreeIds = $this->treeRepo->findAccessibleIdsForUser($userId);
        $context = new SearchContext(
            currentUserId:     $userId,
            currentTreeId:     $person->treeId,
            accessibleTreeIds: $accessibleTreeIds,
            includeLocal:      false,
            includeCrossTree:  true,
            includeExternal:   false,
        );

        $tree = $this->treeRepo->findById($person->treeId);
        if ($tree === null) {
            return;
        }

        // ZAD-2.2 (P2): opt-in check — user wycofał zgodę → brak matching
        if ($this->userRepo !== null && !$this->userRepo->isDiscoveryOptedIn($tree->ownerId)) {
            return;
        }

        $crossTreeSource = $this->registry->get(MatchSourceInterface::SOURCE_CROSS_TREE);
        if ($crossTreeSource === null || !$crossTreeSource->isAvailable()) {
            return;
        }

        try {
            $matches = $crossTreeSource->search($criteria, $context);
        } catch (\Throwable $e) {
            error_log('[MatchingService] findAndNotifyMatches failed: ' . $e->getMessage());
            return;
        }

        if (empty($matches)) {
            return;
        }

        // B1 fix: persistuj sugestie ZANIM wyślesz notyfikację — żeby panel
        // "Możliwe powiązania" w show.php miał co pokazać po kliknięciu w bell.
        $persistedCount = 0;
        if ($this->discoveryRepo !== null) {
            foreach ($matches as $match) {
                if ($match->confidence < self::MIN_CONFIDENCE_TO_PERSIST) {
                    continue;
                }
                try {
                    $this->discoveryRepo->saveSuggestion(
                        personId:    $person->id,
                        sourceType:  $match->sourceType,
                        sourceId:    $match->sourceId,
                        sourceData:  $match->jsonSerialize(),
                        confidence:  $match->confidence,
                        forUserId:   $tree->ownerId,
                    );
                    $persistedCount++;
                } catch (\Throwable $e) {
                    error_log(sprintf(
                        '[MatchingService] saveSuggestion failed for person=%s source=%s: %s',
                        $person->id, $match->sourceId, $e->getMessage()
                    ));
                }
            }
        }

        // Jedno powiadomienie na osobę — nie spamujemy.
        // Dedup sprawdzany przez `NotificationRepository::existsRecentForLink` (24h window),
        // wywoływany wewnątrz NotificationService::notifyPersonMatch → dispatch().
        if ($persistedCount > 0) {
            $this->notifications->notifyPersonMatch(
                treeOwnerId: $tree->ownerId,
                personName:  trim($person->firstName . ' ' . $person->lastName),
                treeId:      $person->treeId,
                personId:    $person->id,
            );
        }
    }

    /**
     * ZAD-2.1 (P1): Batch matching po imporcie GEDCOM.
     *
     * Wywoływane RAZ po zakończeniu `GedcomService::import` przez event `tree.imported`,
     * zamiast N razy przez `person.created` (które jest wyłączone flagą
     * `$GLOBALS['_gedcom_import_in_progress']`).
     *
     * Strategia anti-DoS: limit max 100 najnowszych osób z drzewa. Przy większych
     * importach pełny matching wymaga manualnego uruchomienia reindexTree przez usera.
     *
     * Sprawdza opt-in raz i skiptuje żyjące osoby — privacy by design.
     */
    public function matchTreeAfterImport(string $treeId, string $userId): void
    {
        $tree = $this->treeRepo->findById($treeId);
        if ($tree === null) {
            return;
        }
        if ($this->userRepo !== null && !$this->userRepo->isDiscoveryOptedIn($tree->ownerId)) {
            return;
        }

        @set_time_limit(300); // spójnie z GlobalIndexService::reindexTree

        if ($this->personRepo === null) {
            return;
        }

        // Limit: 100 osób — chroni przed DoS przy bulk imporcie 10k+ osób.
        // User może zawsze uruchomić pełny reindex przez ręczne żądanie (DiscoveryController::reindexTree).
        $allPersons = $this->personRepo->findByTree($treeId);
        $persons = array_slice($allPersons, 0, 100);
        if (empty($persons)) {
            return;
        }

        foreach ($persons as $person) {
            if ($person->isLiving) {
                continue;
            }
            try {
                $this->findAndNotifyMatches($person, $userId);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[MatchingService] matchTreeAfterImport failed for person=%s: %s',
                    $person->id, $e->getMessage()
                ));
            }
        }
    }
}
