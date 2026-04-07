<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';

use App\Core\Session;
use App\Core\Database;
use App\Repositories\TreeRepository;

Session::start();

// Must be logged in
if (!Session::get('user_id')) {
    http_response_code(401);
    exit('401 Unauthorized');
}

$pathParam = $_GET['path'] ?? '';

// Sanitize: only allow alphanumeric, hyphens, underscores, dots, forward slashes
// Format: {tree_id}/{uuid}.webp or {tree_id}/{uuid}_thumb.webp
if (!preg_match('#^[a-f0-9\-]{36}/[a-f0-9_]+\.webp$#', $pathParam)) {
    http_response_code(400);
    exit('400 Bad Request');
}

// Extract tree_id from path
$parts  = explode('/', $pathParam, 2);
$treeId = $parts[0];

// Verify access: user must be owner or member of that tree
$db       = Database::getInstance();
$treeRepo = new TreeRepository($db);
$userId   = Session::get('user_id');
$role     = $treeRepo->getUserRole($treeId, $userId);

if (!in_array($role, ['owner', 'editor', 'viewer'], true)) {
    // Also allow if tree is public
    $tree = $treeRepo->findById($treeId);
    if ($tree === null || !$tree->isPublic) {
        http_response_code(403);
        exit('403 Forbidden');
    }
}

$filePath = STORAGE_PATH . '/media/' . $pathParam;

// Path traversal check
$realBase = realpath(STORAGE_PATH . '/media');
$realFile = realpath($filePath);

if ($realFile === false || $realBase === false || !str_starts_with($realFile, $realBase)) {
    http_response_code(403);
    exit('403 Forbidden');
}

if (!is_file($realFile)) {
    http_response_code(404);
    exit('404 Not Found');
}

header('Content-Type: image/webp');
header('Cache-Control: private, max-age=86400');
header('Content-Length: ' . filesize($realFile));
readfile($realFile);
exit;
