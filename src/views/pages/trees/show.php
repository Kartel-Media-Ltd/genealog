<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\Tree $tree */
/** @var string|null $userRole */
$userRole ??= null;
$canEdit = in_array($userRole, ['owner', 'editor'], true);

require_once __DIR__ . '/../../atoms/icon.php';
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
    <?php render_icon('chevron-right', 'solid', 'h-3 w-3') ?>
    <span class="text-foreground font-medium"><?= htmlspecialchars($tree->name) ?></span>
</nav>

<!-- Nagłówek drzewa -->
<div class="mb-4">
    <div class="flex items-center gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-foreground">
            <?= htmlspecialchars($tree->name) ?>
        </h1>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
               <?= $tree->isPublic ? 'bg-green-100 text-green-800' : 'bg-muted text-muted-foreground' ?>">
            <?= $tree->isPublic ? 'Publiczne' : 'Prywatne' ?>
        </span>
    </div>
    <?php if ($tree->description): ?>
        <p class="mt-1 text-sm text-muted-foreground"><?= htmlspecialchars($tree->description) ?></p>
    <?php endif; ?>
    <p class="mt-1 text-xs text-muted-foreground">
        <?= $tree->personsCount ?> osób · Zaktualizowano <?= date('j M Y', strtotime($tree->updatedAt)) ?>
    </p>
</div>

<!--
  Toolbar drzewa — osobny wiersz pod nagłówkiem (full-width), wyrównany do prawej.
  Kolejność: Drukuj → Dodaj osobę → Osoby → Import/Export → Dostęp → Odkrywanie → Ustawienia
  Na ekranach <md (< 768px) pokazujemy tylko ikony (md:inline dla labeli).
