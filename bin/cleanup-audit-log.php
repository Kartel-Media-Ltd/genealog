<?php
declare(strict_types=1);

/**
 * RODO Art. 5(1)(e) — ograniczenie przechowywania.
 * Cron: kasuje wpisy z `source_audit_log` starsze niż 3 lata.
 *
 * Uruchomić ręcznie: php bin/cleanup-audit-log.php
 * Cron przykład:    0 3 * * * /usr/bin/php /path/to/genealog/bin/cleanup-audit-log.php
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/config/config.php';

use App\Core\Database;

$db = Database::getInstance();

$retentionYears = 3;
$cutoffDate     = date('Y-m-d H:i:s', strtotime("-{$retentionYears} years"));

echo "Cleanup audit log: deleting entries older than {$cutoffDate}...\n";

$db->execute(
    'DELETE FROM source_audit_log WHERE created_at < ?',
    [$cutoffDate]
);

$rowCount = $db->fetchOne(
    'SELECT COUNT(*) AS c FROM source_audit_log'
)['c'] ?? 0;

echo "Cleanup complete. Remaining entries: {$rowCount}\n";
// Cleanup wygasłych password_resets — przeniesione do bin/cleanup-password-resets.php (ZAD-2.4)
