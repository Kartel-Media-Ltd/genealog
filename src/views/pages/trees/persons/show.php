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
        <!-- Stored DB suggestions (z MatchingService) — zachowane dla zgodności wstecznej -->
        <script type="application/json" id="match-suggestions-data">
            <?= json_encode($matchSuggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
        </script>

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
                                    <template x-if="item.data.isDead || item.data.deathYear">
                                        <span class="mr-0.5 text-amber-700" title="Osoba zmarła">&#x271D;</span>
                                    </template>
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
                        window.showModal('Nie udało się zaakceptować dopasowania: ' + e.message, 'Błąd', 'error');
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
                        window.showModal('Nie udało się odrzucić dopasowania: ' + e.message, 'Błąd', 'error');
                    } finally {
                        this.processing[item.id] = false;
                    }
                },
            };
        }
        </script>
        <?php endif; ?>

        <?php if ($canEdit && !$person->isLiving): ?>
        <!-- Panel live discovery — autosearch na otwarciu strony (dla osób nieżyjących) -->
        <div x-data="liveDiscoveryPanel('<?= htmlspecialchars($tree->id) ?>', '<?= htmlspecialchars($person->id) ?>', <?= $hasPendingCrossTreeRequest ? 'true' : 'false' ?>, <?= htmlspecialchars(json_encode($crossTreeLinkStatuses, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= $isGlobalAdmin ? 'true' : 'false' ?>)"
             x-init="autoSearch()"
             class="rounded-lg border border-amber-200 bg-amber-50/40 shadow-sm">

            <div class="border-b border-amber-100 px-5 py-3.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <?php render_icon('magnifying-glass', 'solid', 'h-4 w-4 text-amber-600') ?>
                    <h2 class="text-sm font-semibold text-amber-900">Możliwe powiązania</h2>
                    <span x-show="!loading && totalResults > 0" x-cloak
                          class="inline-flex items-center rounded-full bg-amber-200 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                          x-text="totalResults"></span>
                    <span x-show="loading" x-cloak class="text-xs text-amber-600">
                        <?php render_icon('spinner', 'solid', 'fa-spin h-3 w-3 mr-1') ?>Szukam…
                    </span>
                </div>
                <button type="button" @click="autoSearch()"
                        :disabled="loading"
                        class="text-xs text-amber-600 hover:text-amber-800 disabled:opacity-50">
                    Odśwież
                </button>
            </div>

            <!-- Brak wyników -->
            <div x-show="!loading && totalResults === 0 && searched" x-transition
                 class="px-5 py-5 text-center text-sm text-amber-700">
                Nie znaleziono osób z podobnym profilem w innych drzewach.
                <p class="mt-1 text-xs text-amber-600">
                    Aby pojawić się w wyszukiwaniu innych użytkowników, ustaw widoczność osoby na <strong>Anonimowe</strong>
                    i włącz indeksowanie w <a href="/trees/<?= htmlspecialchars($tree->id) ?>/settings/discovery" class="underline">ustawieniach drzewa</a>.
                </p>
            </div>

            <!-- Wyniki cross-tree -->
            <template x-if="!loading && crossTree.length > 0">
                <div class="px-5 py-3 border-b border-amber-100 last:border-0">
                    <h3 class="text-xs font-semibold text-amber-800 mb-2.5">
                        Z innych drzew (<span x-text="crossTree.length"></span>)
                        <span class="font-normal text-amber-600">— anonimowe, zgodne z RODO</span>
                    </h3>
                    <div class="space-y-2">
                        <template x-for="m in crossTree" :key="'ct-' + m.sourceId">
                            <div class="rounded-md border border-amber-200 bg-white px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-slate-800">
                                            <span class="mr-0.5 text-slate-400">†</span>
                                            <span x-text="m.firstName + ' ' + m.lastName"></span>
                                            <span x-show="m.birthYear" class="text-slate-500 text-xs">
                                                (ur. <span x-text="m.birthYear"></span>)
                                            </span>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            <span x-text="m.treeRef"></span>
                                            <span x-show="m.region"> · <span x-text="m.region"></span></span>
                                            <span class="ml-1.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                                  :class="m.confidence >= 0.85 ? 'bg-green-100 text-green-700' : m.confidence >= 0.65 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
                                                  x-text="Math.round(m.confidence * 100) + '% zgodności'"></span>
                                        </p>
                                    </div>
                                    <!-- Status per-osoba -->
                                    <template x-if="sentRequests[m.sourceId]?.status === 'pending'">
                                        <div class="shrink-0 text-xs text-amber-600 flex items-center gap-1">
                                            <?php render_icon('clock', 'solid', 'h-3 w-3') ?>
                                            Oczekuje
                                        </div>
                                    </template>
                                    <template x-if="sentRequests[m.sourceId]?.status === 'rejected'">
                                        <div class="shrink-0 flex items-center gap-1.5">
                                            <span class="text-xs text-red-500 flex items-center gap-1">
                                                <?php render_icon('xmark', 'solid', 'h-3 w-3') ?>
                                                Odrzucono
                                            </span>
                                            <template x-if="isAdmin">
                                                <button type="button" @click="openRequestModal(m)"
                                                        title="Admin: wyślij prośbę ponownie (nadpisze odrzucone)"
                                                        class="inline-flex h-6 items-center gap-1 rounded px-2 text-[11px] font-medium border border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors">
                                                    <?php render_icon('rotate-right', 'solid', 'h-3 w-3') ?>
                                                    Ponów (admin)
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="sentRequests[m.sourceId]?.status === 'cancelled'">
                                        <div class="shrink-0 flex items-center gap-1.5">
                                            <span class="text-xs text-slate-400 flex items-center gap-1">
                                                <?php render_icon('ban', 'solid', 'h-3 w-3') ?>
                                                Anulowano
                                            </span>
                                            <template x-if="isAdmin">
                                                <button type="button" @click="openRequestModal(m)"
                                                        title="Admin: wyślij prośbę ponownie (nadpisze anulowane)"
                                                        class="inline-flex h-6 items-center gap-1 rounded px-2 text-[11px] font-medium border border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors">
                                                    <?php render_icon('rotate-right', 'solid', 'h-3 w-3') ?>
                                                    Ponów (admin)
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="sentRequests[m.sourceId]?.status === 'accepted'">
                                        <div class="shrink-0 text-xs text-green-600 flex items-center gap-1">
                                            <?php render_icon('check', 'solid', 'h-3 w-3') ?>
                                            Połączono
                                        </div>
                                    </template>
                                    <!-- Globalna blokada (pending do innej osoby) -->
                                    <template x-if="!sentRequests[m.sourceId] && hasPendingRequest">
                                        <div class="shrink-0 text-xs text-amber-600 flex items-center gap-1"
                                             title="Masz już oczekującą prośbę. Poczekaj na odpowiedź lub anuluj ją w /connections.">
                                            <?php render_icon('clock', 'solid', 'h-3 w-3') ?>
                                            Prośba oczekuje
                                        </div>
                                    </template>
                                    <!-- Przycisk (gdy brak statusu i brak globalnie pending) -->
                                    <template x-if="!sentRequests[m.sourceId] && !hasPendingRequest">
                                        <button type="button"
                                                @click="openRequestModal(m)"
                                                class="shrink-0 inline-flex h-7 items-center gap-1 rounded-md border border-purple-300 bg-purple-50 px-2.5 text-xs font-medium text-purple-700 hover:bg-purple-100 transition-colors">
                                            <?php render_icon('link', 'solid', 'h-3 w-3') ?>
                                            Wyślij prośbę
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Wyniki local -->
            <template x-if="!loading && local.length > 0">
                <div class="px-5 py-3 border-b border-amber-100 last:border-0">
                    <h3 class="text-xs font-semibold text-amber-800 mb-2.5">
                        Z Twoich innych drzew (<span x-text="local.length"></span>)
                    </h3>
                    <div class="space-y-2">
                        <template x-for="m in local" :key="'l-' + m.sourceId">
                            <div class="rounded-md border border-amber-200 bg-white px-4 py-2.5 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-800 truncate">
                                        <span x-text="m.firstName + ' ' + m.lastName"></span>
                                        <span x-show="m.birthYear" class="text-xs text-slate-500">(ur. <span x-text="m.birthYear"></span>)</span>
                                    </p>
                                    <p class="text-xs text-slate-500 truncate" x-text="m.treeName"></p>
                                </div>
                                <span class="text-xs text-slate-400 shrink-0" x-text="Math.round(m.confidence * 100) + '%'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Modal do wysyłania prośby cross-tree -->
            <div x-show="requestModal.open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 @keydown.escape.window="requestModal.open = false">
                <div class="absolute inset-0 bg-black/40" @click="requestModal.open = false"></div>
                <div class="relative w-full max-w-sm rounded-xl border bg-white shadow-2xl p-5 space-y-3" @click.stop>
                    <h3 class="text-sm font-semibold text-slate-800">Wyślij prośbę o powiązanie</h3>
                    <p class="text-xs text-slate-600">
                        Łączysz: <strong><?= htmlspecialchars($person->firstName . ' ' . $person->lastName) ?></strong>
                        ↔ <strong x-text="requestModal.targetName"></strong>
                    </p>
                    <div>
                        <label class="text-xs font-medium text-slate-700 block mb-1">Poziom dostępu do danych</label>
                        <select x-model="requestModal.visibilityLevel"
                                class="block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-purple-400">
                            <option value="shared_basic">Podstawowe (imię, nazwisko, rok ur., płeć)</option>
                            <option value="shared_full">Pełne (wszystkie dane poza notatkami)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 block mb-1">Wiadomość (opcjonalnie)</label>
                        <textarea x-model="requestModal.note" rows="2" maxlength="500"
                                  placeholder="Np. Ta osoba to babcia ze strony ojca..."
                                  class="block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none"></textarea>
                    </div>
                    <p x-show="requestModal.error" x-text="requestModal.error" class="text-xs text-red-600"></p>
                    <div class="flex gap-2 pt-1">
                        <button type="button"
                                @click="sendRequest()"
                                :disabled="requestModal.sending"
                                class="flex-1 inline-flex justify-center items-center gap-1.5 rounded-md bg-purple-600 px-4 py-2 text-xs font-medium text-white hover:bg-purple-700 disabled:opacity-50 transition-colors">
                            <span x-show="!requestModal.sending">Wyślij prośbę</span>
                            <span x-show="requestModal.sending">Wysyłam…</span>
                        </button>
                        <button type="button" @click="requestModal.open = false"
                                class="rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                            Anuluj
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function liveDiscoveryPanel(treeId, personId, hasPendingRequest, linkStatuses, isAdmin) {
            return {
                treeId,
                personId,
                hasPendingRequest,
                isAdmin: !!isAdmin,
                loading: false,
                searched: false,
                local:     [],
                crossTree: [],
                external:  [],
                sentRequests: linkStatuses || {},
                requestModal: {
                    open: false,
                    targetName: '',
                    targetGpiId: '',
                    visibilityLevel: 'shared_basic',
                    note: '',
                    sending: false,
                    error: '',
                },

                get totalResults() {
                    return this.local.length + this.crossTree.length + this.external.length;
                },

                async autoSearch() {
                    this.loading = true;
                    const params = new URLSearchParams({
                        treeId:     this.treeId,
                        firstName:  '<?= addslashes($person->firstName) ?>',
                        lastName:   '<?= addslashes($person->lastName) ?>',
                        gender:     '<?= htmlspecialchars($person->gender) ?>',
                    });
                    <?php if ($person->birthDate): ?>
                    params.append('birthYear', '<?= htmlspecialchars(substr($person->birthDate, 0, 4)) ?>');
                    <?php endif; ?>
                    <?php if ($person->birthPlace): ?>
                    params.append('birthPlace', '<?= addslashes($person->birthPlace) ?>');
                    <?php endif; ?>
                    <?php if ($person->deathPlace): ?>
                    params.append('deathPlace', '<?= addslashes($person->deathPlace) ?>');
                    <?php endif; ?>

                    try {
                        const resp = await fetch('/api/discovery/search?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!resp.ok) throw new Error('HTTP ' + resp.status);
                        const data = await resp.json();
                        this.local     = data.local     || [];
                        this.crossTree = data.crossTree || [];
                        this.external  = data.external  || [];

                        // Auto-open request modal if redirected from /persons/new with ?connect_gpi=
                        const connectGpi = new URLSearchParams(window.location.search).get('connect_gpi');
                        if (connectGpi) {
                            const match = this.crossTree.find(m => m.sourceId === connectGpi);
                            if (match) {
                                this.$nextTick(() => this.openRequestModal(match));
                            }
                            // Clean up URL without reloading
                            const cleanUrl = window.location.pathname;
                            window.history.replaceState(null, '', cleanUrl);
                        }
                    } catch (e) {
                        console.warn('Discovery search failed:', e);
                    } finally {
                        this.loading = false;
                        this.searched = true;
                    }
                },

                openRequestModal(match) {
                    const prev = this.sentRequests[match.sourceId];
                    this.requestModal.targetName      = match.firstName + ' ' + match.lastName + (match.birthYear ? ' (ur. ' + match.birthYear + ')' : '');
                    this.requestModal.targetGpiId     = match.sourceId;
                    this.requestModal.visibilityLevel = prev?.visibilityLevel || 'shared_basic';
                    this.requestModal.note            = prev?.note || '';
                    this.requestModal.error           = '';
                    this.requestModal.sending         = false;
                    this.requestModal.open            = true;
                },

                async sendRequest() {
                    if (this.requestModal.sending) return;
                    this.requestModal.sending = true;
                    this.requestModal.error   = '';
                    try {
                        const token = document.querySelector('input[name="_csrf_token"]')?.value || '';
                        const res = await fetch('/api/connections/request', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                _csrf_token:       token,
                                requesterPersonId: this.personId,
                                targetGpiId:       this.requestModal.targetGpiId,
                                visibilityLevel:   this.requestModal.visibilityLevel,
                                note:              this.requestModal.note,
                            }),
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.error || 'Błąd serwera');
                        this.sentRequests[this.requestModal.targetGpiId] = {
                            status: 'pending',
                            note: this.requestModal.note,
                            visibilityLevel: this.requestModal.visibilityLevel,
                        };
                        this.hasPendingRequest = true;
                        this.requestModal.open = false;
                    } catch (e) {
                        this.requestModal.error = e.message;
                    } finally {
                        this.requestModal.sending = false;
                    }
                },
            };
        }
        </script>
        <?php endif; // $canEdit && !$person->isLiving ?>

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
                                            <?php if (!$rel->relatedIsLiving): ?><span class="text-muted-foreground mr-0.5" aria-hidden="true">†</span><?php endif; ?><?= htmlspecialchars($rel->relatedFullName() ?: 'Nieznana osoba') ?><?php
                                                $rAge  = DateHelper::ageInYears($rel->relatedBirthDate, $rel->relatedDeathDate);
                                                $rYear = $rel->relatedBirthDate ? (int) substr($rel->relatedBirthDate, 0, 4) : null;
                                                if ($rAge !== null || $rYear !== null):
                                            ?> <span class="text-muted-foreground font-normal text-xs">(<?php if ($rAge !== null): ?>l.&nbsp;<?= $rAge ?><?php endif; ?><?php if ($rAge !== null && $rYear !== null): ?>, <?php endif; ?><?php if ($rYear !== null): ?>ur.&nbsp;<?= $rYear ?><?php endif; ?>)</span><?php endif; ?>
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
