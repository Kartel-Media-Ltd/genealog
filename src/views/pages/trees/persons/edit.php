<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../../atoms/icon.php';

/** @var \App\Models\Tree $tree */
/** @var \App\Models\Person $person */
?>

<div class="mx-auto max-w-2xl">

    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
        <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
        <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
        <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
           class="hover:text-foreground transition-colors"><?= htmlspecialchars($tree->name) ?></a>
        <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
        <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
           class="hover:text-foreground transition-colors"><?= htmlspecialchars($person->fullName()) ?></a>
        <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
        <span class="text-foreground font-medium">Edytuj</span>
    </nav>

    <!-- Edit form -->
    <div class="rounded-lg border border-border bg-card shadow-sm mb-6">
        <div class="border-b border-border px-6 py-4">
            <h1 class="text-lg font-semibold text-card-foreground">Edytuj dane osoby</h1>
        </div>

        <form method="POST" action="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/edit"
              class="p-6 space-y-6" novalidate
              x-data="{ isLiving: <?= $person->isLiving ? 'true' : 'false' ?>, visibility: '<?= htmlspecialchars($person->visibility) ?>' }">
            <?= Csrf::hiddenInput() ?>

            <!-- Dane podstawowe -->
            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-foreground">Dane podstawowe</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="first_name" class="block text-sm font-medium text-foreground">
                            Imię <span class="text-destructive" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" required maxlength="100"
                               value="<?= htmlspecialchars($person->firstName) ?>"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                    <div class="space-y-1.5">
                        <label for="last_name" class="block text-sm font-medium text-foreground">
                            Nazwisko <span class="text-destructive" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" required maxlength="150"
                               value="<?= htmlspecialchars($person->lastName) ?>"
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
                           value="<?= htmlspecialchars($person->maidenName ?? '') ?>"
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
                        <option value="unknown" <?= $person->gender === 'unknown' ? 'selected' : '' ?>>Nieznana</option>
                        <option value="male"    <?= $person->gender === 'male'    ? 'selected' : '' ?>>Mężczyzna</option>
                        <option value="female"  <?= $person->gender === 'female'  ? 'selected' : '' ?>>Kobieta</option>
                    </select>
                </div>
            </fieldset>

            <!-- Narodziny -->
            <fieldset class="space-y-4 border-t border-border pt-5">
                <legend class="text-sm font-semibold text-foreground">Narodziny</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="birth_date" class="block text-sm font-medium text-foreground">Data urodzenia</label>
                        <input type="text" id="birth_date" name="birth_date" maxlength="10"
                               value="<?= htmlspecialchars($person->birthDate ?? '') ?>"
                               placeholder="np. 1945 lub 1945-06-15"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm placeholder:text-muted-foreground
                                      focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent">
                    </div>
                    <div class="space-y-1.5">
                        <label for="birth_place" class="block text-sm font-medium text-foreground">Miejsce urodzenia</label>
                        <input type="text" id="birth_place" name="birth_place" maxlength="255"
                               value="<?= htmlspecialchars($person->birthPlace ?? '') ?>"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                </div>
            </fieldset>

            <!-- Śmierć -->
            <fieldset class="space-y-4 border-t border-border pt-5" x-show="!isLiving">
                <legend class="text-sm font-semibold text-foreground">Śmierć</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="death_date" class="block text-sm font-medium text-foreground">Data śmierci</label>
                        <input type="text" id="death_date" name="death_date" maxlength="10"
                               value="<?= htmlspecialchars($person->deathDate ?? '') ?>"
                               placeholder="np. 2010 lub 2010-03-22"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm placeholder:text-muted-foreground
                                      focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent">
                    </div>
                    <div class="space-y-1.5">
                        <label for="death_place" class="block text-sm font-medium text-foreground">Miejsce śmierci</label>
                        <input type="text" id="death_place" name="death_place" maxlength="255"
                               value="<?= htmlspecialchars($person->deathPlace ?? '') ?>"
                               class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                                      text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                                      focus:border-transparent">
                    </div>
                </div>
            </fieldset>

            <!-- Prywatność -->
            <fieldset class="space-y-4 border-t border-border pt-5">
                <legend class="text-sm font-semibold text-foreground">Prywatność</legend>
                <div class="flex items-start gap-3 rounded-md border border-border bg-muted/40 p-4">
                    <input type="checkbox" id="is_living" name="is_living" value="1"
                           <?= $person->isLiving ? 'checked' : '' ?>
                           x-model="isLiving"
                           @change="if(isLiving) { visibility = 'private' }"
                           class="mt-0.5 h-4 w-4 rounded border-border text-primary
                                  focus:ring-2 focus:ring-ring focus:ring-offset-2">
                    <div>
                        <label for="is_living" class="block text-sm font-medium text-foreground cursor-pointer">
                            Osoba żyjąca
                        </label>
                        <p class="mt-0.5 text-xs text-muted-foreground">Żyjące osoby są chronione zgodnie z RODO.</p>
                    </div>
                </div>
                <div class="space-y-2">
                    <p class="text-sm font-medium text-foreground">Widoczność</p>

                    <!-- Blokada dla żyjących -->
                    <p x-show="isLiving" x-cloak
                       class="text-xs text-amber-600 flex items-center gap-1.5">
                        <i class="fa-solid fa-triangle-exclamation fa-fw"></i>
                        Żyjące osoby są zawsze prywatne zgodnie z RODO — widoczność zostanie ustawiona automatycznie.
                    </p>

                    <!-- Ukryty input — zawsze wysyła wartość z Alpine (działa też gdy radio disabled) -->
                    <input type="hidden" name="visibility" :value="visibility">

                    <div class="space-y-2" :class="isLiving ? 'opacity-40 pointer-events-none select-none' : ''">

                        <!-- Prywatne -->
                        <label class="flex items-start gap-3 p-3 rounded-md border cursor-pointer transition-colors"
                               :class="visibility === 'private'
                                   ? 'border-foreground/40 bg-muted/60'
                                   : 'border-border hover:border-foreground/20 hover:bg-muted/30'">
                            <input type="radio" value="private" x-model="visibility"
                                   class="mt-0.5 h-4 w-4 shrink-0 text-primary border-border focus:ring-ring">
                            <div>
                                <span class="text-sm font-medium text-foreground flex items-center gap-1.5">
                                    <i class="fa-solid fa-lock fa-fw text-muted-foreground"></i>
                                    Prywatne
                                </span>
                                <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                                    Dane osoby widoczne tylko dla Ciebie i zaproszonych współpracowników tego drzewa.
                                    Osoba nie pojawia się w żadnym wyszukiwaniu poza Twoim drzewem.
                                    Domyślne ustawienie — odpowiednie dla wszystkich żyjących osób oraz dla osób,
                                    których danych nie chcesz udostępniać.
                                </p>
                            </div>
                        </label>

                        <!-- Publiczne -->
                        <label class="flex items-start gap-3 p-3 rounded-md border cursor-pointer transition-colors"
                               :class="visibility === 'public'
                                   ? 'border-blue-400 bg-blue-50'
                                   : 'border-border hover:border-blue-200 hover:bg-blue-50/30'">
                            <input type="radio" value="public" x-model="visibility"
                                   class="mt-0.5 h-4 w-4 shrink-0 text-primary border-border focus:ring-ring">
                            <div>
                                <span class="text-sm font-medium text-foreground flex items-center gap-1.5">
                                    <i class="fa-solid fa-eye fa-fw text-blue-500"></i>
                                    Publiczne
                                </span>
                                <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                                    Pełne dane osoby — imię, nazwisko, daty i miejsca urodzenia/śmierci — są widoczne
                                    dla wszystkich zalogowanych użytkowników Genealog w wyszukiwarce globalnej.
                                    Stosuj wyłącznie dla osób historycznych, których dane są już powszechnie dostępne
                                    (np. przodkowie z metryk kościelnych).
                                </p>
                            </div>
                        </label>

                        <!-- Anonimowe -->
                        <label class="flex items-start gap-3 p-3 rounded-md border cursor-pointer transition-colors"
                               :class="visibility === 'anonymous'
                                   ? 'border-emerald-400 bg-emerald-50'
                                   : 'border-border hover:border-emerald-200 hover:bg-emerald-50/30'">
                            <input type="radio" value="anonymous" x-model="visibility"
                                   class="mt-0.5 h-4 w-4 shrink-0 text-primary border-border focus:ring-ring">
                            <div>
                                <span class="text-sm font-medium text-foreground flex items-center gap-1.5">
                                    <i class="fa-solid fa-link fa-fw text-emerald-600"></i>
                                    Anonimowe
                                    <span class="ml-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold
                                                 bg-emerald-100 text-emerald-700 border border-emerald-200">
                                        Zalecane dla nieżyjących
                                    </span>
                                </span>
                                <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                                    Osoba uczestniczy w <strong class="text-foreground">globalnym kojarzeniu rodzin</strong>
                                    — inni użytkownicy mogą odkryć, że ich przodek może być powiązany z osobą w Twoim
                                    drzewie, bez dostępu do jej pełnych danych. Imię i nazwisko są widoczne tylko po
                                    obustronnym potwierdzeniu powiązania.
                                </p>
                                <p class="mt-1.5 text-xs text-amber-600 leading-relaxed">
                                    Wymagane: osoba musi być oznaczona jako nieżyjąca, a rok urodzenia musi przypadać
                                    ponad 100 lat temu (lub nie być podany). Właściciel drzewa musi włączyć globalne
                                    indeksowanie w ustawieniach drzewa.
                                </p>
                            </div>
                        </label>

                    </div>

                    <!-- Wskazówka gdy nieżyjąca + private -->
                    <p x-show="!isLiving && visibility === 'private'" x-cloak
                       class="text-xs text-muted-foreground flex items-start gap-1.5 pt-1">
                        <i class="fa-solid fa-circle-info fa-fw mt-0.5 shrink-0"></i>
                        Aby inni genealodzy mogli powiązać tę osobę z osobami w swoich drzewach, rozważ ustawienie
                        widoczności na <strong>Anonimowe</strong>.
                    </p>
                </div>
            </fieldset>

            <!-- Notatki -->
            <fieldset class="border-t border-border pt-5">
                <div class="space-y-1.5">
                    <label for="notes" class="block text-sm font-medium text-foreground">Notatki</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="5000"
                              class="w-full px-3 py-2 rounded-md border border-border bg-background
                                     text-foreground text-sm resize-y
                                     focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                    ><?= htmlspecialchars($person->notes ?? '') ?></textarea>
                </div>
            </fieldset>

            <div class="flex items-center justify-end gap-3 border-t border-border pt-5">
                <a href="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>"
                   class="inline-flex h-10 items-center rounded-md border border-input px-4
                          text-sm font-medium text-foreground hover:bg-accent transition-colors">
                    Anuluj
                </a>
                <button type="submit"
                        class="inline-flex h-10 items-center rounded-md px-4 text-sm font-medium
                               bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                    Zapisz zmiany
                </button>
            </div>
        </form>
    </div>

    <!-- Photo upload -->
    <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h2 class="text-base font-semibold text-card-foreground">Zdjęcie profilowe</h2>
        </div>
        <div class="p-6">
            <?php if ($person->photoPath): ?>
                <div class="mb-4">
                    <img src="/media.php?path=<?= urlencode($person->photoPath) ?>"
                         alt="Bieżące zdjęcie"
                         class="h-24 w-24 rounded-full object-cover border border-border">
                </div>
            <?php endif; ?>
            <form method="POST"
                  action="/trees/<?= htmlspecialchars($tree->id) ?>/persons/<?= htmlspecialchars($person->id) ?>/photo"
                  enctype="multipart/form-data"
                  class="flex items-center gap-3">
                <?= Csrf::hiddenInput() ?>
                <input type="file" id="photo" name="photo" accept="image/*"
                       class="text-sm text-foreground file:mr-4 file:py-1.5 file:px-3
                              file:rounded-md file:border file:border-input file:text-xs file:font-medium
                              file:text-foreground file:bg-background hover:file:bg-accent
                              file:transition-colors file:cursor-pointer">
                <button type="submit"
                        class="inline-flex h-9 items-center rounded-md px-4 text-sm font-medium
                               bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                    Wyślij
                </button>
            </form>
            <p class="mt-2 text-xs text-muted-foreground">
                Akceptujemy JPEG, PNG, WebP, GIF — max <?= defined('UPLOAD_MAX_MB') ? UPLOAD_MAX_MB : 10 ?> MB.
            </p>
        </div>
    </div>

</div>
