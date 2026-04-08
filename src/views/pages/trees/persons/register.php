<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../atoms/icon.php';

/** @var \App\Models\Tree   $tree */
/** @var \App\Models\Person $person */
/** @var array              $cognaticList     entries from RegisterService::build() */
/** @var array              $patrilinearList  entries from RegisterService::build() */

/**
 * Helper: render one entry row.
 * @param array{number: string, person: \App\Models\Person, depth: int, spouses: \App\Models\Person[], note: ?string, skipped: bool} $entry
 */
function renderRegisterRow(array $entry, string $treeId): void
{
    $person  = $entry['person'];
    $number  = $entry['number'];
    $depth   = $entry['depth'];
    $note    = $entry['note'];
    $spouses = $entry['spouses'];
    $isRoot  = $depth === 0;

    $indent = ($depth > 0 ? ($depth - 1) * 20 : 0);

    if ($isRoot) {
        // Root person — shown as header
        echo '<div class="py-3 border-b border-border mb-3">';
        echo '<p class="text-xs font-medium text-muted-foreground mb-0.5">Przodek / punkt wyjścia</p>';
        echo '<p class="text-lg font-bold text-foreground">';
        if (!$person->isLiving) echo '<span class="text-muted-foreground mr-1" aria-hidden="true">†</span>';
        echo htmlspecialchars($person->fullName());
        if ($person->maidenName) {
            echo ' <span class="text-sm font-normal text-muted-foreground">(z d. ' . htmlspecialchars($person->maidenName) . ')</span>';
        }
        if ($person->birthDate || $person->deathDate) {
            echo '<span class="text-sm font-normal text-muted-foreground ml-2">';
            if ($person->birthDate) echo '* ' . htmlspecialchars(substr($person->birthDate, 0, 4));
            if ($person->deathDate) echo ($person->birthDate ? ' ' : '') . '† ' . htmlspecialchars(substr($person->deathDate, 0, 4));
            echo '</span>';
        }
        echo '</p>';
        echo '</div>';
        return;
    }

    echo '<div class="flex items-baseline gap-2 py-1.5 hover:bg-muted/30 rounded px-1 -mx-1 transition-colors"'
        . ' style="padding-left: ' . ($indent + 4) . 'px">';

    // Number
    echo '<span class="font-mono text-xs text-muted-foreground flex-shrink-0 min-w-[3rem] text-right">'
        . htmlspecialchars($number) . '.</span>';

    // Name
    echo '<span class="text-sm text-foreground">';
    if (!$person->isLiving) echo '<span class="text-muted-foreground mr-0.5" aria-hidden="true">†</span>';
    echo '<a href="/trees/' . htmlspecialchars($treeId) . '/persons/' . htmlspecialchars($person->id)
        . '" class="hover:underline font-medium">' . htmlspecialchars($person->fullName()) . '</a>';
    if ($person->maidenName) {
        echo ' <span class="text-muted-foreground">(z d. ' . htmlspecialchars($person->maidenName) . ')</span>';
    }

    // Dates
    if ($person->birthDate || $person->deathDate) {
        echo ' <span class="text-xs text-muted-foreground">';
        if ($person->birthDate) echo '* ' . htmlspecialchars(substr($person->birthDate, 0, 4));
        if ($person->deathDate) echo ($person->birthDate ? ', ' : '') . '† ' . htmlspecialchars(substr($person->deathDate, 0, 4));
        echo '</span>';
    }

    // Patrilinear note (daughter: zamężna za X)
    if ($note) {
        echo ' <span class="text-xs italic text-muted-foreground">' . htmlspecialchars($note) . '</span>';
    }

    // Spouses (cognatic mode)
    if (!empty($spouses)) {
        $spouseTexts = array_map(
            fn(\App\Models\Person $s) => htmlspecialchars($s->fullName()),
            $spouses
        );
        echo ' <span class="text-xs text-muted-foreground">× ' . implode(', × ', $spouseTexts) . '</span>';
    }

    echo '</span>';
    echo '</div>';
}
?>

<style>
@media print {
    nav, .no-print { display: none !important; }
    .print-header  { display: block !important; }
    body { font-size: 11pt; }
    a { color: inherit; text-decoration: none; }
    [x-cloak] { display: none; }
}
.print-header { display: none; }
</style>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground no-print" aria-label="Nawigacja">
    <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
       class="hover:text-foreground transition-colors"><?= htmlspecialchars($tree->name) ?></a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons"
       class="hover:text-foreground transition-colors">Osoby</a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
       class="hover:text-foreground transition-colors"><?= htmlspecialchars($person->fullName()) ?></a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <span class="text-foreground font-medium">Rejestr potomków</span>
