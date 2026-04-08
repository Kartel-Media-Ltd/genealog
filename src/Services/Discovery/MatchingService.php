<?php
declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\Person;
use App\Repositories\DiscoveryRepository;
use App\Repositories\TreeRepository;
use App\Services\Discovery\DTO\MatchResult;
use App\Services\Discovery\DTO\SearchContext;
use App\Services\Discovery\DTO\SearchCriteria;
use App\Services\NotificationService;

/**
 * Orkiestruje wywołania wszystkich aktywnych źródeł dopasowań i grupuje wyniki
 * po sourceType (`local`, `cross_tree`, `external`).
 *
 * Używany zarówno przez autosuggest (real-time) jak i przez
 * `findAndNotifyMatches()` — asynchroniczne powiadomienia po dodaniu osoby.
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

            // Filtry wg kontekstu
            if ($name === 'local' && !$context->includeLocal) {
                continue;
            }
            if ($name === 'cross_tree' && !$context->includeCrossTree) {
                continue;
            }
            if (!in_array($name, ['local', 'cross_tree'], true) && !$context->includeExternal) {
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
                'local'      => 'local',
                'cross_tree' => 'crossTree',
                default      => 'external',
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

        $crossTreeSource = $this->registry->get('cross_tree');
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

        $tree = $this->treeRepo->findById($person->treeId);
        if ($tree === null) {
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

        // Jedno powiadomienie na osobę — nie spamujemy
        // (dedup będzie sprawdzany przez NotificationRepository::existsRecentForPerson — patrz important #3)
        if ($persistedCount > 0) {
            $this->notifications->notifyPersonMatch(
                treeOwnerId: $tree->ownerId,
                personName:  trim($person->firstName . ' ' . $person->lastName),
                treeId:      $person->treeId,
                personId:    $person->id,
            );
        }
    }
}
