<?php
declare(strict_types=1);

namespace App\Services\Discovery;

use App\Core\Database;
use App\Core\Uuid;
use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;

/**
 * Zarządza wpisami w `global_person_index` — anonimowym indeksie osób
 * historycznych do cross-tree matchingu.
 *
 * RODO compliance (egzekwowane TUTAJ):
 *   1. `is_living = 0` — żyjące osoby NIGDY nie trafiają do indeksu
 *   2. `visibility != 'private'`
 *   3. `trees.is_indexed_globally = 1` — user musi jawnie włączyć (DEFAULT 0)
 *   4. `users.discovery_opt_in = 1` — opt-in na poziomie konta (DEFAULT 0)
 *   5. birth_year IS NULL OR birth_year < NOW()-100 (zasada 100 lat)
 *
 * Naruszenie którejkolwiek reguły → `indexPerson()` staje się no-op +
 * jeśli istnieje stary wpis, zostaje usunięty.
 */
final class GlobalIndexService
{
    public function __construct(
        private readonly Database           $db,
        private readonly FingerprintService $fingerprint,
        private readonly PersonRepository   $personRepo,
        private readonly TreeRepository     $treeRepo,
        private readonly ?\App\Repositories\UserRepository $userRepo = null,
    ) {}

    /**
     * Policz i zapisz fingerprint_hash + name_soundex do persons.
     * Używane NIEZALEŻNIE od RODO — lokalny index jest zawsze potrzebny do
     * search w drzewach usera (nawet gdy owner nie opt-in do global indeksu).
     */
    public function syncPersonFingerprint(Person $person): void
    {
        $birthYear = $this->yearFromDate($person->birthDate);

        $hash = $this->fingerprint->compute(
            $person->firstName,
            $person->lastName,
            $birthYear,
            $person->birthPlace,
        );
        $soundex = $this->fingerprint->computeSoundex($person->firstName, $person->lastName);

        $this->personRepo->updateFingerprint($person->id, $hash, $soundex);
    }

    /**
     * Dodaj lub zaktualizuj wpis w global_person_index dla danej osoby.
     * No-op jeśli osoba nie spełnia reguł RODO. Lokalny fingerprint w persons
     * zapisywany ZAWSZE (przez syncPersonFingerprint).
     *
     * B4: używamy `INSERT ... ON DUPLICATE KEY UPDATE` zamiast SELECT+INSERT/UPDATE
     * żeby uniknąć race condition przy równoczesnych wywołaniach (np. reindex-all
     * razem z EventDispatcher emit z nowego create). UNIQUE KEY uq_gpi_person
     * zapewnia atomowość.
     */
    public function indexPerson(Person $person): void
    {
        // Zawsze aktualizuj lokalny fingerprint (nie dotyka global_person_index)
        $this->syncPersonFingerprint($person);

        // Important #10: isEligible zwraca Tree (lub null) — eliminuje duplikat query.
        // Stara wersja robiła 2× SELECT trees na osobę (raz w isEligible, raz tutaj).
        $tree = $this->getEligibleTree($person);
        if ($tree === null) {
            // Jeśli wcześniej była indeksowana, a teraz już nie powinna być — usuń.
            $this->unindexPerson($person->id);
            return;
        }

        $birthYear = $this->yearFromDate($person->birthDate);

        $hash = $this->fingerprint->compute(
            $person->firstName,
            $person->lastName,
            $birthYear,
            $person->birthPlace,
        );
        if ($hash === null) {
            return;
        }

        $soundex = $this->fingerprint->computeSoundex($person->firstName, $person->lastName);
        $region  = $this->fingerprint->extractRegion($person->birthPlace);

        $gender = in_array($person->gender, ['male', 'female', 'unknown'], true)
            ? $person->gender
            : 'unknown';

        $this->db->execute(
            'INSERT INTO global_person_index
                (id, fingerprint_hash, name_soundex, first_name, last_name,
                 tree_id, person_id, owner_user_id, region,
                 earliest_birth_year, latest_birth_year, is_living, gender)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE
                fingerprint_hash    = VALUES(fingerprint_hash),
                name_soundex        = VALUES(name_soundex),
                first_name          = VALUES(first_name),
                last_name           = VALUES(last_name),
                tree_id             = VALUES(tree_id),
                owner_user_id       = VALUES(owner_user_id),
                region              = VALUES(region),
                earliest_birth_year = VALUES(earliest_birth_year),
                latest_birth_year   = VALUES(latest_birth_year),
                is_living           = 0,
                gender              = VALUES(gender)',
            [
                Uuid::generate(),
                $hash, $soundex, $person->firstName, $person->lastName,
                $person->treeId, $person->id, $tree->ownerId, $region,
                $birthYear, $birthYear, $gender,
            ]
        );
    }

