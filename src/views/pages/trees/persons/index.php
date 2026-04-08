<?php
declare(strict_types=1);
use App\Core\DateHelper;
require_once __DIR__ . '/../../../atoms/icon.php';

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
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
       class="hover:text-foreground transition-colors"><?= htmlspecialchars($tree->name) ?></a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
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
                <?php render_icon('file', 'solid', 'h-3.5 w-3.5') ?>
                Rejestr drzewa
                <?php render_icon('chevron-down', 'solid', 'h-3 w-3 transition-transform') ?>
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
            <?php render_icon('print', 'solid', 'h-4 w-4') ?>
            Drukuj listę
        </a>
        <?php if ($canEdit): ?>
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/new"
               class="inline-flex h-10 items-center gap-2 rounded-md px-4 text-sm font-medium
                      bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <?php render_icon('plus', 'solid', 'h-4 w-4') ?>
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
                <?php render_icon('users', 'solid', 'h-8 w-8 text-muted-foreground') ?>
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
                <?php render_icon('magnifying-glass', 'solid', 'absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground') ?>
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
                        <?php $needsVisibilityHint = !$person->isLiving && $person->visibility === 'private'; ?>
                        <tr
                            class="border-b border-border last:border-0 transition-colors <?= $needsVisibilityHint ? 'bg-red-50 hover:bg-red-100/70' : 'hover:bg-muted/30' ?>"
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
                                    <?php if ($needsVisibilityHint): ?>
                                        <span class="relative group hidden sm:inline-flex">
                                            <span class="inline-flex h-8 w-8 items-center justify-center text-red-400 cursor-default">
                                                <?php render_icon('circle-info', 'solid', 'h-4 w-4', 'Uwaga o widoczności') ?>
                                            </span>
                                            <span class="pointer-events-none absolute right-full mr-2 top-1/2 -translate-y-1/2 z-20
                                                         w-64 rounded-md border border-red-200 bg-white px-3 py-2 text-xs
                                                         text-red-700 shadow-md opacity-0 group-hover:opacity-100 transition-opacity
                                                         text-left leading-relaxed">
                                                Ta osoba nie pojawi się w wynikach wyszukiwania innych użytkowników.
                                                Zmień widoczność na <strong>Anonimową</strong>, aby umożliwić innym
                                                genealogom odnalezienie swoich korzeni i połączenie rodzin.
                                            </span>
                                        </span>
                                    <?php endif; ?>
                                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
                                       title="Szczegóły"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input
                                              text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                                        <?php render_icon('eye', 'solid', 'h-3.5 w-3.5', 'Szczegóły') ?>
                                    </a>
                                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/register"
                                       title="Rejestr potomków"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input
                                              text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                                        <?php render_icon('file', 'solid', 'h-3.5 w-3.5', 'Rejestr potomków') ?>
                                    </a>
                                    <?php if ($canEdit): ?>
                                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/edit"
                                           title="Edytuj"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input
                                                  text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                                            <?php render_icon('pen-to-square', 'solid', 'h-3.5 w-3.5', 'Edytuj') ?>
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
