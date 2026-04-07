<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\Tree[] $trees */
$trees       ??= [];
$sharedTrees ??= [];
?>

<!-- Nagłówek sekcji -->
<div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Moje drzewa</h1>
        <p class="mt-1 text-sm text-muted-foreground">Wszystkie Twoje drzewa genealogiczne</p>
    </div>
    <a href="/trees/new"
       class="mt-4 inline-flex h-10 items-center gap-2 rounded-md px-4 text-sm font-medium
              bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:mt-0">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nowe drzewo
    </a>
</div>

<?php if (empty($trees)): ?>
    <!-- Empty state -->
    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border
                bg-card py-16 text-center">
        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
            <svg class="h-8 w-8 text-muted-foreground" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 22V12"/>
                <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                <path d="M12 12c0 0-3 1.5-3 5"/>
                <path d="M12 12c0 0 3 1.5 3 5"/>
            </svg>
        </div>
        <h2 class="mb-2 text-base font-semibold text-foreground">Brak drzew genealogicznych</h2>
        <p class="mb-6 max-w-sm text-sm text-muted-foreground">
            Stwórz swoje pierwsze drzewo rodzinne i zacznij dodawać przodków.
        </p>
        <a href="/trees/new"
           class="inline-flex h-10 items-center gap-2 rounded-md px-4 text-sm font-medium
                  bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            Utwórz pierwsze drzewo
        </a>
    </div>

<?php else: ?>
    <!-- Siatka kart -->
    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
        <?php foreach ($trees as $tree): ?>
            <li>
                <article class="group relative rounded-lg border border-border bg-card p-5
                               shadow-sm transition-shadow hover:shadow-md">

                    <!-- Nagłówek karty -->
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center
                                        rounded-md bg-primary/10">
                                <svg class="h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.5" aria-hidden="true">
                                    <path d="M12 22V12"/>
                                    <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                                    <path d="M12 12c0 0-3 1.5-3 5"/>
                                    <path d="M12 12c0 0 3 1.5 3 5"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h2 class="truncate text-sm font-semibold leading-tight text-foreground">
                                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
                                       class="focus-visible:outline-none focus-visible:ring-2
                                              focus-visible:ring-ring rounded
                                              after:absolute after:inset-0">
                                        <?= htmlspecialchars($tree->name) ?>
                                    </a>
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    <?= $tree->personsCount ?> <?= $tree->personsCount === 1 ? 'osoba' : ($tree->personsCount < 5 ? 'osoby' : 'osób') ?>
                                </p>
                            </div>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                               <?= $tree->isPublic
                                   ? 'bg-green-100 text-green-800'
                                   : 'bg-muted text-muted-foreground' ?>">
                            <?= $tree->isPublic ? 'Publiczne' : 'Prywatne' ?>
                        </span>
                    </div>

                    <?php if ($tree->description): ?>
                        <p class="mb-3 line-clamp-2 text-xs text-muted-foreground">
                            <?= htmlspecialchars($tree->description) ?>
                        </p>
                    <?php endif; ?>

                    <p class="mb-4 text-xs text-muted-foreground">
                        Zaktualizowano: <?= date('j M Y', strtotime($tree->updatedAt)) ?>
                    </p>

                    <!-- Akcje -->
                    <div class="relative flex gap-2">
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
                           class="relative z-10 inline-flex h-8 flex-1 items-center justify-center rounded-md
                                  bg-primary px-3 text-xs font-medium text-primary-foreground
                                  hover:bg-primary/90 transition-colors
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            Otwórz
                        </a>
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/edit"
                           class="relative z-10 inline-flex h-8 items-center justify-center rounded-md
                                  border border-input px-3 text-xs font-medium text-foreground
                                  hover:bg-accent transition-colors
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            Edytuj
                        </a>
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/members"
                           class="relative z-10 inline-flex h-8 items-center justify-center rounded-md
                                  border border-input px-3 text-xs font-medium text-foreground
                                  hover:bg-accent transition-colors
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                           title="Zarządzaj dostępem">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </a>
                    </div>
                </article>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (!empty($sharedTrees)): ?>
    <!-- Sekcja: Udostępnione mi -->
    <div class="mt-10 mb-6 flex flex-col gap-1">
        <h2 class="text-xl font-bold tracking-tight text-foreground">Udostępnione mi</h2>
        <p class="text-sm text-muted-foreground">Drzewa, do których zostałeś zaproszony</p>
    </div>

    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
        <?php foreach ($sharedTrees as $tree): ?>
            <li>
                <article class="group relative rounded-lg border border-border bg-card p-5
                               shadow-sm transition-shadow hover:shadow-md">

                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center
                                        rounded-md bg-blue-500/10">
                                <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.5" aria-hidden="true">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-semibold leading-tight text-foreground">
                                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
                                       class="focus-visible:outline-none focus-visible:ring-2
                                              focus-visible:ring-ring rounded
                                              after:absolute after:inset-0">
                                        <?= htmlspecialchars($tree->name) ?>
                                    </a>
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    <?= $tree->personsCount ?> <?= $tree->personsCount === 1 ? 'osoba' : ($tree->personsCount < 5 ? 'osoby' : 'osób') ?>
                                    <?php if ($tree->ownerName): ?>
                                        &middot; właściciel: <?= htmlspecialchars($tree->ownerName) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                               bg-blue-100 text-blue-800">
                            Gość
                        </span>
                    </div>

                    <?php if ($tree->description): ?>
                        <p class="mb-3 line-clamp-2 text-xs text-muted-foreground">
                            <?= htmlspecialchars($tree->description) ?>
                        </p>
                    <?php endif; ?>

                    <p class="mb-4 text-xs text-muted-foreground">
                        Zaktualizowano: <?= date('j M Y', strtotime($tree->updatedAt)) ?>
                    </p>

                    <div class="relative flex gap-2">
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
                           class="relative z-10 inline-flex h-8 flex-1 items-center justify-center rounded-md
                                  bg-primary px-3 text-xs font-medium text-primary-foreground
                                  hover:bg-primary/90 transition-colors
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            Otwórz
                        </a>
                    </div>
                </article>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
