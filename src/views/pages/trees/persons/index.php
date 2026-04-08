<?php
declare(strict_types=1);
use App\Core\DateHelper;

/** @var \App\Models\Tree $tree */
/** @var \App\Models\Person[] $persons */
/** @var array{person: \App\Models\Person, depth: int}[] $personsTree */
/** @var string|null $userRole */
$userRole    ??= null;
$personsTree ??= array_map(fn($p) => ['person' => $p, 'depth' => 0], $persons);
$canEdit = in_array($userRole, ['owner', 'editor'], true);
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
       class="hover:text-foreground transition-colors"><?= htmlspecialchars($tree->name) ?></a>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span class="text-foreground font-medium">Osoby</span>
</nav>

<!-- Header -->
<?php
// Root persons (depth=0) for the register dropdown
$rootPersons = array_values(array_filter($personsTree, fn($e) => $e['depth'] === 0));
?>
<div x-data="{ view: 'hierarchy', search: '' }">
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Osoby w drzewie</h1>
        <p class="mt-1 text-sm text-muted-foreground"><?= count($persons) ?> osób</p>
    </div>
    <div class="flex flex-wrap gap-2">

        <!-- Rejestr drzewa — dropdown with root persons -->
        <?php if (!empty($rootPersons)): ?>
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                           text-sm font-medium text-foreground hover:bg-accent transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                Rejestr drzewa
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
                     :class="open ? 'rotate-180' : ''" class="transition-transform">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div x-show="open" x-cloak
                 class="absolute right-0 z-50 mt-1 min-w-[220px] rounded-md border border-border
                        bg-background shadow-lg py-1">
                <p class="px-3 py-1.5 text-xs font-medium text-muted-foreground">
                    Wybierz punkt startowy:
                </p>
                <?php foreach ($rootPersons as $re): ?>
                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($re['person']->id) ?>/register"
                       class="flex items-center gap-2 px-3 py-2 text-sm text-foreground hover:bg-accent transition-colors">
                        <?php if (!$re['person']->isLiving): ?>
                            <span class="text-muted-foreground text-xs" aria-hidden="true">†</span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($re['person']->fullName()) ?></span>
                        <?php if ($re['person']->birthDate): ?>
                            <span class="ml-auto text-xs text-muted-foreground">
                                <?= htmlspecialchars(substr($re['person']->birthDate, 0, 4)) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <a :href="'/trees/<?= htmlspecialchars($tree->id) ?>/persons/print?mode=' + view"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-flex h-10 items-center gap-2 rounded-md border border-input px-4
                  text-sm font-medium text-foreground hover:bg-accent transition-colors
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Drukuj listę
        </a>
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
        <?php endif; ?>
    </div>
</div>

<?php
// Compute decimal numbers for hierarchical view
$counters = [];
foreach ($personsTree as &$entry) {
    $d = $entry['depth'];
    // When going shallower or same level, clear deeper counters
    foreach (array_keys($counters) as $k) {
        if ($k > $d) unset($counters[$k]);
    }
    $counters[$d] = ($counters[$d] ?? 0) + 1;
    $parts = [];
    for ($i = 0; $i <= $d; $i++) {
        $parts[] = $counters[$i] ?? 1;
    }
    $entry['number'] = implode('.', $parts);
}
unset($entry);
?>

