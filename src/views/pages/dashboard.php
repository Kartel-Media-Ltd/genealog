<?php
/**
 * Page: Dashboard
 * Główna strona po zalogowaniu — używa AppLayout.
 *
 * Zmienne przekazywane przez kontroler:
 *   $currentUser  = ['id' => 1, 'name' => 'Jan Kowalski', 'email' => 'jan@example.com', 'avatar' => '']
 *   $trees        = [['id' => 1, 'name' => 'Rodzina Kowalskich', 'persons_count' => 42, 'updated_at' => '2026-03-15'], ...]
 *   $recentTrees  = [...] (ostatnio aktywne drzewa)
 *   $stats        = ['total_trees' => 3, 'total_persons' => 156, 'total_events' => 89]
 */

require_once __DIR__ . '/../atoms/button.php';
require_once __DIR__ . '/../atoms/badge.php';
require_once __DIR__ . '/../atoms/avatar.php';
require_once __DIR__ . '/../atoms/card.php';
require_once __DIR__ . '/../atoms/spinner.php';

$currentUser = $currentUser ?? ['name' => 'Użytkownik', 'email' => '', 'avatar' => ''];
$trees       = $trees ?? [];
$recentTrees = $recentTrees ?? [];
$stats       = $stats ?? ['total_trees' => 0, 'total_persons' => 0, 'total_events' => 0];

$pageTitle = 'Dashboard';

/*
 * ─────────────────────────────────────────────────────────────────────
 * WIREFRAME ASCII
 * ─────────────────────────────────────────────────────────────────────
 *
 *  ┌──────────────────────────────────────────────────────────────────┐
 *  │ HEADER: Logo | Drzewa | Poszukiwania |             [Avatar ▾]   │
 *  ├──────────────────────────────────────────────────────────────────┤
 *  │                                                                  │
 *  │  Witaj, Jan! 👋                                                  │
 *  │  Ostatnie logowanie: 5 kwi 2026, 14:22                          │
 *  │                                                                  │
 *  │  ┌────────────────┐ ┌────────────────┐ ┌────────────────┐       │
 *  │  │ 📊 Drzewa      │ │ 👥 Osoby       │ │ 📅 Zdarzenia   │       │
 *  │  │                │ │                │ │                │       │
 *  │  │      3         │ │      156       │ │       89       │       │
 *  │  │ drzewa rodzinne│ │ dodanych osób  │ │ zdarzeń życia  │       │
 *  │  └────────────────┘ └────────────────┘ └────────────────┘       │
 *  │                                                                  │
 *  │  ┌──────────────────────────────────────────────────────────┐   │
 *  │  │ Moje drzewa genealogiczne          [+ Nowe drzewo]       │   │
 *  │  ├──────────────────────────────────────────────────────────┤   │
 *  │  │                                                          │   │
 *  │  │  ┌──────────────────────┐  ┌──────────────────────┐     │   │
 *  │  │  │ Rodzina Kowalskich   │  │ Rodzina Wiśniewskich  │     │   │
 *  │  │  │ 42 osoby             │  │ 28 osób               │     │   │
 *  │  │  │ Aktywne [badge]      │  │ [badge] Prywatne      │     │   │
 *  │  │  │ [Otwórz] [Edytuj]    │  │ [Otwórz] [Edytuj]    │     │   │
 *  │  │  └──────────────────────┘  └──────────────────────┘     │   │
 *  │  │                                                          │   │
 *  │  │  [ Pusta karta: + Dodaj pierwsze drzewo ]               │   │
 *  │  └──────────────────────────────────────────────────────────┘   │
 *  │                                                                  │
 *  │  ┌──────────────────────────────────────────────────────────┐   │
 *  │  │ Ostatnia aktywność                                       │   │
 *  │  ├──────────────────────────────────────────────────────────┤   │
 *  │  │  • Dodano osobę: Anna Kowalska — 5 kwi 2026             │   │
 *  │  │  • Edytowano drzewo: Rodzina Kowalskich — 3 kwi 2026    │   │
 *  │  └──────────────────────────────────────────────────────────┘   │
 *  │                                                                  │
 *  ├──────────────────────────────────────────────────────────────────┤
 *  │ FOOTER                                                           │
 *  └──────────────────────────────────────────────────────────────────┘
 */

?>

<!-- ================================================================
     Hero — powitanie
     ================================================================ -->
