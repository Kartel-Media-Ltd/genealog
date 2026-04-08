<?php
declare(strict_types=1);
use App\Core\Csrf;
use App\Core\DateHelper;
require_once __DIR__ . '/../../../atoms/icon.php';

/** @var \App\Models\Tree $tree */
/** @var \App\Models\Person $person */
/** @var \App\Models\Relationship[] $relationships */
/** @var bool $canEdit */
/** @var array $suggestions */
$suggestions ??= [];
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
       class="hover:text-foreground transition-colors"><?= htmlspecialchars($tree->name) ?></a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons"
       class="hover:text-foreground transition-colors">Osoby</a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <span class="text-foreground font-medium"><?php if (!$person->isLiving): ?><span class="text-muted-foreground mr-0.5" aria-hidden="true">†</span><?php endif; ?><?= htmlspecialchars($person->fullName()) ?></span>
</nav>

<div class="grid gap-6 lg:grid-cols-3">

    <!-- Left: Person card -->
    <div class="lg:col-span-1">
        <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">

            <!-- Photo -->
            <div class="aspect-square w-full bg-muted flex items-center justify-center">
                <?php if ($person->photoPath): ?>
                    <img src="/media.php?path=<?= urlencode($person->photoPath) ?>"
                         alt="<?= htmlspecialchars($person->fullName()) ?>"
                         class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-6xl font-light text-muted-foreground">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($person->firstName, 0, 1) . mb_substr($person->lastName, 0, 1))) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="p-5 space-y-4">
                <!-- Name + badges -->
                <div>
                    <h1 class="text-xl font-bold text-card-foreground"><?php if (!$person->isLiving): ?><span class="text-muted-foreground mr-0.5" aria-hidden="true">†</span><?php endif; ?><?= htmlspecialchars($person->fullName()) ?><?php
                        $birthYear = $person->birthDate ? (int) substr($person->birthDate, 0, 4) : null;
                        $age       = DateHelper::ageInYears($person->birthDate, $person->deathDate);
                        if ($age !== null || $birthYear !== null):
                    ?> <span class="text-muted-foreground font-normal text-sm">(<?php if ($age !== null): ?>l.&nbsp;<?= $age ?><?php endif; ?><?php if ($age !== null && $birthYear !== null): ?>, <?php endif; ?><?php if ($birthYear !== null): ?>ur.&nbsp;<?= $birthYear ?><?php endif; ?>)</span><?php endif; ?></h1>
                    <?php if ($person->maidenName): ?>
                        <p class="text-sm text-muted-foreground">z d. <?= htmlspecialchars($person->maidenName) ?></p>
                    <?php endif; ?>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            <?= $person->isLiving ? 'bg-green-100 text-green-800' : 'bg-muted text-muted-foreground' ?>">
                            <?= $person->isLiving ? 'Żyjąca' : 'Historyczna' ?>
                        </span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            <?= match($person->visibility) {
                                'public'    => 'bg-blue-100 text-blue-800',
                                'anonymous' => 'bg-yellow-100 text-yellow-800',
                                default     => 'bg-muted text-muted-foreground',
                            } ?>">
                            <?= match($person->visibility) {
                                'public'    => 'Publiczne',
                                'anonymous' => 'Anonimowe',
                                default     => 'Prywatne',
                            } ?>
                        </span>
                    </div>
                </div>

                <!-- Key facts -->
                <dl class="space-y-2 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-28 flex-shrink-0 text-muted-foreground">Płeć:</dt>
                        <dd class="text-foreground">
                            <?= match($person->gender) {
                                'male'   => 'Mężczyzna',
                                'female' => 'Kobieta',
                                default  => 'Nieznana',
                            } ?>
                        </dd>
                    </div>
                    <?php if ($person->birthDate): ?>
                        <div class="flex gap-2">
                            <dt class="w-28 flex-shrink-0 text-muted-foreground">Urodzony/a:</dt>
                            <dd class="text-foreground">
                                <?= htmlspecialchars($person->birthDate) ?>
                                <?php if ($person->birthPlace): ?>
                                    <br><span class="text-muted-foreground text-xs"><?= htmlspecialchars($person->birthPlace) ?></span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($person->deathDate): ?>
                        <div class="flex gap-2">
                            <dt class="w-28 flex-shrink-0 text-muted-foreground">Zmarły/a:</dt>
                            <dd class="text-foreground">
                                <?= htmlspecialchars($person->deathDate) ?>
                                <?php if ($person->deathPlace): ?>
                                    <br><span class="text-muted-foreground text-xs"><?= htmlspecialchars($person->deathPlace) ?></span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if ($person->notes): ?>
                    <div class="border-t border-border pt-4">
                        <p class="text-xs font-medium text-muted-foreground mb-1">Notatki</p>
                        <p class="text-sm text-foreground whitespace-pre-line"><?= htmlspecialchars($person->notes) ?></p>
                    </div>
                <?php endif; ?>

                <div class="border-t border-border pt-4">
                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/register"
                       class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-md border border-input px-4
                              text-sm font-medium text-foreground hover:bg-accent transition-colors">
                        <?php render_icon('list', 'solid', 'h-3.5 w-3.5') ?>
                        Rejestr potomków
                    </a>
                </div>

                <?php if ($canEdit): ?>
                    <div class="border-t border-border pt-4 flex flex-col gap-2">
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/edit"
                           class="inline-flex h-9 w-full items-center justify-center rounded-md border border-input px-4
                                  text-sm font-medium text-foreground hover:bg-accent transition-colors">
                            Edytuj dane
                        </a>
                        <form method="POST" action="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/delete"
                              onsubmit="return confirm('Usunąć tę osobę? Operacji nie można cofnąć.')">
                            <?= Csrf::hiddenInput() ?>
                            <button type="submit"
                                    class="inline-flex h-9 w-full items-center justify-center rounded-md px-4
                                           text-sm font-medium bg-destructive text-destructive-foreground
                                           hover:bg-destructive/90 transition-colors">
                                Usuń osobę
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Relationships + Suggestions -->
    <div class="lg:col-span-2 space-y-6">

        <?php
        /** @var array $matchSuggestions */
        $matchSuggestions = $matchSuggestions ?? [];
        ?>

        <?php if (!empty($matchSuggestions) && $canEdit): ?>
        <!-- Important #9: emit JSON jako script tag zamiast inline `x-data`
             — chroni przed 50KB+ atrybutem przy bulk GEDCOM imporcie. -->
        <script type="application/json" id="match-suggestions-data">
            <?= json_encode($matchSuggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
        </script>

        <!-- Discovery match suggestions — cross-tree / external / local fingerprint matches -->
        <div x-data="matchSuggestionsPanel()"
             class="rounded-lg border border-amber-300 bg-amber-50/60 shadow-sm">
            <div class="border-b border-amber-200 px-6 py-4 flex items-center gap-2">
                <?php render_icon('lightbulb', 'solid', 'h-4.5 w-4.5 text-amber-600') ?>
                <h2 class="text-base font-semibold text-amber-900">
                    Możliwe powiązania
                    <span class="ml-1 text-xs font-normal text-amber-700" x-text="'(' + items.length + ')'"></span>
                </h2>
            </div>

            <div class="divide-y divide-amber-200">
                <template x-for="item in items" :key="item.id">
                    <div class="px-6 py-4" x-show="!hidden[item.id]" x-transition>
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                          :class="badgeClass(item.confidence)"
                                          x-text="confidenceLabel(item.confidence)"></span>
                                    <span class="text-xs text-amber-700" x-text="item.data.sourceLabel || item.source_type"></span>
                                </div>
                                <p class="mt-1 font-medium text-amber-950">
                                    <span x-text="(item.data.firstName || '?') + ' ' + (item.data.lastName || '?')"></span>
                                    <template x-if="item.data.birthYear">
                                        <span class="text-amber-700 text-sm">
                                            (<span x-text="`ur. ${item.data.birthYear}`"></span>)
                                        </span>
                                    </template>
                                </p>
                                <template x-if="item.data.region || item.data.treeRef">
                                    <p class="mt-0.5 text-xs text-amber-700">
                                        <span x-show="item.data.region" x-text="item.data.region"></span>
                                        <span x-show="item.data.region && item.data.treeRef"> · </span>
                                        <span x-show="item.data.treeRef" x-text="item.data.treeRef"></span>
                                    </p>
                                </template>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <button type="button"
                                        @click="acceptMatch(item)"
                                        :disabled="processing[item.id]"
                                        class="inline-flex items-center gap-1 rounded-md bg-amber-600 px-3 py-1 text-xs font-medium text-white hover:bg-amber-700 disabled:opacity-50">
                                    <?php render_icon('check', 'solid', 'h-3 w-3') ?>
                                    Akceptuj
                                </button>
                                <button type="button"
                                        @click="rejectMatch(item)"
                                        :disabled="processing[item.id]"
                                        class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-white px-3 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50 disabled:opacity-50">
                                    <?php render_icon('xmark', 'solid', 'h-3 w-3') ?>
                                    Odrzuć
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="items.every(i => hidden[i.id])"
                     class="px-6 py-4 text-center text-sm text-amber-700">
                    Wszystkie sugestie zostały rozpatrzone.
                </div>
            </div>
        </div>

        <script>
        function matchSuggestionsPanel() {
            // Important #9: parsuj JSON z osobnego script tag zamiast x-data attribute
            const dataEl = document.getElementById('match-suggestions-data');
            const initialItems = dataEl ? JSON.parse(dataEl.textContent || '[]') : [];

            return {
                items: initialItems.map(item => ({
                    ...item,
                    data: typeof item.source_data === 'string'
                        ? JSON.parse(item.source_data)
                        : (item.source_data || {})
                })),
                hidden: {},
                processing: {},

                badgeClass(confidence) {
                    if (confidence >= 0.85) return 'bg-green-100 text-green-800';
                    if (confidence >= 0.65) return 'bg-amber-100 text-amber-800';
                    return 'bg-slate-100 text-slate-700';
                },

                confidenceLabel(confidence) {
                    const pct = Math.round(confidence * 100);
                    if (confidence >= 0.85) return pct + '% — wysokie';
                    if (confidence >= 0.65) return pct + '% — średnie';
                    return pct + '% — niskie';
                },

                /**
                 * Wykonuje POST z CSRF token + aktualizuje meta tag z nowym
                 * tokenem zwróconym przez backend (Csrf::verify rotuje token).
                 */
                async _postWithCsrf(url) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    const csrf = meta?.content || '';
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: '_csrf_token=' + encodeURIComponent(csrf),
                    });
                    let data = null;
                    try { data = await r.json(); } catch (e) { /* ignore parse error */ }
                    // B2: rotacja CSRF — backend zwraca nowy token, aktualizujemy meta
                    if (data && data.csrf && meta) {
                        meta.content = data.csrf;
                    }
                    if (!r.ok) {
                        const msg = (data && data.message) || ('HTTP ' + r.status);
                        throw new Error(msg);
                    }
                    return data;
                },

                async acceptMatch(item) {
                    if (this.processing[item.id]) return;
                    this.processing[item.id] = true;
                    try {
                        await this._postWithCsrf('/api/discovery/match/' + item.id + '/import');
                        this.hidden[item.id] = true;
                    } catch (e) {
                        alert('Nie udało się zaakceptować dopasowania: ' + e.message);
                    } finally {
                        this.processing[item.id] = false;
                    }
                },

                async rejectMatch(item) {
                    if (this.processing[item.id]) return;
                    this.processing[item.id] = true;
                    try {
                        await this._postWithCsrf('/api/discovery/match/' + item.id + '/reject');
                        this.hidden[item.id] = true;
                    } catch (e) {
                        alert('Nie udało się odrzucić dopasowania: ' + e.message);
                    } finally {
                        this.processing[item.id] = false;
                    }
                },
            };
        }
        </script>
        <?php endif; ?>

        <?php if (!empty($suggestions) && $canEdit): ?>
        <!-- Suggestions panel -->
        <div class="rounded-lg border border-primary/40 bg-primary/5 shadow-sm">
            <div class="border-b border-primary/20 px-6 py-4 flex items-center gap-2">
                <?php render_icon('circle-info', 'solid', 'h-4 w-4 text-primary') ?>
                <h2 class="text-base font-semibold text-foreground">Sugerowane relacje do dodania</h2>
                <span class="ml-auto text-xs text-muted-foreground">Na podstawie już istniejących powiązań</span>
            </div>

            <form method="POST"
                  action="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/suggestions">
                <?= Csrf::hiddenInput() ?>

                <div class="px-6 py-4 space-y-3">
                    <?php foreach ($suggestions as $s): ?>
                        <?php
                        $tp      = $s['targetPerson'];
                        // FORM-interpretation: ('parent', A, B) = "B is A's parent",
                        // ('child', A, B) = "B is A's child" — label describes role of $tp.
                        $typeLabel = match($s['type']) {
                            'parent'  => 'rodzic',
                            'child'   => 'dziecko',
                            'sibling' => 'rodzeństwo',
                            'spouse'  => 'małżonek/małżonka',
                            'partner' => 'partner/partnerka',
                            default   => $s['type'],
                        };
                        $initials = mb_strtoupper(
                            mb_substr($tp->firstName, 0, 1) . mb_substr($tp->lastName, 0, 1)
                        );
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
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox"
                                   name="suggestions[]"
                                   value="<?= $value ?>"
                                   checked
                                   class="h-4 w-4 rounded border-border text-primary">
                            <!-- Avatar -->
                            <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-medium text-muted-foreground"
                                 style="background:<?= $gColor ?>">
                                <?php if ($tp->photoPath): ?>
                                    <img src="/media.php?path=<?= urlencode($tp->photoPath) ?>"
                                         alt="" class="h-8 w-8 rounded-full object-cover">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials) ?>
                                <?php endif; ?>
                            </div>
                            <!-- Name + relation -->
                            <div class="flex-1 min-w-0">
                                <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($tp->id) ?>"
                                   class="text-sm font-medium text-foreground hover:underline"
                                   onclick="event.stopPropagation()">
                                    <?= htmlspecialchars($tp->fullName()) ?>
                                </a>
                                <p class="text-xs text-muted-foreground">
                                    <span class="font-medium"><?= htmlspecialchars($typeLabel) ?></span>
                                    — <?= htmlspecialchars($s['reason']) ?>
                                </p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-primary/20 px-6 py-3 flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex h-9 items-center gap-2 rounded-md px-4 text-sm font-medium
                                   bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                        <?php render_icon('check', 'solid', 'h-3.5 w-3.5') ?>
                        Dodaj zaznaczone
                    </button>
                    <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
                       class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                        Pomiń →
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Relationships panel -->
        <div class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-card-foreground">Relacje rodzinne</h2>
                <?php if ($canEdit): ?>
                    <div class="flex items-center gap-2">
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/new"
                           class="inline-flex h-8 items-center gap-1.5 rounded-md px-3
                                  text-xs font-medium bg-primary text-primary-foreground
                                  hover:bg-primary/90 transition-colors">
                            <?php render_icon('plus', 'solid', 'h-3 w-3') ?>
                            Dodaj osobę
                        </a>
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/relationships/new"
                           class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input px-3
                                  text-xs font-medium text-foreground hover:bg-accent transition-colors">
                            <?php render_icon('plus', 'solid', 'h-3 w-3') ?>
                            Dodaj relację
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($relationships)): ?>
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-sm text-muted-foreground">Brak relacji rodzinnych.</p>
                    <?php if ($canEdit): ?>
                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/relationships/new"
                           class="mt-4 text-sm text-primary hover:underline">
                            Dodaj pierwszą relację
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php
                $grouped = [];
                foreach ($relationships as $rel) {
                    $grouped[$rel->type][] = $rel;
                }
                $order = ['parent', 'child', 'spouse', 'partner', 'sibling'];
                ?>
                <div class="divide-y divide-border">
                    <?php foreach ($order as $type): ?>
                        <?php if (!isset($grouped[$type])) continue; ?>
                        <div class="px-6 py-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                                <?= match($type) {
                                    'parent'  => 'Rodzice',
                                    'child'   => 'Dzieci',
                                    'spouse'  => 'Małżonkowie',
                                    'partner' => 'Partnerzy',
                                    'sibling' => 'Rodzeństwo',
                                    default   => $type,
                                } ?>
                            </h3>
                            <ul class="space-y-2">
                                <?php foreach ($grouped[$type] as $rel): ?>
                                    <li class="flex items-center justify-between gap-3">
                                        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($rel->personBId) ?>"
                                           class="flex items-center gap-2.5 text-sm text-foreground hover:underline">
                                            <div class="h-7 w-7 rounded-full bg-muted flex items-center justify-center flex-shrink-0 text-xs font-medium text-muted-foreground">
                                                <?= htmlspecialchars(mb_strtoupper(mb_substr($rel->relatedFirstName ?? '?', 0, 1) . mb_substr($rel->relatedLastName ?? '', 0, 1))) ?>
                                            </div>
                                            <?= htmlspecialchars($rel->relatedFullName() ?: 'Nieznana osoba') ?>
                                        </a>
                                        <?php if ($canEdit): ?>
                                            <form method="POST"
                                                  action="/trees/<?= htmlspecialchars($tree->id) ?>/relationships/<?= htmlspecialchars($rel->id) ?>/delete"
                                                  onsubmit="return confirm('Usunąć tę relację?')">
                                                <?= Csrf::hiddenInput() ?>
                                                <button type="submit"
                                                        class="text-xs text-muted-foreground hover:text-destructive transition-colors">
                                                    Usuń
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