<?php if (empty($persons)): ?>
    <!-- Empty state -->
    <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                <svg class="h-8 w-8 text-muted-foreground" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </div>
            <h3 class="mb-2 text-sm font-medium text-foreground">Brak osób w drzewie</h3>
            <p class="mb-6 max-w-xs text-sm text-muted-foreground">
                Dodaj pierwszą osobę i zacznij budować swoje drzewo genealogiczne.
            </p>
            <?php if ($canEdit): ?>
                <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/new"
                   class="inline-flex h-10 items-center gap-2 rounded-md px-4 text-sm font-medium
                          bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                    Dodaj pierwszą osobę
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Search + Table -->
    <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4 flex items-center gap-3 flex-wrap">
            <div class="relative flex-1 max-w-sm">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="search"
                    x-model="search"
                    placeholder="Szukaj osoby..."
                    class="w-full h-9 pl-9 pr-3 rounded-md border border-border bg-background
                           text-sm text-foreground placeholder:text-muted-foreground
                           focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                    aria-label="Szukaj wśród osób"
                >
            </div>
            <!-- View toggle -->
            <div class="inline-flex rounded-md border border-border overflow-hidden text-xs font-medium">
                <button type="button"
                        @click="view = 'list'"
                        :class="view === 'list' ? 'bg-primary text-primary-foreground' : 'bg-background text-foreground hover:bg-muted'"
                        class="px-3 h-9 transition-colors">
                    Lista
                </button>
                <button type="button"
                        @click="view = 'hierarchy'"
                        :class="view === 'hierarchy' ? 'bg-primary text-primary-foreground' : 'bg-background text-foreground hover:bg-muted'"
                        class="px-3 h-9 border-l border-border transition-colors">
                    Hierarchia
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border bg-muted/40">
                        <th class="px-6 py-3 text-left font-medium text-muted-foreground">Imię i nazwisko</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground hidden sm:table-cell">Data ur.</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground hidden md:table-cell">Płeć</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground hidden lg:table-cell">Widoczność</th>
                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($personsTree as $entry):
                        $person = $entry['person'];
                        $depth  = $entry['depth'];
                        $number = $entry['number'];
                        $indent = $depth * 24; // px per level
                    ?>
                        <tr
                            class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors"
                            data-search-name="<?= htmlspecialchars(strtolower($person->fullName()), ENT_QUOTES, 'UTF-8') ?>"
                            x-show="search === '' || $el.dataset.searchName.includes(search.toLowerCase())"
                        >
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3"
                                     :style="view === 'hierarchy' ? 'padding-left: <?= $indent ?>px' : ''">
                                    <!-- Decimal number (hierarchy mode only) -->
                                    <span x-show="view === 'hierarchy'"
                                          class="font-mono text-xs text-muted-foreground flex-shrink-0 min-w-[2.5rem] text-right select-none">
                                        <?= htmlspecialchars($number) ?>
                                    </span>
                                    <?php if ($depth > 0): ?>
                                        <span x-show="view === 'hierarchy'"
                                              class="text-muted-foreground/40 flex-shrink-0 select-none" aria-hidden="true">└</span>
                                    <?php endif; ?>
                                    <div class="h-8 w-8 rounded-full overflow-hidden bg-muted flex items-center justify-center flex-shrink-0">
                                        <?php if ($person->photoPath): ?>
                                            <img src="/media.php?path=<?= urlencode($person->photoPath) ?>"
                                                 alt="" class="h-8 w-8 object-cover">
                                        <?php else: ?>
                                            <span class="text-xs font-medium text-muted-foreground">
                                                <?= htmlspecialchars(mb_strtoupper(mb_substr($person->firstName, 0, 1) . mb_substr($person->lastName, 0, 1))) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
                                       class="font-medium text-foreground hover:underline">
                                        <?php if (!$person->isLiving): ?><span class="text-muted-foreground mr-0.5" title="osoba nieżyjąca" aria-label="zmarła">†</span><?php endif; ?><?= htmlspecialchars($person->fullName()) ?><?php $age = DateHelper::ageInYears($person->birthDate, $person->deathDate); if ($age !== null): ?> <span class="text-muted-foreground font-normal text-xs">l.&nbsp;<?= $age ?></span><?php endif; ?><?php if ($person->maidenName): ?> <span class="text-muted-foreground font-normal">(z d. <?= htmlspecialchars($person->maidenName) ?>)</span><?php endif; ?>
                                    </a>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground hidden sm:table-cell">
                                <?= $person->birthDate ? htmlspecialchars(substr($person->birthDate, 0, 4)) : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground hidden md:table-cell">
                                <?= match($person->gender) {
                                    'male'   => 'Mężczyzna',
                                    'female' => 'Kobieta',
                                    default  => 'Nieznana',
                                } ?>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    <?= match($person->visibility) {
                                        'public'    => 'bg-green-100 text-green-800',
                                        'anonymous' => 'bg-yellow-100 text-yellow-800',
                                        default     => 'bg-muted text-muted-foreground',
                                    } ?>">
                                    <?= match($person->visibility) {
                                        'public'    => 'Publiczne',
                                        'anonymous' => 'Anonimowe',
                                        default     => 'Prywatne',
                                    } ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
                                       class="inline-flex h-8 items-center rounded-md border border-input px-2.5
                                              text-xs font-medium text-foreground hover:bg-accent transition-colors">
                                        Szczegóły
                                    </a>
                                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/register"
                                       title="Rejestr potomków"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input
                                              text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13"
                                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" aria-hidden="true">
                                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                        </svg>
                                        <span class="sr-only">Rejestr potomków</span>
                                    </a>
                                    <?php if ($canEdit): ?>
                                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/edit"
                                           class="inline-flex h-8 items-center rounded-md border border-input px-2.5
                                                  text-xs font-medium text-foreground hover:bg-accent transition-colors">
                                            Edytuj
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</div><!-- /x-data wrapper -->
