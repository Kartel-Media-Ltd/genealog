<?php
declare(strict_types=1);

/**
 * Bezpieczeństwo — cleanup wygasłych tokenów resetu hasła.
 * Cron: kasuje wpisy z `password_resets` starsze niż 24h od `expires_at`.
 *
 * Uruchomić ręcznie: php bin/cleanup-password-resets.php
 * Cron przykład:    0 4 * * * /usr/bin/php /path/to/genealog/bin/cleanup-password-resets.php
 *
 * Dlaczego? Wygasłe tokeny same w sobie nie są groźne (TTL sprawdzany przy
 * weryfikacji), ale zalegają w DB i ułatwiają potencjalne ataki bruteforce
 * po hasłowym hash'u jeśli baza wycieknie.
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/config/config.php';

use App\Core\Database;

$db = Database::getInstance();

$beforeCount = (int)($db->fetchOne('SELECT COUNT(*) AS c FROM password_resets')['c'] ?? 0);

echo "Cleanup password_resets: deleting tokens expired more than 24h ago...\n";
echo "Tokens before: {$beforeCount}\n";

$db->execute(
    'DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
);

$afterCount = (int)($db->fetchOne('SELECT COUNT(*) AS c FROM password_resets')['c'] ?? 0);

echo "Tokens after: {$afterCount}\n";
echo "Deleted: " . ($beforeCount - $afterCount) . "\n";
