<?php
declare(strict_types=1);
/** @var string|null $pageTitle */
/** @var string|null $pageSize    e.g. 'A3 landscape' or 'A4 portrait' */
/** @var string $content */
$pageSize  = $pageSize  ?? 'A3 landscape';
$pageTitle = $pageTitle ?? 'Druk — Genealog';
$pageMargin = ($pageSize === 'A4 portrait') ? '15mm' : '10mm';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:,">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/globals.css">
    <style>
        /* Per-page size — pozostałe reguły @media print są w globals.css */
        @page {
            size: <?= htmlspecialchars($pageSize) ?>;
            margin: <?= $pageMargin ?>;
        }
    </style>
</head>
<body class="bg-white text-gray-900">
    <?= $content ?? '' ?>
</body>
</html>
