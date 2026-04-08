<?php
declare(strict_types=1);
use App\Core\Session;
use App\Core\Csrf;

$flash = Session::getFlash();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

require_once __DIR__ . '/../atoms/icon.php';
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Admin') ?> — Genealog Admin</title>
    <link rel="icon" href="data:,">
    <script src="/vendor/tailwind.js"></script>
    <!-- Font Awesome 6 Free (lokalny vendor) -->
    <link rel="preload" href="/vendor/fontawesome/webfonts/fa-solid-900.woff2"
          as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
    <style>
        :root {
            --background: 0 0% 100%; --foreground: 240 10% 3.9%;
            --card: 0 0% 100%; --card-foreground: 240 10% 3.9%;
            --primary: 240 5.9% 10%; --primary-foreground: 0 0% 98%;
            --muted: 240 4.8% 95.9%; --muted-foreground: 240 3.8% 46.1%;
            --border: 240 5.9% 90%; --input: 240 5.9% 90%;
            --ring: 240 5.9% 10%;
            --destructive: 0 84.2% 60.2%; --destructive-foreground: 0 0% 98%;
            --accent: 240 4.8% 95.9%; --accent-foreground: 240 5.9% 10%;
        }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="fixed inset-y-0 left-0 z-40 w-56 bg-slate-900 flex flex-col">
        <!-- Logo -->
        <div class="flex items-center gap-2 px-4 py-4 border-b border-slate-700">
            <div class="h-7 w-7 rounded bg-white/10 flex items-center justify-center text-white">
                <?php render_icon('tree', 'solid', 'h-4 w-4') ?>
            </div>
            <div>
                <p class="text-white text-sm font-semibold leading-none">Genealog</p>
                <p class="text-slate-400 text-xs mt-0.5">Panel Admina</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-4 space-y-1">
            <a href="/admin" class="<?= $currentPath === '/admin' ? 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium bg-white/10 text-white' : 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white transition-colors' ?>">
                <?php render_icon('gauge-high', 'solid', 'fa-fw h-4 w-4') ?>
                Dashboard
            </a>
            <a href="/admin/users" class="<?= str_starts_with($currentPath, '/admin/users') ? 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium bg-white/10 text-white' : 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white transition-colors' ?>">
                <?php render_icon('users', 'solid', 'fa-fw h-4 w-4') ?>
                Użytkownicy
            </a>
            <a href="/admin/trees" class="<?= str_starts_with($currentPath, '/admin/trees') ? 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium bg-white/10 text-white' : 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white transition-colors' ?>">
                <?php render_icon('tree', 'solid', 'fa-fw h-4 w-4') ?>
                Drzewa
            </a>
            <a href="/admin/logs" class="<?= str_starts_with($currentPath, '/admin/logs') ? 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium bg-white/10 text-white' : 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white transition-colors' ?>">
                <?php render_icon('list-ul', 'solid', 'fa-fw h-4 w-4') ?>
                Logi
            </a>
        </nav>

        <!-- Bottom: back to app -->
        <div class="px-3 py-4 border-t border-slate-700">
            <a href="/dashboard"
               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
                <?php render_icon('chevron-left', 'solid', 'h-4 w-4') ?>
                Wróć do aplikacji
            </a>
            <p class="mt-2 px-3 text-xs text-slate-500 truncate"><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
        </div>
    </aside>

    <!-- Main -->
    <div class="ml-56 flex-1 flex flex-col min-h-screen">

        <!-- Topbar -->
        <header class="sticky top-0 z-30 bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
            <h1 class="text-base font-semibold text-slate-900"><?= htmlspecialchars($title ?? '') ?></h1>
            <span class="text-sm text-slate-500"><?= htmlspecialchars($currentUser['name'] ?? '') ?></span>
        </header>

        <!-- Content -->
        <main class="flex-1 p-6">

            <!-- Flash messages — Session::getFlash() zwraca tablicę ['type' => '…', 'message' => '…'] -->
            <?php if (!empty($flash['type']) && !empty($flash['message'])):
                $cls = match($flash['type']) {
                    'success' => 'bg-green-50 border-green-200 text-green-800',
                    'error'   => 'bg-red-50 border-red-200 text-red-800',
                    default   => 'bg-blue-50 border-blue-200 text-blue-800',
                };
            ?>
                <div class="mb-4 rounded-md border px-4 py-3 text-sm <?= $cls ?>" role="alert">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
