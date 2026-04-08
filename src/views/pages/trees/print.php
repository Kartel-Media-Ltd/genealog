<?php
declare(strict_types=1);
/** @var \App\Models\Tree $tree */
/** @var string $treeId */
require_once __DIR__ . '/../../atoms/icon.php';
?>
<div class="flex h-screen flex-col">
    <!-- Pasek narzędziowy (ukryty przy druku) -->
    <div class="no-print flex shrink-0 items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2">
        <span class="mr-2 text-sm font-medium text-gray-700">
            <?= htmlspecialchars($tree->name) ?>
        </span>
        <!-- Radio orientacja — ustawia inline @page przy zmianie -->
        <div class="ml-2 flex items-center gap-2 text-xs text-gray-600">
            <span>Format:</span>
            <label class="flex items-center gap-1 cursor-pointer">
                <input type="radio" name="orientation" value="A3 landscape" checked
                       onchange="document.getElementById('print-page-style').textContent = '@page { size: A3 landscape; margin: 10mm; }'">
                A3 ↔
            </label>
            <label class="flex items-center gap-1 cursor-pointer">
                <input type="radio" name="orientation" value="A4 landscape"
                       onchange="document.getElementById('print-page-style').textContent = '@page { size: A4 landscape; margin: 10mm; }'">
                A4 ↔
            </label>
            <label class="flex items-center gap-1 cursor-pointer">
                <input type="radio" name="orientation" value="A3 portrait"
                       onchange="document.getElementById('print-page-style').textContent = '@page { size: A3 portrait; margin: 15mm; }'">
                A3 ↕
            </label>
        </div>
        <style id="print-page-style"></style>

        <div class="ml-auto flex gap-2">
            <button
                type="button"
                id="btn-print"
                class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                <?php render_icon('print', 'solid', 'h-4 w-4') ?>
                Drukuj
            </button>
            <button
                type="button"
                id="btn-export-png"
                disabled
                data-filename="drzewo-<?= htmlspecialchars($treeId) ?>.png"
                class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                <?php render_icon('download', 'solid', 'h-4 w-4') ?>
                Pobierz PNG
            </button>
            <a href="/trees/<?= htmlspecialchars($treeId) ?>"
               class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors">
                <?php render_icon('xmark', 'solid', 'h-4 w-4') ?>
                Zamknij
            </a>
        </div>
    </div>

    <!-- Kontener drzewa D3 — flex-1 wypełnia resztę viewportu (zamiast magic 48px) -->
    <div id="tree-canvas"
         class="relative flex-1 w-full overflow-hidden"
         data-tree-id="<?= htmlspecialchars($treeId) ?>"
         aria-label="Wizualizacja drzewa genealogicznego">
        <div class="absolute inset-0 flex items-center justify-center text-sm text-gray-500">
            Ładowanie drzewa…
        </div>
    </div>
</div>

<script src="/vendor/d3.min.js" defer></script>
<script src="/js/tree-visualizer.js" defer></script>
<script src="/js/print-helper.js" defer></script>