<section class="mb-8">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[hsl(var(--foreground))]">
                Witaj, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?>!
            </h1>
            <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">
                Twoje drzewo genealogiczne czeka na uzupełnienie.
            </p>
        </div>
        <!-- Quick action — CTA -->
        <a href="/trees/new"
           class="mt-4 inline-flex h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium
                  bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]
                  hover:bg-[hsl(var(--primary)/0.9)] transition-colors duration-200
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2
                  sm:mt-0">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nowe drzewo
        </a>
    </div>
</section>

<!-- ================================================================
     Statystyki — 3 karty
     ================================================================ -->
<section aria-label="Statystyki" class="mb-8">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        <!-- Karta: Drzewa -->
        <div class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))]
                    p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">
                        Drzewa rodzinne
                    </p>
                    <p class="mt-2 text-3xl font-bold text-[hsl(var(--foreground))]">
                        <?= (int)$stats['total_trees'] ?>
                    </p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full
                            bg-[hsl(var(--primary)/0.1)]">
                    <svg class="h-6 w-6 text-[hsl(var(--primary))]"
                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M12 22V12"/>
                        <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                        <path d="M12 12c0 0-3 1.5-3 5"/>
                        <path d="M12 12c0 0 3 1.5 3 5"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-[hsl(var(--muted-foreground))]">
                Twoje drzewa genealogiczne
            </p>
        </div>

        <!-- Karta: Osoby -->
        <div class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))]
                    p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">
                        Osoby
                    </p>
                    <p class="mt-2 text-3xl font-bold text-[hsl(var(--foreground))]">
                        <?= (int)$stats['total_persons'] ?>
                    </p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full
                            bg-[hsl(142,70%,45%,0.1)]">
                    <svg class="h-6 w-6 text-[hsl(var(--success))]"
                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-[hsl(var(--muted-foreground))]">
                Dodanych osób łącznie
            </p>
        </div>

        <!-- Karta: Zdarzenia -->
        <div class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))]
                    p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">
                        Zdarzenia
                    </p>
                    <p class="mt-2 text-3xl font-bold text-[hsl(var(--foreground))]">
                        <?= (int)$stats['total_events'] ?>
                    </p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full
                            bg-[hsl(var(--warning)/0.1)]">
                    <svg class="h-6 w-6 text-[hsl(var(--warning))]"
                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-[hsl(var(--muted-foreground))]">
                Narodziny, śluby, zgony...
            </p>
        </div>

    </div>
</section>

<!-- ================================================================
     Moje drzewa genealogiczne
     ================================================================ -->
