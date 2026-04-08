<?php declare(strict_types=1); ?>

<div class="bg-muted/30 border-b border-border">
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-10">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-muted-foreground mb-5" aria-label="Nawigacja">
            <a href="/" class="hover:text-foreground transition-colors">Strona główna</a>
            <span aria-hidden="true">/</span>
            <span class="text-foreground font-medium">Regulamin</span>
        </nav>

        <div class="flex items-start gap-4">
            <div class="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-file-lines text-primary text-xl" aria-hidden="true"></i>
            </div>
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground">Regulamin serwisu Genealog</h1>
                <p class="mt-1 text-muted-foreground">
                    Ostatnia aktualizacja: <time datetime="<?= date('Y-m-d') ?>"><?= date('d.m.Y') ?></time>
                    &nbsp;·&nbsp; Korzystanie z serwisu oznacza akceptację niniejszego regulaminu.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
    <div>

        <!-- Sidebar TOC (desktop) -->
        <aside class="hidden">
            <div class="sticky top-24">
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Spis treści</p>
                <nav aria-label="Spis treści">
                    <ol class="space-y-0.5">
                        <?php
                        $sections = [
                            ['id' => 'par-1',  'n' => '§1',  'label' => 'Postanowienia ogólne'],
                            ['id' => 'par-2',  'n' => '§2',  'label' => 'Usługi'],
                            ['id' => 'par-3',  'n' => '§3',  'label' => 'Rejestracja'],
                            ['id' => 'par-3a', 'n' => '§3a', 'label' => 'Integracje Discovery'],
                            ['id' => 'par-4',  'n' => '§4',  'label' => 'Dane osób trzecich'],
                            ['id' => 'par-5',  'n' => '§5',  'label' => 'Zabronione użycie'],
                            ['id' => 'par-6',  'n' => '§6',  'label' => 'Prawa autorskie'],
                            ['id' => 'par-7',  'n' => '§7',  'label' => 'Odpowiedzialność'],
                            ['id' => 'par-8',  'n' => '§8',  'label' => 'Zakończenie usługi'],
                            ['id' => 'par-9',  'n' => '§9',  'label' => 'Reklamacje'],
                            ['id' => 'par-10', 'n' => '§10', 'label' => 'Postanowienia końcowe'],
                        ];
                        foreach ($sections as $s):
                        ?>
                        <li>
                            <a href="#<?= $s['id'] ?>"
                               class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm transition-colors hover:bg-muted hover:text-foreground text-muted-foreground">
                                <span class="flex-shrink-0 text-[10px] font-mono font-semibold w-6 text-muted-foreground/60 group-hover:text-muted-foreground"><?= $s['n'] ?></span>
                                <span><?= htmlspecialchars($s['label']) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <!-- Quick links -->
                <div class="mt-6 pt-6 border-t border-border space-y-2">
                    <a href="/privacy" class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors">
                        <i class="fa-solid fa-shield-halved text-xs" aria-hidden="true"></i>
                        Polityka prywatności
                    </a>
                    <a href="/register" class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors">
                        <i class="fa-solid fa-user-plus text-xs" aria-hidden="true"></i>
                        Utwórz konto
                    </a>
                </div>
            </div>
        </aside>

        <!-- Mobilny TOC (accordion) -->
        <div class="mb-6" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm font-medium text-foreground">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-list text-muted-foreground" aria-hidden="true"></i>
                    Spis treści
                </span>
                <i class="fa-solid fa-chevron-down text-muted-foreground transition-transform duration-200"
                   :class="open ? 'rotate-180' : ''" aria-hidden="true"></i>
            </button>
            <div x-show="open" x-collapse class="border border-t-0 border-border rounded-b-lg bg-background">
                <ol class="p-3 space-y-0.5">
                    <?php foreach ($sections as $s): ?>
                    <li>
                        <a href="#<?= $s['id'] ?>" @click="open = false"
                           class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                            <span class="text-[10px] font-mono text-muted-foreground/60 w-6"><?= $s['n'] ?></span>
                            <?= htmlspecialchars($s['label']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>

        <!-- Treść -->
        <article class="min-w-0 space-y-2">

            <?php
            // Helper: renders a section header
            function terms_header(string $id, string $par, string $title): void {
                echo '<div class="flex items-center gap-3 mb-4">';
                echo '<div class="flex-shrink-0 h-8 rounded-lg bg-primary/10 px-2.5 flex items-center justify-center">';
                echo '<span class="text-xs font-bold text-primary font-mono">' . htmlspecialchars($par) . '</span>';
                echo '</div>';
                echo '<h2 class="text-lg font-semibold text-foreground">' . htmlspecialchars($title) . '</h2>';
                echo '</div>';
            }

            // Helper: ordered list item
            function terms_items(array $items): void {
                echo '<ol class="space-y-2 list-none counter-reset-terms">';
                foreach ($items as $i => $item) {
                    echo '<li class="flex items-start gap-3 text-sm text-foreground/90 leading-relaxed">';
                    echo '<span class="flex-shrink-0 h-5 w-5 rounded bg-muted text-[10px] font-bold text-muted-foreground flex items-center justify-center mt-0.5">' . ($i + 1) . '</span>';
                    echo '<span>' . $item . '</span>';
                    echo '</li>';
                }
                echo '</ol>';
            }
            ?>

            <!-- §1 -->
            <section id="par-1" class="scroll-mt-24">
                <?php terms_header('par-1', '§1', 'Postanowienia ogólne'); ?>
                <div class="pl-11">
                    <?php terms_items([
                        'Niniejszy regulamin określa zasady korzystania z serwisu Genealog (dalej: „Serwis"), dostępnego pod adresem <strong>' . htmlspecialchars(defined('SITE_URL') ? SITE_URL : 'genealog.pl') . '</strong>.',
                        'Usługodawcą jest <strong>' . htmlspecialchars(defined('COMPANY_NAME') ? COMPANY_NAME : 'Genealog') . '</strong>, dalej „Usługodawca".',
                        'Użytkownikiem jest każda osoba fizyczna korzystająca z Serwisu — jako użytkownik zarejestrowany lub niezalogowany.',
                        'Korzystanie z Serwisu oznacza akceptację niniejszego regulaminu.',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §2 -->
            <section id="par-2" class="scroll-mt-24">
                <?php terms_header('par-2', '§2', 'Usługi'); ?>
                <div class="pl-11 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-3">
                        <?php foreach ([
                            ['fa-sitemap', 'Drzewa genealogiczne', 'Tworzenie, edycja i współdzielenie drzew rodzinnych.'],
                            ['fa-file-import', 'Import / Eksport', 'Pełna obsługa formatu GEDCOM 5.5.1.'],
                            ['fa-users', 'Współpraca', 'Zapraszanie rodziny z rolami owner / editor / viewer.'],
                        ] as [$icon, $title, $desc]): ?>
                        <div class="rounded-lg border border-border bg-muted/20 px-4 py-3 text-center">
                            <i class="fa-solid <?= $icon ?> text-primary text-lg mb-2" aria-hidden="true"></i>
                            <p class="text-xs font-semibold text-foreground"><?= htmlspecialchars($title) ?></p>
                            <p class="text-[11px] text-muted-foreground mt-1"><?= htmlspecialchars($desc) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php terms_items([
                        'Podstawowe funkcjonalności Serwisu są dostępne <strong>bezpłatnie</strong>.',
                        'Usługodawca zastrzega sobie prawo do wprowadzania zmian w zakresie usług z zachowaniem praw nabytych użytkowników.',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §3 -->
            <section id="par-3" class="scroll-mt-24">
                <?php terms_header('par-3', '§3', 'Rejestracja'); ?>
                <div class="pl-11">
                    <?php terms_items([
                        'Rejestracja wymaga podania imienia/nazwiska, adresu e-mail i hasła.',
                        'Użytkownik zobowiązuje się do podawania danych prawdziwych i kompletnych.',
                        'Minimalna długość hasła: <strong>12 znaków</strong>, zawierające cyfrę lub znak specjalny.',
                        'Jedno konto odpowiada jednej osobie fizycznej. Dzielenie konta z osobami trzecimi jest zabronione.',
                        'Przy rejestracji Użytkownik akceptuje Regulamin i Politykę prywatności. Akceptacja jest zapisywana wraz z wersją dokumentu (RODO Art. 7(1)).',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §3a -->
            <section id="par-3a" class="scroll-mt-24">
                <?php terms_header('par-3a', '§3a', 'Integracje z zewnętrznymi rejestrami (Discovery)'); ?>
                <div class="pl-11 space-y-3">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 flex items-start gap-2.5 text-sm">
                        <i class="fa-solid fa-info-circle text-blue-600 mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                        <p class="text-blue-900">Funkcja Discovery jest domyślnie <strong>wyłączona</strong>. Włączenie jej w ustawieniach drzewa oznacza świadomą zgodę na wyszukiwanie w zewnętrznych rejestrach.</p>
                    </div>
                    <?php terms_items([
                        'Serwis umożliwia opcjonalne wyszukiwanie osób w zewnętrznych rejestrach genealogicznych (FamilySearch, Geneteka, inne).',
                        'Włączenie Discovery dla drzewa oznacza zgodę na wysyłanie ograniczonych danych (imię, nazwisko, rok urodzenia) do wybranych rejestrów w momencie ręcznego wyszukiwania.',
                        'W przypadku rejestru FamilySearch (USA) obowiązują Standardowe Klauzule Umowne (SCC) — szczegóły w Polityce Prywatności §8–9.',
                        'Wyniki wyszukiwania są prezentowane Użytkownikowi — nie są automatycznie importowane. Import wymaga osobnej decyzji.',
                        'Użytkownik może w każdej chwili wyłączyć Discovery dla drzewa w ustawieniach.',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §4 -->
            <section id="par-4" class="scroll-mt-24">
                <?php terms_header('par-4', '§4', 'Dane osób trzecich w drzewach'); ?>
                <div class="pl-11 space-y-3">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 flex items-start gap-2.5 text-sm">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                        <p class="text-amber-900"><strong>Szczególnie istotne:</strong> Za dane osób trzecich wprowadzone do drzew genealogicznych odpowiada <strong>użytkownik</strong>, nie Usługodawca.</p>
                    </div>
                    <?php terms_items([
                        'Użytkownik wprowadzający dane osób trzecich (krewnych, przodków) oświadcza, że ma podstawę prawną do ich przetwarzania zgodnie z RODO.',
                        'Serwis zapewnia mechanizmy ochrony prywatności żyjących osób: domyślna flaga „is_living", ograniczenie widoczności w globalnym indeksie, filtrowanie eksportu GEDCOM.',
                        'Jeśli osoba trzecia zgłosi żądanie usunięcia swoich danych, Usługodawca zwróci się do właściciela drzewa o podjęcie działań w terminie 14 dni.',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §5 -->
            <section id="par-5" class="scroll-mt-24">
                <?php terms_header('par-5', '§5', 'Zabronione użycie'); ?>
                <div class="pl-11">
                    <div class="rounded-lg border border-border overflow-hidden">
                        <?php foreach ([
                            ['fa-ban',              'Treści niezgodne z prawem',    'Dyskryminujące, obraźliwe, nielegalne.'],
                            ['fa-copyright',        'Naruszenie praw autorskich',   'Przesyłanie materiałów bez prawa do ich publikacji.'],
                            ['fa-bug',              'Ataki hakerskie',              'DDoS, exploity podatności, nieautoryzowany dostęp.'],
                            ['fa-robot',            'Automatyzacja i scraping',     'Masowe pobieranie danych obciążające infrastrukturę.'],
                            ['fa-user-secret',      'Ujawnianie danych żyjących',   'Bez zgody osób, których dane dotyczą.'],
                            ['fa-envelope-circle-check', 'Spam i masowa rejestracja', 'Tworzenie kont w celach reklamowych lub spamowych.'],
                        ] as [$icon, $label, $desc]): ?>
                        <div class="flex items-center gap-3 px-4 py-3 border-b border-border last:border-0 hover:bg-muted/20 transition-colors">
                            <div class="h-7 w-7 rounded-md bg-destructive/10 flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid <?= $icon ?> text-destructive text-[10px]" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-foreground"><?= htmlspecialchars($label) ?></p>
                                <p class="text-[11px] text-muted-foreground"><?= htmlspecialchars($desc) ?></p>
                            </div>
                            <i class="fa-solid fa-xmark text-destructive/60 ml-auto flex-shrink-0" aria-hidden="true"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §6 -->
            <section id="par-6" class="scroll-mt-24">
                <?php terms_header('par-6', '§6', 'Prawa autorskie'); ?>
                <div class="pl-11">
                    <?php terms_items([
                        'Prawa autorskie do Serwisu (kod, design, nazwa) przysługują Usługodawcy.',
                        'Użytkownik zachowuje prawa autorskie do danych wprowadzonych przez siebie.',
                        'Udostępniając dane w Serwisie, użytkownik udziela Usługodawcy niewyłącznej licencji na ich przetwarzanie w zakresie niezbędnym do świadczenia usługi.',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §7 -->
            <section id="par-7" class="scroll-mt-24">
                <?php terms_header('par-7', '§7', 'Odpowiedzialność'); ?>
                <div class="pl-11 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                        <div class="rounded-lg border border-border bg-muted/20 px-4 py-3">
                            <p class="text-xs font-semibold text-foreground mb-1">RPO — Recovery Point Objective</p>
                            <p class="text-2xl font-bold text-primary">24h</p>
                            <p class="text-[11px] text-muted-foreground mt-1">Maksymalna utrata danych w przypadku awarii.</p>
                        </div>
                        <div class="rounded-lg border border-border bg-muted/20 px-4 py-3">
                            <p class="text-xs font-semibold text-foreground mb-1">RTO — Recovery Time Objective</p>
                            <p class="text-2xl font-bold text-primary">4h</p>
                            <p class="text-[11px] text-muted-foreground mt-1">Maksymalny czas przywrócenia usługi.</p>
                        </div>
                    </div>
                    <?php terms_items([
                        'Usługodawca dokłada starań, by Serwis był dostępny i bezpieczny, ale nie gwarantuje ciągłości działania ani wolności od błędów.',
                        'Usługodawca nie odpowiada za utracone dane w zakresie większym niż wynikający z obowiązku zapewnienia kopii zapasowych (RPO 24h, RTO 4h).',
                        'Użytkownik jest odpowiedzialny za regularny eksport własnych danych jako kopii zapasowej (dostępne w Ustawieniach konta).',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §8 -->
            <section id="par-8" class="scroll-mt-24">
                <?php terms_header('par-8', '§8', 'Zakończenie świadczenia usługi'); ?>
                <div class="pl-11">
                    <?php terms_items([
                        'Użytkownik może w każdym momencie usunąć swoje konto z poziomu Ustawień → Strefa niebezpieczna.',
                        'Usługodawca może zablokować lub usunąć konto użytkownika w przypadku naruszenia niniejszego regulaminu.',
                        'Po usunięciu konta dane są anonimizowane zgodnie z Polityką prywatności.',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §9 -->
            <section id="par-9" class="scroll-mt-24">
                <?php terms_header('par-9', '§9', 'Reklamacje'); ?>
                <div class="pl-11 space-y-3">
                    <?php terms_items([
                        'Reklamacje należy zgłaszać na adres: <a href="mailto:' . htmlspecialchars(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'kontakt@genealog.pl') . '" class="text-primary hover:underline font-medium">' . htmlspecialchars(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'kontakt@genealog.pl') . '</a>.',
                        'Usługodawca rozpatrzy reklamację w terminie <strong>30 dni roboczych</strong>.',
                    ]); ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <!-- §10 -->
            <section id="par-10" class="scroll-mt-24">
                <?php terms_header('par-10', '§10', 'Postanowienia końcowe'); ?>
                <div class="pl-11 space-y-3">
                    <?php terms_items([
                        'Regulamin może zostać zmieniony. O zmianach użytkownicy zostaną poinformowani e-mailem z co najmniej <strong>14-dniowym wyprzedzeniem</strong>.',
                        'W sprawach nieuregulowanych stosuje się przepisy prawa brytyjskiego, w szczególności <em>UK GDPR</em> (Retained EU Law) oraz <em>Data Protection Act 2018</em>.',
                        'Spory wynikające z korzystania z Serwisu rozstrzyga sąd właściwy dla siedziby Usługodawcy.',
                    ]); ?>

                    <div class="mt-4 rounded-lg border border-border bg-muted/20 px-5 py-4 flex items-center gap-4">
                        <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-envelope text-primary" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Kontakt w sprawach regulaminu:</p>
                            <a href="mailto:<?= htmlspecialchars(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'kontakt@genealog.pl') ?>"
                               class="text-base font-semibold text-primary hover:underline">
                                <?= htmlspecialchars(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'kontakt@genealog.pl') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

        </article>
    </div>
</div>
