<?php
declare(strict_types=1);
/** @var string|null $pageTitle */
/** @var string|null $pageSize    e.g. 'A3 landscape' or 'A4 portrait' */
/** @var string $content */

// Whitelist dozwolonych rozmiarów strony — chroni przed CSS injection
// gdyby kiedyś $pageSize trafił z user input zamiast hardcoded controllera.
$allowedPageSizes = ['A3 landscape', 'A3 portrait', 'A4 landscape', 'A4 portrait'];
$pageSize = (isset($pageSize) && in_array($pageSize, $allowedPageSizes, true))
    ? $pageSize
    : 'A3 landscape';

$pageTitle  = $pageTitle ?? 'Druk — Genealog';
$pageMargin = ($pageSize === 'A4 portrait' || $pageSize === 'A3 portrait') ? '15mm' : '10mm';
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