<section aria-labelledby="trees-heading" class="mb-8">

    <div class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))] shadow-sm">

        <!-- Nagłówek sekcji -->
        <div class="flex items-center justify-between border-b border-[hsl(var(--border))] px-6 py-4">
            <h2 id="trees-heading"
                class="text-base font-semibold text-[hsl(var(--card-foreground))]">
                Moje drzewa genealogiczne
            </h2>
            <a href="/trees/new"
               class="inline-flex h-9 items-center gap-1.5 rounded-md border border-[hsl(var(--input))]
                      bg-[hsl(var(--background))] px-3 text-sm font-medium
                      text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]
                      transition-colors duration-200
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))]">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nowe drzewo
            </a>
        </div>

        <!-- Lista drzew -->
        <div class="p-6">
            <?php if (empty($trees)): ?>
                <!-- Empty state -->
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full
                                bg-[hsl(var(--muted))]">
                        <svg class="h-8 w-8 text-[hsl(var(--muted-foreground))]"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M12 22V12"/>
                            <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                            <path d="M12 12c0 0-3 1.5-3 5"/>
                            <path d="M12 12c0 0 3 1.5 3 5"/>
                        </svg>
                    </div>
                    <h3 class="mb-2 text-sm font-medium text-[hsl(var(--foreground))]">
                        Brak drzew genealogicznych
                    </h3>
                    <p class="mb-6 text-sm text-[hsl(var(--muted-foreground))]">
                        Zacznij od stworzenia swojego pierwszego drzewa rodzinnego.
                    </p>
                    <a href="/trees/new"
                       class="inline-flex h-11 items-center gap-2 rounded-md px-4 text-sm font-medium
                              bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]
                              hover:bg-[hsl(var(--primary)/0.9)] transition-colors duration-200
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))]">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Utwórz pierwsze drzewo
                    </a>
                </div>

            <?php else: ?>
                <!-- Siatka kart drzew -->
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    role="list"
                    aria-label="Lista drzew genealogicznych">

                    <?php foreach ($trees as $tree): ?>
                        <li>
                            <article class="group rounded-lg border border-[hsl(var(--border))]
                                           bg-[hsl(var(--background))] p-5 transition-shadow duration-200
                                           hover:shadow-md focus-within:shadow-md">

                                <!-- Nagłówek karty drzewa -->
                                <div class="mb-3 flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2.5">
                                        <!-- Ikona drzewa z kolorem -->
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center
                                                    rounded-md bg-[hsl(var(--primary)/0.1)]">
                                            <svg class="h-5 w-5 text-[hsl(var(--primary))]"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                 fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <path d="M12 22V12"/>
                                                <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                                                <path d="M12 12c0 0-3 1.5-3 5"/>
                                                <path d="M12 12c0 0 3 1.5 3 5"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-medium text-sm leading-tight text-[hsl(var(--foreground))]">
                                                <a href="/trees/<?= (int)$tree['id'] ?>"
                                                   class="focus-visible:outline-none focus-visible:ring-2
                                                          focus-visible:ring-[hsl(var(--ring))] rounded
                                                          after:absolute after:inset-0">
                                                    <?= htmlspecialchars($tree['name']) ?>
                                                </a>
                                            </h3>
                                            <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                                <?= (int)($tree['persons_count'] ?? 0) ?> osób
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                    render_badge(
                                        ($tree['is_private'] ?? false) ? 'Prywatne' : 'Publiczne',
                                        ($tree['is_private'] ?? false) ? 'secondary' : 'success'
                                    );
                                    ?>
                                </div>

                                <!-- Meta: ostatnia aktualizacja -->
                                <p class="mb-4 text-xs text-[hsl(var(--muted-foreground))]">
                                    Zaktualizowano:
                                    <?= isset($tree['updated_at'])
                                        ? date('j M Y', strtotime($tree['updated_at']))
                                        : 'nigdy' ?>
                                </p>

                                <!-- Akcje karty -->
                                <div class="relative flex items-center gap-2">
                                    <a href="/trees/<?= (int)$tree['id'] ?>"
                                       class="inline-flex h-9 flex-1 items-center justify-center gap-1.5
                                              rounded-md bg-[hsl(var(--primary))] px-3 text-xs font-medium
                                              text-[hsl(var(--primary-foreground))]
                                              hover:bg-[hsl(var(--primary)/0.9)] transition-colors duration-150
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))]"
                                       aria-label="Otwórz drzewo: <?= htmlspecialchars($tree['name']) ?>">
                                        Otwórz
                                    </a>
                                    <a href="/trees/<?= (int)$tree['id'] ?>/edit"
                                       class="inline-flex h-9 items-center justify-center rounded-md border
                                              border-[hsl(var(--input))] px-3 text-xs font-medium
                                              text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]
                                              transition-colors duration-150
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))]"
                                       aria-label="Edytuj drzewo: <?= htmlspecialchars($tree['name']) ?>">
                                        Edytuj
                                    </a>
                                </div>

                            </article>
                        </li>
                    <?php endforeach; ?>

                </ul>
            <?php endif; ?>
        </div>

        <?php if (!empty($trees)): ?>
            <!-- Footer karty sekcji — link do wszystkich -->
            <div class="border-t border-[hsl(var(--border))] px-6 py-3">
                <a href="/trees"
                   class="text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] rounded
                          inline-flex items-center gap-1">
                    Zobacz wszystkie drzewa
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" aria-hidden="true">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- ================================================================
     Ostatnia aktywność
     ================================================================ -->
<section aria-labelledby="activity-heading">

    <div class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))] shadow-sm">

        <div class="border-b border-[hsl(var(--border))] px-6 py-4">
            <h2 id="activity-heading"
                class="text-base font-semibold text-[hsl(var(--card-foreground))]">
                Ostatnia aktywność
            </h2>
        </div>

        <div class="p-6">
            <?php if (empty($recentTrees)): ?>
                <p class="py-6 text-center text-sm text-[hsl(var(--muted-foreground))]">
                    Brak aktywności do wyświetlenia.
                </p>
            <?php else: ?>
                <ul class="space-y-4" role="list">
                    <?php foreach ($recentTrees as $item): ?>
                        <li class="flex items-start gap-4">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center
                                        rounded-full bg-[hsl(var(--muted))]">
                                <svg class="h-4 w-4 text-[hsl(var(--muted-foreground))]"
                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-[hsl(var(--foreground))]">
                                    <?= htmlspecialchars($item['description'] ?? '') ?>
                                </p>
                                <p class="mt-0.5 text-xs text-[hsl(var(--muted-foreground))]">
                                    <?= isset($item['created_at'])
                                        ? date('j M Y, H:i', strtotime($item['created_at']))
                                        : '' ?>
                                </p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</section>

