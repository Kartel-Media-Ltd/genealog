#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Worker kolejki reindeksowania Redis — Discovery (I7).
 *
 * Uruchomienie jednorazowe:
 *   php bin/reindex-worker.php
 *
 * Uruchomienie jako daemon (supervisor/nohup):
 *   nohup php bin/reindex-worker.php >> storage/logs/reindex-worker.log 2>&1 &
 *
 * Wymaga:
 *   - PHP ext-redis
 *   - REDIS_HOST lub REDIS_SOCKET zdefiniowane w .env.local
 *
 * Kolejka: genealog:reindex_queue (Redis List, LPUSH/BRPOP)
 * Format job: {"treeId": "...", "queued_at": 1234567890}
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

$redis = RedisService::connect();
if ($redis === null) {
    fwrite(STDERR, "[ReindexWorker] Błąd: Redis niezdefiniowany.\n");
    fwrite(STDERR, "  Ustaw REDIS_HOST (+ opcjonalnie REDIS_PORT) lub REDIS_SOCKET w .env.local.\n");
    exit(1);
}

$db             = Database::getInstance();
$userRepo       = new UserRepository($db);
$treeRepo       = new TreeRepository($db);
$personRepo     = new PersonRepository($db);
$fingerprintSvc = new FingerprintService();
$globalIndexSvc = new GlobalIndexService($db, $fingerprintSvc, $personRepo, $treeRepo, $userRepo);

$queue = 'genealog:reindex_queue';

echo "[ReindexWorker] Uruchomiony. Kolejka: {$queue}\n";
echo "[ReindexWorker] Oczekuję na zadania (CTRL+C aby zatrzymać)...\n";

while (true) {
    // BRPOP blokuje do 5 sekund — pozwala na czyste zamknięcie przez SIGTERM
    $item = $redis->brPop([$queue], 5);
    if ($item === null || $item === false) {
        continue;
    }

    $raw = (string) $item[1];
    $job = json_decode($raw, true);

    if (!is_array($job) || empty($job['treeId'])) {
        error_log("[ReindexWorker] Niepoprawne zadanie (pomijam): {$raw}");
        continue;
    }

    $treeId = (string) $job['treeId'];
    $age    = isset($job['queued_at']) ? (time() - (int) $job['queued_at']) . 's w kolejce' : '';

    echo "[ReindexWorker] Reindeksuję drzewo {$treeId} {$age}...\n";

    try {
        $globalIndexSvc->reindexTree($treeId);
        echo "[ReindexWorker] Gotowe: {$treeId}\n";
    } catch (\Throwable $e) {
        error_log(sprintf(
            '[ReindexWorker] reindexTree nieudany treeId=%s: %s',
            $treeId,
            $e->getMessage()
        ));
    }
}
