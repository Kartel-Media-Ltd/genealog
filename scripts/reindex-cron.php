#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Cron script — przetwarza kolejkę reindeksowania Redis (Discovery I7).
 *
 * Dodaj do crontab (cPanel / SSH):
 *   crontab -e
 *
 *   * /5 * * * * php /sciezka/do/genealog/scripts/reindex-cron.php >> /sciezka/do/genealog/storage/logs/reindex-cron.log 2>&1
 *
 * Zamień /sciezka/do/genealog/ na absolutną ścieżkę do projektu na serwerze.
 * Zalecana częstotliwość: co 5 minut.
 *
 * Jeśli Redis nie jest skonfigurowany w .env.local → skrypt kończy się cicho (exit 0).
 * Jeśli Redis jest skonfigurowany → przetwarza wszystkie aktualnie oczekujące joby.
 *
 * Zabezpieczenia:
 *   - Lock file → zapobiega równoczesnemu uruchomieniu dwóch instancji crona
 *   - Limit czasu → max 4.5 min (cron co 5 min = bezpieczny margines)
 *   - rPop (nieblokujące) → kończy się gdy kolejka pusta
 */

chdir(dirname(__DIR__));
require_once 'vendor/autoload.php';
require_once 'config/config.php';

use App\Core\Database;
use App\Services\RedisService;
use App\Services\Discovery\FingerprintService;
use App\Services\Discovery\GlobalIndexService;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;
use App\Repositories\UserRepository;

// --- Lock file — chroni przed równoległymi uruchomieniami przez cron ---
$lockFile = defined('ROOT_PATH')
    ? ROOT_PATH . '/storage/reindex-cron.lock'
    : dirname(__DIR__) . '/storage/reindex-cron.lock';

$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    // Poprzednia instancja nadal działa — wyjdź cicho
    exit(0);
}

// --- Limit czasu: 270s (4.5 min) — cron co 5 min = bezpieczny margines ---
$maxSeconds = 270;
set_time_limit($maxSeconds + 30);
$startedAt = time();

// --- Połączenie Redis ---
$redis = RedisService::connect();
if ($redis === null) {
    // Redis niezdefiniowany w .env.local — nic do robienia
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

// --- Bootstrap serwisów ---
$db             = Database::getInstance();
$userRepo       = new UserRepository($db);
$treeRepo       = new TreeRepository($db);
$personRepo     = new PersonRepository($db);
$fingerprintSvc = new FingerprintService();
$globalIndexSvc = new GlobalIndexService($db, $fingerprintSvc, $personRepo, $treeRepo, $userRepo);

$queue     = 'genealog:reindex_queue';
$processed = 0;
$errors    = 0;

// --- Pętla przetwarzania ---
while ((time() - $startedAt) < $maxSeconds) {
    // rPop: nieblokujące — natychmiast zwraca null gdy kolejka pusta
    $item = $redis->rPop($queue);
    if ($item === false || $item === null) {
        break; // kolejka pusta — koniec pracy
    }

    $job = json_decode((string) $item, true);
    if (!is_array($job) || empty($job['treeId'])) {
        error_log('[reindex-cron] Niepoprawny job (pomijam): ' . $item);
        $errors++;
        continue;
    }

    $treeId = (string) $job['treeId'];
    try {
        $globalIndexSvc->reindexTree($treeId);
        $processed++;
    } catch (\Throwable $e) {
        error_log(sprintf('[reindex-cron] reindexTree nieudany treeId=%s: %s', $treeId, $e->getMessage()));
        $errors++;
    }
}

// --- Podsumowanie (trafia do logu crona) ---
if ($processed > 0 || $errors > 0) {
    $elapsed = time() - $startedAt;
    echo sprintf(
        '[%s] reindex-cron: %d jobów wykonanych, %d błędów, czas: %ds' . PHP_EOL,
        date('Y-m-d H:i:s'),
        $processed,
        $errors,
        $elapsed
    );
}

// --- Zwolnij lock ---
flock($lock, LOCK_UN);
fclose($lock);
exit(0);
