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
              x-data="{ isLiving: true, visibility: 'private' }">
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
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                    <div class="space-y-1.5">
                        <label for="last_name" class="block text-sm font-medium text-foreground">
                            Nazwisko <span class="text-destructive" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" required maxlength="150"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
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