</nav>

<!-- Print header (hidden on screen, shown on print) -->
<div class="print-header mb-4">
    <h1 class="text-2xl font-bold">Rejestr potomków</h1>
    <p class="text-sm text-muted-foreground"><?= htmlspecialchars($tree->name) ?></p>
</div>

<div x-data="{ mode: 'cognatic' }">

    <!-- Header + controls -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between no-print">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Rejestr potomków</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                <?= htmlspecialchars($person->fullName()) ?> —
                <span x-show="mode === 'cognatic'">system kognatyczny (wszyscy potomkowie)</span>
                <span x-show="mode === 'patrilinear'" x-cloak>system patrylinearny (linia męska)</span>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <!-- Mode toggle -->
            <div class="inline-flex rounded-md border border-border overflow-hidden text-sm font-medium">
                <button
                    @click="mode = 'cognatic'"
                    :class="mode === 'cognatic' ? 'bg-primary text-primary-foreground' : 'bg-background text-foreground hover:bg-muted'"
                    class="px-3 h-9 transition-colors">
                    Kognatyczny
                </button>
                <button
                    @click="mode = 'patrilinear'"
                    :class="mode === 'patrilinear' ? 'bg-primary text-primary-foreground' : 'bg-background text-foreground hover:bg-muted'"
                    class="px-3 h-9 border-l border-border transition-colors">
                    Patrylinearny
                </button>
            </div>
            <!-- Print -->
            <button onclick="window.print()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md border border-input px-3
                           text-sm font-medium text-foreground hover:bg-accent transition-colors">
                <?php render_icon('print', 'solid', 'h-4 w-4') ?>
                Drukuj
            </button>
            <!-- Back -->
            <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
               onclick="if (window.history.length > 1) { history.back(); return false; }"
               class="inline-flex h-9 items-center rounded-md border border-input px-3
                      text-sm font-medium text-foreground hover:bg-accent transition-colors">
                ← Powrót
            </a>
        </div>
    </div>

    <!-- Legend -->
    <div class="mb-4 flex flex-wrap gap-4 text-xs text-muted-foreground no-print">
        <span>× = małżonek/partner</span>
        <span>* = rok urodzenia</span>
        <span>† = rok śmierci / osoba nieżyjąca</span>
        <span x-show="mode === 'patrilinear'" x-cloak><em>zamężna za</em> = córka, linia nie kontynuowana</span>
    </div>

    <!-- Cognatic register -->
    <div x-show="mode === 'cognatic'"
         class="rounded-lg border border-border bg-card shadow-sm px-6 py-5">
        <?php if (empty($cognaticList)): ?>
            <p class="text-sm text-muted-foreground text-center py-8">Brak danych.</p>
        <?php else: ?>
            <?php foreach ($cognaticList as $entry): ?>
                <?php renderRegisterRow($entry, $tree->id); ?>
            <?php endforeach; ?>
            <p class="mt-4 text-xs text-muted-foreground border-t border-border pt-3">
                Łącznie potomków: <?= max(0, count($cognaticList) - 1) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Patrilinear register -->
    <div x-show="mode === 'patrilinear'" x-cloak
         class="rounded-lg border border-border bg-card shadow-sm px-6 py-5">
        <?php if (empty($patrilinearList)): ?>
            <p class="text-sm text-muted-foreground text-center py-8">Brak danych.</p>
        <?php else: ?>
            <?php foreach ($patrilinearList as $entry): ?>
                <?php renderRegisterRow($entry, $tree->id); ?>
            <?php endforeach; ?>
            <?php
            $patrilinearExpanded = count(array_filter($patrilinearList, fn($e) => !$e['skipped'] && $e['depth'] > 0));
            $patrilinearDaughters = count(array_filter($patrilinearList, fn($e) => $e['skipped']));
            ?>
            <p class="mt-4 text-xs text-muted-foreground border-t border-border pt-3">
                Potomkowie w linii męskiej: <?= $patrilinearExpanded ?>
                <?php if ($patrilinearDaughters > 0): ?>
                    · Córki (poza rejestr): <?= $patrilinearDaughters ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

</div>
