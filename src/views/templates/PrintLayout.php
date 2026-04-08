<?php
declare(strict_types=1);
use App\Core\PageSize;
/** @var string|null $pageTitle */
/** @var string|null $pageSize    e.g. 'A3 landscape' or 'A4 portrait' */
/** @var string $content */

// Sanityzacja rozmiaru strony — single source of truth w App\Core\PageSize
$pageSize   = PageSize::sanitize($pageSize ?? null);
$pageMargin = PageSize::defaultMargin($pageSize);
$pageTitle  = $pageTitle ?? 'Druk — Genealog';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:,">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="/vendor/tailwind.js"></script>
    <link rel="stylesheet" href="/css/globals.css">
    <!-- Font Awesome 6 Free (lokalny vendor) -->
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
    <style>
        /* Per-page size — wartość z whitelist, pozostałe reguły @media print w globals.css */
        @page {
            size: <?= $pageSize ?>;
            margin: <?= $pageMargin ?>;
        }
    </style>
</head>
<body class="bg-white text-gray-900">
    <?= $content ?? '' ?>
</body>
</html>
