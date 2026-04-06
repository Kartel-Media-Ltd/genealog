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
    <?php if ($canEdit): ?>
        <div class="flex gap-2">
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
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/edit"
               class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                      text-sm font-medium text-foreground hover:bg-accent transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                Ustawienia
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Placeholder drzewa D3 -->
<div class="rounded-lg border border-border bg-card shadow-sm">
    <div class="border-b border-border px-6 py-4 flex items-center justify-between">
        <h2 class="text-base font-semibold text-card-foreground">Wizualizacja drzewa</h2>
        <div class="flex gap-1">
            <button type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input
                           px-3 text-xs font-medium text-foreground hover:bg-accent transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    title="Powiększ">
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
                    title="Pomniejsz">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    <line x1="8" y1="11" x2="14" y2="11"/>
                </svg>
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
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- D3 tree container — placeholder do implementacji w Fazie 3 -->
        <div id="tree-canvas"
             class="relative h-[600px] w-full overflow-hidden bg-muted/30"
             data-tree-id="<?= htmlspecialchars($tree->id) ?>"
             aria-label="Wizualizacja drzewa genealogicznego">
            <div class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                Wizualizacja D3.js — w trakcie implementacji
            </div>
        </div>
    <?php endif; ?>
</div>
