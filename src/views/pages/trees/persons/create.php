<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\Tree $tree */
?>

<div class="mx-auto max-w-2xl">

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
        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons"
           class="hover:text-foreground transition-colors">Osoby</a>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
        <span class="text-foreground font-medium">Nowa osoba</span>
    </nav>

    <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h1 class="text-lg font-semibold text-card-foreground">Dodaj osobę</h1>
        </div>

        <form method="POST" action="/trees/<?= htmlspecialchars($tree->id) ?>/persons"
              class="p-6 space-y-6" novalidate
              x-data="personDiscovery('<?= htmlspecialchars($tree->id) ?>')">
            <?= Csrf::hiddenInput() ?>

            <!-- Sekcja 1: Dane podstawowe -->
            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-foreground">Dane podstawowe</legend>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="first_name" class="block text-sm font-medium text-foreground">
                            Imię <span class="text-destructive" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" required maxlength="100"
                               x-ref="firstName"
                               @input.debounce.400ms="triggerSearch()"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                    <div class="space-y-1.5">
                        <label for="last_name" class="block text-sm font-medium text-foreground">
                            Nazwisko <span class="text-destructive" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" required maxlength="150"
                               x-ref="lastName"
                               @input.debounce.400ms="triggerSearch()"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                </div>

                <!-- Panel autosuggest — pojawia się gdy są dopasowania -->
                <div x-show="showPanel" x-cloak x-transition
                     class="mt-4 rounded-lg border border-primary/30 bg-primary/5 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            Możliwe dopasowania
                            (<span x-text="totalResults"></span>)
                        </h3>
                        <button type="button" @click="closePanel()"
                                class="text-xs text-muted-foreground hover:text-foreground">
                            Ukryj
                        </button>
                    </div>

                    <!-- Loading -->
                    <div x-show="loading" class="text-sm text-muted-foreground">Szukam…</div>

                    <!-- Local -->
                    <div x-show="!loading && results.local.length > 0" class="mb-3">
                        <h4 class="text-xs font-semibold text-foreground mb-1.5">
                            Z Twoich drzew (<span x-text="results.local.length"></span>)
                        </h4>
                        <div class="space-y-1.5">
                            <template x-for="m in results.local" :key="'l-' + m.sourceId">
                                <div class="flex items-center justify-between gap-2 rounded-md border border-border bg-card px-3 py-2">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-foreground truncate">
                                            <span x-text="m.firstName + ' ' + m.lastName"></span>
                                            <span x-show="m.birthYear" class="text-xs text-muted-foreground">
                                                (ur. <span x-text="m.birthYear"></span>)
                                            </span>
                                        </div>
                                        <div class="text-xs text-muted-foreground truncate">
                                            <span x-text="m.treeName"></span>
                                            <span x-show="m.birthPlace" class="ml-1">· <span x-text="m.birthPlace"></span></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-xs text-muted-foreground"
                                              x-text="Math.round(m.confidence * 100) + '%'"></span>
                                        <button type="button" @click="useMatch(m)"
                                                class="inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-xs font-medium text-primary-foreground hover:bg-primary/90">
                                            Użyj danych
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Cross-tree -->
                    <div x-show="!loading && results.crossTree.length > 0" class="mb-3">
                        <h4 class="text-xs font-semibold text-foreground mb-1.5">
                            Z innych drzew (<span x-text="results.crossTree.length"></span>)
                            <span class="font-normal text-muted-foreground">— anonimowe, zgodne z RODO</span>
                        </h4>
                        <div class="space-y-1.5">
                            <template x-for="m in results.crossTree" :key="'c-' + m.sourceId">
                                <div class="flex items-center justify-between gap-2 rounded-md border border-border bg-card px-3 py-2">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-foreground truncate">
                                            <span x-text="m.firstName + ' ' + m.lastName"></span>
                                            <span x-show="m.birthYear" class="text-xs text-muted-foreground">
                                                (ur. <span x-text="m.birthYear"></span>)
                                            </span>
                                        </div>
                                        <div class="text-xs text-muted-foreground truncate">
                                            <span x-text="m.treeRef"></span>
                                            <span x-show="m.region" class="ml-1">· <span x-text="m.region"></span></span>
                                        </div>
                                    </div>
                                    <span class="text-xs text-muted-foreground shrink-0"
                                          x-text="Math.round(m.confidence * 100) + '%'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- External (Faza 7) -->
                    <div x-show="!loading && results.external.length > 0" class="mb-3">
                        <h4 class="text-xs font-semibold text-foreground mb-1.5">
                            Z zewnętrznych baz (<span x-text="results.external.length"></span>)
                        </h4>
                        <div class="space-y-1.5">
                            <template x-for="m in results.external" :key="'e-' + m.sourceId">
                                <div class="flex items-center justify-between gap-2 rounded-md border border-border bg-card px-3 py-2">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-foreground truncate">
                                            <span x-text="m.firstName + ' ' + m.lastName"></span>
                                            <span x-show="m.birthYear" class="text-xs text-muted-foreground">
                                                (ur. <span x-text="m.birthYear"></span>)
                                            </span>
                                        </div>
                                    </div>
                                    <button type="button" @click="useMatch(m)"
                                            class="inline-flex h-7 items-center rounded-md border border-input px-2.5 text-xs font-medium text-foreground hover:bg-accent">
                                        Użyj danych
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div x-show="!loading && totalResults === 0" class="text-sm text-muted-foreground">
                        Brak dopasowań.
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="maiden_name" class="block text-sm font-medium text-foreground">
                        Nazwisko panieńskie <span class="text-xs text-muted-foreground font-normal">(opcjonalnie)</span>
                    </label>
                    <input type="text" id="maiden_name" name="maiden_name" maxlength="150"
                           class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                  text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                  focus:border-transparent">
                </div>

                <div class="space-y-1.5">
                    <label for="gender" class="block text-sm font-medium text-foreground">Płeć</label>
                    <select id="gender" name="gender"
                            class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                   text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                   focus:border-transparent">
                        <option value="unknown">Nieznana</option>
                        <option value="male">Mężczyzna</option>
                        <option value="female">Kobieta</option>
                    </select>
                </div>
            </fieldset>

            <!-- Sekcja 2: Narodziny -->
            <fieldset class="space-y-4 border-t border-border pt-5">
                <legend class="text-sm font-semibold text-foreground">Narodziny</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="birth_date" class="block text-sm font-medium text-foreground">
                            Data urodzenia <span class="text-xs text-muted-foreground font-normal">(RRRR lub RRRR-MM-DD)</span>
                        </label>
                        <input type="text" id="birth_date" name="birth_date" maxlength="10"
                               placeholder="np. 1945 lub 1945-06-15"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm placeholder:text-muted-foreground
                                      focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent">
                    </div>
                    <div class="space-y-1.5">
                        <label for="birth_place" class="block text-sm font-medium text-foreground">Miejsce urodzenia</label>
                        <input type="text" id="birth_place" name="birth_place" maxlength="255"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                </div>
            </fieldset>

            <!-- Sekcja 3: Śmierć -->
            <fieldset class="space-y-4 border-t border-border pt-5" x-show="!isLiving">
                <legend class="text-sm font-semibold text-foreground">Śmierć</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="death_date" class="block text-sm font-medium text-foreground">
                            Data śmierci <span class="text-xs text-muted-foreground font-normal">(RRRR lub RRRR-MM-DD)</span>
                        </label>
                        <input type="text" id="death_date" name="death_date" maxlength="10"
                               placeholder="np. 2010 lub 2010-03-22"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm placeholder:text-muted-foreground
                                      focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent">
                    </div>
                    <div class="space-y-1.5">
                        <label for="death_place" class="block text-sm font-medium text-foreground">Miejsce śmierci</label>
                        <input type="text" id="death_place" name="death_place" maxlength="255"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                </div>
            </fieldset>

            <!-- Sekcja 4: Prywatność (RODO) -->
            <fieldset class="space-y-4 border-t border-border pt-5">
                <legend class="text-sm font-semibold text-foreground">Prywatność</legend>

                <div class="flex items-start gap-3 rounded-md border border-border bg-muted/40 p-4">
                    <input type="checkbox" id="is_living" name="is_living" value="1" checked
                           x-model="isLiving"
                           @change="if(isLiving) { visibility = 'private' }"
                           class="mt-0.5 h-4 w-4 rounded border-border text-primary
                                  focus:ring-2 focus:ring-ring focus:ring-offset-2">
                    <div>
                        <label for="is_living" class="block text-sm font-medium text-foreground cursor-pointer">
                            Osoba żyjąca
                        </label>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Żyjące osoby są chronione zgodnie z RODO.
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="visibility" class="block text-sm font-medium text-foreground">Widoczność</label>
                    <select id="visibility" name="visibility"
                            x-model="visibility"
                            :disabled="isLiving"
                            class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                   text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                   focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="private">Prywatne (tylko Ty i zaproszeni)</option>
                        <option value="public">Publiczne (widoczne w wyszukiwaniu)</option>
                        <option value="anonymous">Anonimowe (tylko w globalnym indeksie)</option>
                    </select>
                    <p x-show="isLiving" class="text-xs text-amber-600">
                        Żyjące osoby są zawsze prywatne (RODO).
                    </p>
                </div>
            </fieldset>

            <!-- Sekcja 5: Notatki -->
            <fieldset class="border-t border-border pt-5">
                <div class="space-y-1.5">
                    <label for="notes" class="block text-sm font-medium text-foreground">
                        Notatki <span class="text-xs text-muted-foreground font-normal">(opcjonalnie)</span>
                    </label>
                    <textarea id="notes" name="notes" rows="4" maxlength="5000"
                              class="w-full px-3 py-2 rounded-md border border-border bg-background
                                     text-foreground text-sm placeholder:text-muted-foreground resize-y
                                     focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                              placeholder="Dodatkowe informacje o osobie..."></textarea>
                </div>
            </fieldset>

            <div class="flex items-center justify-end gap-3 border-t border-border pt-5">
                <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons"
                   class="inline-flex h-10 items-center rounded-md border border-input px-4
                          text-sm font-medium text-foreground hover:bg-accent transition-colors
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Anuluj
                </a>
                <button type="submit"
                        class="inline-flex h-10 items-center rounded-md px-4 text-sm font-medium
                               bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Dodaj osobę
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function personDiscovery(treeId) {
    return {
        // Istniejący state formularza (zachowany z poprzedniej wersji)
        isLiving: true,
        visibility: 'private',

        // Discovery state
        treeId: treeId,
        loading: false,
        showPanel: false,
        results: { local: [], crossTree: [], external: [] },
        lastQuery: '',

        get totalResults() {
            return this.results.local.length
                 + this.results.crossTree.length
                 + this.results.external.length;
        },

        triggerSearch() {
            const first = this.$refs.firstName.value.trim();
            const last  = this.$refs.lastName.value.trim();

            // Minimum: 2 znaki w co najmniej jednym polu
            if (first.length < 2 && last.length < 2) {
                this.showPanel = false;
                return;
            }

            const query = first + '|' + last;
            if (query === this.lastQuery && this.showPanel) {
                return;
            }
            this.lastQuery = query;

            this.fetchMatches(first, last);
        },

        async fetchMatches(firstName, lastName) {
            this.loading = true;
            this.showPanel = true;

            const params = new URLSearchParams({
                treeId: this.treeId,
                firstName: firstName,
                lastName: lastName,
            });

            try {
                const resp = await fetch('/api/discovery/search?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status);
                }
                this.results = await resp.json();
                if (this.totalResults === 0) {
                    // Nie pokazuj pustego panelu — schowaj po 1s
                    setTimeout(() => {
                        if (this.totalResults === 0) this.showPanel = false;
                    }, 1000);
                }
            } catch (e) {
                console.warn('Discovery search failed:', e);
                this.showPanel = false;
            } finally {
                this.loading = false;
            }
        },

        useMatch(match) {
            // Wypełnij pola formularza danymi z match
            const setIfEmpty = (id, value) => {
                const el = document.getElementById(id);
                if (el && !el.value && value != null && value !== '') {
                    el.value = value;
                }
            };

            setIfEmpty('first_name', match.firstName);
            setIfEmpty('last_name',  match.lastName);
            if (match.birthYear) setIfEmpty('birth_date', String(match.birthYear));
            if (match.birthPlace) setIfEmpty('birth_place', match.birthPlace);
            if (match.deathYear) setIfEmpty('death_date', String(match.deathYear));

            if (match.gender) {
                const genderEl = document.getElementById('gender');
                if (genderEl && genderEl.value === 'unknown') {
                    genderEl.value = match.gender;
                }
            }

            this.closePanel();
        },

        closePanel() {
            this.showPanel = false;
        },
    };
}
</script>
