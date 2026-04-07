<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Załaduj konfigurację testową
$envFile = file_exists(dirname(__DIR__) . '/.env.test')
    ? '.env.test'
    : '.env.local';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__), $envFile);
$dotenv->safeLoad();

// Stałe wymagane przez core — minimalne potrzeby dla unit testów
if (!defined('BCRYPT_COST'))        define('BCRYPT_COST', 4); // niski koszt w testach
if (!defined('RATE_LIMIT_ATTEMPTS')) define('RATE_LIMIT_ATTEMPTS', 5);
if (!defined('RATE_LIMIT_WINDOW'))   define('RATE_LIMIT_WINDOW', 900);
if (!defined('ROOT_PATH'))          define('ROOT_PATH', dirname(__DIR__));
if (!defined('SRC_PATH'))           define('SRC_PATH', ROOT_PATH . '/src');
if (!defined('VIEWS_PATH'))         define('VIEWS_PATH', SRC_PATH . '/views');
if (!defined('STORAGE_PATH'))       define('STORAGE_PATH', ROOT_PATH . '/storage');
if (!defined('SITE_URL'))           define('SITE_URL', 'http://localhost:8002');
if (!defined('APP_ENV'))            define('APP_ENV', 'test');
if (!defined('APP_DEBUG'))          define('APP_DEBUG', true);
if (!defined('SESSION_NAME'))       define('SESSION_NAME', 'genealog_sess_test');
if (!defined('SESSION_LIFETIME'))   define('SESSION_LIFETIME', 7200);
if (!defined('SESSION_ABSOLUTE'))   define('SESSION_ABSOLUTE', 28800);
