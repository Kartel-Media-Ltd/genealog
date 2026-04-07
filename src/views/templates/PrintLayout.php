<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:,">
    <title><?= htmlspecialchars($pageTitle ?? 'Druk — Genealog') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/globals.css">
    <style>
        @page {
            size: A3 landscape;
            margin: 10mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0; padding: 0; }
            .print-full { width: 100% !important; height: auto !important; }
            #tree-canvas, #tree-canvas svg { width: 100% !important; height: auto !important; }
            .print-table { width: 100%; border-collapse: collapse; }
            .print-table th,
            .print-table td { border: 1px solid #ccc; padding: 3px 6px; font-size: 10pt; }
            .print-table thead { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; }
            .print-table tr { page-break-inside: avoid; }
            footer.no-screen { display: block !important; }
        }
        footer.no-screen { display: none; }
    </style>
</head>
<body class="bg-white text-gray-900">
    <?= $content ?? '' ?>
</body>
</html>
