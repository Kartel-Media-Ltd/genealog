<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\Tree $tree */
/** @var \App\Models\Person $person */
/** @var \App\Models\Person[] $others */
/** @var array $suggestions */
$suggestions ??= [];
?>

<div class="mx-auto max-w-xl">

    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
           class="hover:text-foreground transition-colors"><?= htmlspecialchars($person->fullName()) ?></a>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
        <span class="text-foreground font-medium">Nowa relacja</span>
    </nav>

    <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h1 class="text-lg font-semibold text-card-foreground">Dodaj relację rodzinną</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Definiujesz relację z perspektywy: <strong><?= htmlspecialchars($person->fullName()) ?></strong>
            </p>
        </div>

        <?php if (empty($others)): ?>
            <div class="p-6 text-center">
                <p class="text-sm text-muted-foreground">Brak innych osób w drzewie. Dodaj więcej osób, aby tworzyć relacje.</p>
                <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/new"
                   class="mt-4 inline-flex h-9 items-center rounded-md px-4 text-sm font-medium
                          bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                    Dodaj osobę
                </a>
            </div>
        <?php else: ?>
            <form method="POST"
                  action="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/relationships"
                  class="p-6 space-y-5" novalidate>
                <?= Csrf::hiddenInput() ?>

                <div class="space-y-1.5">
                    <label for="type" class="block text-sm font-medium text-foreground">
                        Typ relacji <span class="text-destructive" aria-hidden="true">*</span>
                    </label>
                    <select id="type" name="type" required
                            class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                   text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                   focus:border-transparent">
                        <option value="">— wybierz —</option>
                        <option value="parent">Rodzic (tej osoby)</option>
                        <option value="child">Dziecko (tej osoby)</option>
                        <option value="spouse">Małżonek/Małżonka</option>
                        <option value="partner">Partner/Partnerka</option>
                        <option value="sibling">Rodzeństwo</option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Relacja odwrotna zostanie dodana automatycznie (np. rodzic↔dziecko).
                    </p>
                </div>

                <?php
                // Build persons JSON for Alpine.js
                $personsJson = json_encode(array_map(function ($p) {
                    $label = ($p->isLiving ? '' : '† ') . $p->fullName();
                    if ($p->birthDate) {
                        $label .= ' (ur. ' . substr($p->birthDate, 0, 4) . ')';
                    }
                    return ['id' => $p->id, 'label' => $label, 'dead' => !$p->isLiving];
                }, array_values($others)), JSON_UNESCAPED_UNICODE);
                ?>
                <div class="space-y-1.5"
                     x-data="{
                         open: false,
                         search: '',
                         selected: '',
                         selectedLabel: '',
                         persons: <?= htmlspecialchars($personsJson, ENT_QUOTES) ?>,
                         get filtered() {
                             if (!this.search) return this.persons;
                             const s = this.search.toLowerCase();
                             return this.persons.filter(p => p.label.toLowerCase().includes(s));
                         },
                         choose(p) {
                             this.selected = p.id;
                             this.selectedLabel = p.label;
                             this.open = false;
                             this.search = '';
                         }
                     }"
                     @click.outside="open = false">

                    <label class="block text-sm font-medium text-foreground">
                        Osoba <span class="text-destructive" aria-hidden="true">*</span>
                    </label>

                    <!-- Hidden real input -->
                    <input type="hidden" name="person_b_id" :value="selected" required>

                    <!-- Trigger -->
                    <button type="button"
                            @click="open = !open"
                            :class="open ? 'ring-2 ring-ring border-transparent' : ''"
                            class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                   text-foreground text-sm text-left flex items-center justify-between
                                   focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent
                                   transition-colors">
                        <span :class="selected ? 'text-foreground' : 'text-muted-foreground'"
                              x-text="selectedLabel || '— wybierz osobę —'"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             :class="open ? 'rotate-180' : ''"
                             class="flex-shrink-0 transition-transform" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <!-- Dropdown panel -->
                    <div x-show="open" x-cloak
                         class="relative z-50 mt-1 w-full rounded-md border border-border bg-background
                                shadow-lg">

                        <!-- Search input -->
                        <div class="p-2 border-b border-border">
                            <input type="text"
                                   x-model="search"
                                   x-ref="searchInput"
                                   x-init="$watch('open', v => v && $nextTick(() => $refs.searchInput?.focus()))"
                                   placeholder="Szukaj osoby..."
                                   class="w-full h-8 px-2 rounded border border-border bg-background
                                          text-sm text-foreground placeholder:text-muted-foreground
                                          focus:outline-none focus:ring-1 focus:ring-ring">
                        </div>

                        <!-- Options list -->
                        <ul class="max-h-56 overflow-y-auto py-1" role="listbox">
                            <template x-if="filtered.length === 0">
                                <li class="px-3 py-2 text-sm text-muted-foreground">Brak wyników.</li>
                            </template>
                            <template x-for="p in filtered" :key="p.id">
                                <li @click="choose(p)"
                                    :class="selected === p.id ? 'bg-primary/10 text-primary' : 'hover:bg-accent'"
                                    class="px-3 py-1.5 text-sm cursor-pointer transition-colors flex items-center gap-1.5"
                                    role="option"
                                    :aria-selected="selected === p.id">
                                    <span x-show="p.dead"
                                          class="text-muted-foreground text-xs"
                                          aria-label="osoba nieżyjąca">†</span>
                                    <span x-text="p.dead ? p.label.replace(/^† /, '') : p.label"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
                       class="inline-flex h-10 items-center rounded-md border border-input px-4
                              text-sm font-medium text-foreground hover:bg-accent transition-colors">
                        Anuluj
                    </a>
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-md px-4 text-sm font-medium
                                   bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                        Dodaj relację
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($suggestions)): ?>
    <div class="mt-4 rounded-lg border border-primary/40 bg-primary/5 shadow-sm">
        <div class="border-b border-primary/20 px-6 py-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" class="text-primary flex-shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <h2 class="text-sm font-semibold text-foreground">Inne sugerowane relacje</h2>
            <span class="ml-auto text-xs text-muted-foreground">Na podstawie istniejących powiązań</span>
        </div>

        <form method="POST"
              action="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/suggestions">
            <?= Csrf::hiddenInput() ?>

            <div class="px-6 py-4 space-y-3">
                <?php foreach ($suggestions as $s): ?>
                    <?php
                    $tp = $s['targetPerson'];
                    $typeLabel = match($s['type']) {
                        'child'   => 'dziecko',
                        'parent'  => 'rodzic',
                        'sibling' => 'rodzeństwo',
                        'spouse'  => 'małżonek/małżonka',
                        'partner' => 'partner/partnerka',
                        default   => $s['type'],
                    };
                    $initials = mb_strtoupper(mb_substr($tp->firstName, 0, 1) . mb_substr($tp->lastName, 0, 1));
                    $gColor = match($tp->gender) {
                        'male'   => 'hsl(210,70%,88%)',
                        'female' => 'hsl(340,60%,88%)',
                        default  => 'hsl(var(--muted))',
                    };
                    $value = htmlspecialchars(json_encode([
                        'type'           => $s['type'],
                        'targetPersonId' => $tp->id,
                    ]));
                    ?>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="suggestions[]" value="<?= $value ?>" checked
                               class="h-4 w-4 rounded border-border text-primary">
                        <div class="h-7 w-7 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-medium text-muted-foreground"
                             style="background:<?= $gColor ?>">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-medium text-foreground">
                                <?= htmlspecialchars($tp->fullName()) ?>
                            </span>
                            <span class="text-xs text-muted-foreground ml-1">
                                — <?= htmlspecialchars($typeLabel) ?> (<?= htmlspecialchars($s['reason']) ?>)
                            </span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="border-t border-primary/20 px-6 py-3">
                <button type="submit"
                        class="inline-flex h-9 items-center gap-2 rounded-md px-4 text-sm font-medium
                               bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Dodaj zaznaczone
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>
