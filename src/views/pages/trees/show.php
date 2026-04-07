<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\Tree $tree */
/** @var string|null $userRole */
$userRole ??= null;
$canEdit = in_array($userRole, ['owner', 'editor'], true);
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span class="text-foreground font-medium"><?= htmlspecialchars($tree->name) ?></span>
</nav>

<!-- Nagłówek drzewa -->
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
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
    <div class="flex gap-2">
        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/print"
           target="_blank"
           class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                  text-sm font-medium text-foreground hover:bg-accent transition-colors
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Drukuj drzewo
        </a>
        <?php if ($canEdit): ?>
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/gedcom"
               title="Import / eksport GEDCOM"
               class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-input
                      text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="12" x2="12" y2="18"/>
                    <line x1="9" y1="15" x2="15" y2="15"/>
                </svg>
                <span class="sr-only">GEDCOM</span>
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'owner'): ?>
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/members"
               class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Dostęp
            </a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/new"
               class="inline-flex h-10 items-center gap-2 rounded-md px-4 text-sm font-medium
                      bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Dodaj osobę
            </a>
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons"
               class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                Osoby
            </a>
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/edit"
               class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                Ustawienia
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
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    <line x1="11" y1="8" x2="11" y2="14"/>
                    <line x1="8" y1="11" x2="14" y2="11"/>
                </svg>
            </button>
            <button type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input
                           px-3 text-xs font-medium text-foreground hover:bg-accent transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    title="Pomniejsz" id="tree-zoom-out">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    <line x1="8" y1="11" x2="14" y2="11"/>
                </svg>
            </button>
            <!-- Expand / collapse -->
            <button type="button"
                    @click="expanded = !expanded"
                    :title="expanded ? 'Zamknij pełny ekran' : 'Pełny ekran'"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input
                           px-3 text-xs font-medium text-foreground hover:bg-accent transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <!-- Expand icon -->
                <svg x-show="!expanded" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="15 3 21 3 21 9"/>
                    <polyline points="9 21 3 21 3 15"/>
                    <line x1="21" y1="3" x2="14" y2="10"/>
                    <line x1="3" y1="21" x2="10" y2="14"/>
                </svg>
                <!-- Collapse icon -->
                <svg x-show="expanded" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="4 14 10 14 10 20"/>
                    <polyline points="20 10 14 10 14 4"/>
                    <line x1="10" y1="14" x2="3" y2="21"/>
                    <line x1="21" y1="3" x2="14" y2="10"/>
                </svg>
                <span x-text="expanded ? 'Zamknij' : 'Pełny ekran'" class="hidden sm:inline"></span>
            </button>
        </div>
    </div>

    <?php if ($tree->personsCount === 0): ?>
        <!-- Empty tree -->
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                <svg class="h-8 w-8 text-muted-foreground" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Dodaj pierwszą osobę
                    </a>
                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/gedcom"
                       class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                              text-sm font-medium text-foreground hover:bg-accent transition-colors
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="12" x2="12" y2="18"/>
                            <line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
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
                <svg class="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Ładowanie drzewa...
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js" defer></script>
        <script src="/js/tree-visualizer.js" defer></script>
    <?php endif; ?>
</div>
