<?php
declare(strict_types=1);
/** @var \App\Models\Tree $tree */
/** @var string $treeId */
?>
<!-- Pasek narzędziowy (ukryty przy druku) -->
<div class="no-print flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2">
    <span class="mr-2 text-sm font-medium text-gray-700">
        <?= htmlspecialchars($tree->name) ?>
    </span>
    <div class="ml-auto flex gap-2">
        <button
            onclick="window.print()"
            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Drukuj
        </button>
        <button
            onclick="exportSvgAsPng(document.querySelector('#tree-canvas svg'), 'drzewo-<?= htmlspecialchars($treeId) ?>.png')"
            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Pobierz PNG
        </button>
        <a href="/trees/<?= htmlspecialchars($treeId) ?>"
           class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            Zamknij
        </a>
    </div>
</div>

<!-- Kontener drzewa D3 -->
<div id="tree-canvas"
     style="width:100%; height:calc(100vh - 48px);"
     data-tree-id="<?= htmlspecialchars($treeId) ?>"
     aria-label="Wizualizacja drzewa genealogicznego">
    <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:0.875rem;color:#6b7280;">
        Ładowanie drzewa…
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js" defer></script>
<script src="/js/tree-visualizer.js" defer></script>
<script src="/js/print-helper.js" defer></script>
