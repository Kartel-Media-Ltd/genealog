<?php
declare(strict_types=1);

/**
 * CLI script — retroaktywnie wypełnia `global_person_index` dla wszystkich
 * istniejących osób spełniających reguły RODO. Uruchom raz po migracji 008.
 *
 * Użycie: php bin/reindex-all.php
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/config/config.php';

use App\Core\Database;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;
use App\Services\Discovery\FingerprintService;
use App\Services\Discovery\GlobalIndexService;

$db          = Database::getInstance();
$personRepo  = new PersonRepository($db);
$treeRepo    = new TreeRepository($db);
$fingerprint = new FingerprintService();
$indexSvc    = new GlobalIndexService($db, $fingerprint, $personRepo, $treeRepo);

echo "Reindexing all persons: filling fingerprint_hash + name_soundex, plus global_person_index for opt-in trees...\n";

$trees   = $db->fetchAll('SELECT id FROM trees');
$treeIds = array_map(static fn(array $row): string => $row['id'], $trees);

if (empty($treeIds)) {
    echo "No trees found. Nothing to do.\n";
    exit(0);
}

$totalPersons = 0;

foreach ($treeIds as $treeId) {
    $persons = $personRepo->findByTree($treeId);
    foreach ($persons as $person) {
        // indexPerson() wewnętrznie wywołuje syncPersonFingerprint() oraz
        // — gdy osoba spełnia reguły RODO — INSERT...ON DUPLICATE KEY UPDATE
        // do global_person_index. Jedno wywołanie załatwia obie rzeczy.
        $indexSvc->indexPerson($person);
        $totalPersons++;
    }
}

// Zliczamy finalny stan globalnego indeksu jednym zapytaniem (zamiast 2×N per osoba)
$globalCount = (int)($db->fetchOne('SELECT COUNT(*) AS c FROM global_person_index')['c'] ?? 0);

echo sprintf(
    "Processed %d persons from %d trees.\n  global_person_index now has %d entries.\n",
    $totalPersons, count($treeIds), $globalCount
);