    /**
     * Usuń wpis dla konkretnej osoby.
     */
    public function unindexPerson(string $personId): void
    {
        $this->db->execute(
            'DELETE FROM global_person_index WHERE person_id = ?',
            [$personId]
        );
    }

    /**
     * Usuń wszystkie wpisy dla danego drzewa (wywoływane gdy user wyłącza
     * is_indexed_globally lub usuwa drzewo).
     */
    public function unindexTree(string $treeId): void
    {
        $this->db->execute(
            'DELETE FROM global_person_index WHERE tree_id = ?',
            [$treeId]
        );
    }

    /**
     * Reindeksuj wszystkie osoby w drzewie — używane po włączeniu
     * is_indexed_globally lub po zmianie ustawień.
     *
     * ZAD-2.3 (P3): pobiera tree + opt-in RAZ przed pętlą (eliminacja N+1),
     * dodaje set_time_limit dla dużych drzew + chunkowanie z early exit.
     */
    public function reindexTree(string $treeId): void
    {
        @set_time_limit(300);

        // Pobierz tree i opt-in raz — były pobierane per każda osoba.
        $tree = $this->treeRepo->findById($treeId);
        if ($tree === null || !$tree->isIndexedGlobally) {
            // Drzewo usunięte lub wyłączone globally → unindex wszystko.
            $this->unindexTree($treeId);
            return;
        }

        // Sprawdź opt-in ownera raz.
        $optedIn = false;
        if ($this->userRepo !== null) {
            $optedIn = $this->userRepo->isDiscoveryOptedIn($tree->ownerId);
        } else {
            $owner = $this->db->fetchOne(
                'SELECT discovery_opt_in FROM users WHERE id = ?',
                [$tree->ownerId]
            );
            $optedIn = $owner !== null && (int)$owner['discovery_opt_in'] === 1;
        }

        if (!$optedIn) {
            // Opt-in cofnięty → usuń wszystkie wpisy drzewa
            $this->unindexTree($treeId);
            return;
        }

        $persons = $this->personRepo->findByTree($treeId);
        $chunkSize = 100;
        $chunks = array_chunk($persons, $chunkSize);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $person) {
                try {
                    // indexPerson nadal wywołuje getEligibleTree wewnętrznie — ale zyski
                    // z pre-checku powyżej są znaczące (early exit dla całego drzewa).
                    $this->indexPerson($person);
                } catch (\Throwable $e) {
                    error_log(sprintf(
                        '[GlobalIndexService] reindexTree: failed person %s: %s',
                        $person->id, $e->getMessage()
                    ));
                }
            }
        }
    }

    /**
     * Sprawdź czy osoba spełnia WSZYSTKIE reguły RODO do indeksowania.
     */
    /**
     * Zwraca Tree gdy osoba kwalifikuje się do globalnego indeksu, null inaczej.
     * Important #10: zastępuje `isEligible(): bool` żeby eliminować duplikat
     * SELECT trees w `indexPerson()`.
     */
    private function getEligibleTree(Person $person): ?\App\Models\Tree
    {
        // R1: żyjąca osoba NIGDY
        if ($person->isLiving) {
            return null;
        }

        // R2: visibility != 'private'
        if ($person->visibility === 'private') {
            return null;
        }

        // R3/R4: drzewo + owner opt-in
        $tree = $this->treeRepo->findById($person->treeId);
        if ($tree === null || !$tree->isIndexedGlobally) {
            return null;
        }

        // Important #4: dedykowana metoda UserRepository::isDiscoveryOptedIn
        if ($this->userRepo !== null) {
            if (!$this->userRepo->isDiscoveryOptedIn($tree->ownerId)) {
                return null;
            }
        } else {
            $owner = $this->db->fetchOne(
                'SELECT discovery_opt_in FROM users WHERE id = ?',
                [$tree->ownerId]
            );
            if (!$owner || (int)$owner['discovery_opt_in'] !== 1) {
                return null;
            }
        }

        // R5: zasada 100 lat (używa metody FingerprintService::isHistorical)
        $birthYear = $this->yearFromDate($person->birthDate);
        if (!$this->fingerprint->isHistorical($person->isLiving, $birthYear)) {
            return null;
        }

        return $tree;
    }

    private function yearFromDate(?string $date): ?int
    {
        if ($date === null || $date === '') {
            return null;
        }
        $year = (int)substr($date, 0, 4);
        return $year > 0 ? $year : null;
    }

}