-->
<div class="mb-6">
    <div class="flex flex-wrap justify-end gap-2">
        <?php if ($canEdit): ?>
            <!-- 1. Dodaj osobę (primary CTA) -->
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/new"
               title="Dodaj osobę"
               aria-label="Dodaj osobę"
               class="inline-flex h-10 w-10 md:w-auto items-center justify-center md:justify-start gap-2 rounded-md md:px-4
                      text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <?php render_icon('user-plus', 'solid', 'h-4 w-4') ?>
                <span class="hidden md:inline">Dodaj osobę</span>
            </a>

            <!-- 2. Osoby (lista) -->
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons"
               title="Lista osób"
               aria-label="Lista osób"
               class="inline-flex h-10 w-10 md:w-auto items-center justify-center md:justify-start gap-2 rounded-md border border-input md:px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <?php render_icon('users', 'solid', 'h-4 w-4') ?>
                <span class="hidden md:inline">Osoby</span>
            </a>
        <?php endif; ?>

        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/print"
           target="_blank"
           rel="noopener noreferrer"
           title="Drukuj drzewo"
           aria-label="Drukuj drzewo"
           class="inline-flex h-10 w-10 md:w-auto items-center justify-center md:justify-start gap-2 rounded-md border border-input md:px-4
                  text-sm font-medium text-foreground hover:bg-accent transition-colors
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <?php render_icon('print', 'solid', 'h-4 w-4') ?>
            <span class="hidden md:inline">Drukuj drzewo</span>
        </a>

        <?php if ($canEdit): ?>

            <!-- 3. Import/Export (GEDCOM) -->
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/gedcom"
               title="Import / eksport GEDCOM"
               aria-label="Import / eksport GEDCOM"
               class="inline-flex h-10 w-10 md:w-auto items-center justify-center md:justify-start gap-2 rounded-md border border-input md:px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <?php render_icon('file-import', 'solid', 'h-4 w-4') ?>
                <span class="hidden md:inline">Import/Export</span>
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'owner'): ?>
            <!-- 4. Dostęp (members) -->
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/members"
               title="Zarządzaj dostępem"
               aria-label="Zarządzaj dostępem"
               class="inline-flex h-10 w-10 md:w-auto items-center justify-center md:justify-start gap-2 rounded-md border border-input md:px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <?php render_icon('lock', 'solid', 'h-4 w-4') ?>
                <span class="hidden md:inline">Dostęp</span>
            </a>

            <!-- 5. Odkrywanie (cross-tree discovery) -->
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/settings/discovery"
               title="Odkrywanie (cross-tree)"
               aria-label="Odkrywanie (cross-tree)"
               class="inline-flex h-10 w-10 md:w-auto items-center justify-center md:justify-start gap-2 rounded-md border border-input md:px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <?php render_icon('share', 'solid', 'h-4 w-4') ?>
                <span class="hidden md:inline">Udostępnianie</span>
            </a>
        <?php endif; ?>

        <?php if ($canEdit): ?>
            <!-- 6. Ustawienia -->
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/edit"
               title="Ustawienia drzewa"
               aria-label="Ustawienia drzewa"
               class="inline-flex h-10 w-10 md:w-auto items-center justify-center md:justify-start gap-2 rounded-md border border-input md:px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <?php render_icon('gear', 'solid', 'h-4 w-4') ?>
                
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Placeholder drzewa D3 -->
<div x-data="{ expanded: false }"
     @keydown.escape.window="expanded = false"
     :class="expanded ? 'fixed inset-0 z-50 flex flex-col bg-background rounded-none border-0 shadow-none' : 'rounded-lg border border-border bg-card shadow-sm'">
    <div class="border-b border-border px-6 py-4 flex items-center justify-between"
         :class="expanded ? 'shrink-0' : ''">
        <h2 class="text-base font-semibold text-card-foreground">Wizualizacja drzewa</h2>
        <div class="flex gap-1">
            <button type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input
                           px-3 text-xs font-medium text-foreground hover:bg-accent transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    title="Powiększ" id="tree-zoom-in">
                <?php render_icon('magnifying-glass-plus', 'solid', 'h-3.5 w-3.5') ?>
            </button>
            <button type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input
                           px-3 text-xs font-medium text-foreground hover:bg-accent transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    title="Pomniejsz" id="tree-zoom-out">
                <?php render_icon('magnifying-glass-minus', 'solid', 'h-3.5 w-3.5') ?>
            </button>
            <!-- Expand / collapse -->
            <button type="button"
                    @click="expanded = !expanded"
                    :title="expanded ? 'Zamknij pełny ekran' : 'Pełny ekran'"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input
                           px-3 text-xs font-medium text-foreground hover:bg-accent transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <span x-show="!expanded" aria-hidden="true"><?php render_icon('expand', 'solid', 'h-3.5 w-3.5') ?></span>
                <span x-show="expanded" x-cloak aria-hidden="true"><?php render_icon('compress', 'solid', 'h-3.5 w-3.5') ?></span>
                <span x-text="expanded ? 'Zamknij' : 'Pełny ekran'" class="hidden sm:inline"></span>
            </button>
        </div>
    </div>

    <?php if ($tree->personsCount === 0): ?>
        <!-- Empty tree -->
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                <?php render_icon('users', 'solid', 'h-8 w-8 text-muted-foreground') ?>
            </div>
            <h3 class="mb-2 text-sm font-medium text-foreground">Drzewo jest puste</h3>
            <p class="mb-6 max-w-xs text-sm text-muted-foreground">
                Dodaj pierwszą osobę i zacznij budować swoje drzewo genealogiczne.
            </p>
            <?php if ($canEdit): ?>
                <div class="flex flex-col items-center gap-2 sm:flex-row">
                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/new"
                       class="inline-flex h-10 items-center gap-2 rounded-md px-4 text-sm font-medium
                              bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <?php render_icon('user-plus', 'solid', 'h-4 w-4') ?>
                        Dodaj pierwszą osobę
                    </a>
                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/gedcom"
                       class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                              text-sm font-medium text-foreground hover:bg-accent transition-colors
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <?php render_icon('file-import', 'solid', 'h-4 w-4') ?>
                        Importuj z pliku .ged
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div id="tree-canvas"
             :class="expanded ? 'relative flex-1 w-full overflow-hidden bg-muted/30' : 'relative h-[600px] w-full overflow-hidden bg-muted/30'"
             data-tree-id="<?= htmlspecialchars($tree->id) ?>"
             aria-label="Wizualizacja drzewa genealogicznego">
            <div class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                <?php render_icon('spinner', 'solid', 'fa-spin mr-2 h-4 w-4') ?>
                Ładowanie drzewa...
            </div>
        </div>
        <script src="/vendor/d3.min.js" defer></script>
        <script src="/js/tree-visualizer.js" defer></script>
    <?php endif; ?>
</div>
