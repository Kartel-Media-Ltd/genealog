<?php declare(strict_types=1); ?>

<!-- ================================================================
     HERO
     ================================================================ -->
<section class="hero-gradient hero-dots relative overflow-hidden">
    <!-- Decorative blobs -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-40 -right-32 h-[600px] w-[600px] rounded-full bg-primary/5 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-32 h-[500px] w-[500px] rounded-full bg-primary/8 blur-3xl"></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 lg:py-36">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">

            <!-- Tekst hero -->
            <div class="text-center lg:text-left">
                <!-- Badge -->
                <div class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary mb-6 animate-fade-up">
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                    </svg>
                    Bezpłatna platforma genealogiczna
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-foreground leading-[1.1] animate-fade-up animation-delay-100">
                    Odkryj historię<br>
                    <span class="text-primary">swojej rodziny</span>
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-muted-foreground leading-relaxed animate-fade-up animation-delay-200">
                    Buduj drzewo genealogiczne, zapraszaj bliskich do współpracy i łącz
                    historię swojej rodziny z innymi. Eksportuj do druku i formatu GEDCOM.
                </p>

                <!-- CTAs -->
                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start animate-fade-up animation-delay-300">
                    <a href="/register"
                       class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-base font-semibold text-primary-foreground shadow-md hover:bg-primary/90 hover:shadow-lg transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <i class="fa-solid fa-tree" aria-hidden="true"></i>
                        Zacznij za darmo
                    </a>
                    <a href="#how-it-works"
                       class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-background px-6 py-3 text-base font-semibold text-foreground hover:bg-accent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <i class="fa-solid fa-play text-primary" aria-hidden="true"></i>
                        Jak to działa
                    </a>
                </div>

                <!-- Social proof -->
                <div class="mt-8 flex items-center gap-4 justify-center lg:justify-start animate-fade-up animation-delay-400">
                    <div class="flex -space-x-2">
                        <?php foreach (['bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500'] as $color): ?>
                        <div class="h-8 w-8 rounded-full border-2 border-background <?= $color ?> flex items-center justify-center" aria-hidden="true">
                            <i class="fa-solid fa-user text-white text-[10px]"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Dołącz do społeczności genealogów
                    </p>
                </div>
            </div>

            <!-- Ilustracja drzewa -->
            <div class="flex justify-center lg:justify-end animate-fade-up animation-delay-200">
                <div class="relative w-full max-w-lg">
                    <!-- Karta "preview" drzewa -->
                    <div class="rounded-2xl border border-border bg-background shadow-2xl overflow-hidden">
                        <!-- Nagłówek karty -->
                        <div class="border-b border-border bg-muted/30 px-5 py-3 flex items-center gap-2">
                            <div class="h-3 w-3 rounded-full bg-red-400"></div>
                            <div class="h-3 w-3 rounded-full bg-amber-400"></div>
                            <div class="h-3 w-3 rounded-full bg-green-400"></div>
                            <span class="ml-3 text-xs text-muted-foreground font-mono">Drzewo: Rodzina Kowalskich</span>
                        </div>
                        <!-- Wizualizacja drzewa — uproszczona SVG -->
                        <div class="p-6 bg-gradient-to-b from-muted/10 to-transparent">
                            <svg viewBox="0 0 400 280" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto" aria-label="Przykładowe drzewo genealogiczne" role="img">
                                <!-- Połączenia -->
                                <g stroke="hsl(221.2 83.2% 53.3%)" stroke-width="1.5" fill="none" opacity="0.4">
                                    <!-- Level 0 → 1 -->
                                    <line x1="200" y1="60" x2="100" y2="140"/>
                                    <line x1="200" y1="60" x2="300" y2="140"/>
                                    <!-- Level 1 → 2 -->
                                    <line x1="100" y1="160" x2="55"  y2="230"/>
                                    <line x1="100" y1="160" x2="145" y2="230"/>
                                    <line x1="300" y1="160" x2="255" y2="230"/>
                                    <line x1="300" y1="160" x2="345" y2="230"/>
                                </g>

                                <!-- Węzeł root -->
                                <g transform="translate(200,40)">
                                    <rect x="-52" y="-18" width="104" height="38" rx="8"
                                          fill="hsl(221.2 83.2% 53.3%)" stroke="none"/>
                                    <text x="0" y="-2" text-anchor="middle" font-size="10" font-weight="600" fill="white" font-family="sans-serif">Jan Kowalski</text>
                                    <text x="0" y="11" text-anchor="middle" font-size="8" fill="white" opacity="0.85" font-family="sans-serif">ur. 1952</text>
                                </g>

                                <!-- Level 1 -->
                                <?php foreach ([
                                    ['x' => 100, 'name' => 'Marek K.', 'year' => '1975'],
                                    ['x' => 300, 'name' => 'Anna K.',  'year' => '1978'],
                                ] as $node): ?>
                                <g transform="translate(<?= $node['x'] ?>,148)">
                                    <rect x="-45" y="-16" width="90" height="34" rx="7"
                                          fill="hsl(221.2 83.2% 53.3% / 0.12)"
                                          stroke="hsl(221.2 83.2% 53.3%)" stroke-width="1.5"/>
                                    <text x="0" y="-2" text-anchor="middle" font-size="9" font-weight="600" fill="hsl(221.2 83.2% 25%)" font-family="sans-serif"><?= htmlspecialchars($node['name']) ?></text>
                                    <text x="0" y="11" text-anchor="middle" font-size="8" fill="hsl(215.4 16.3% 46.9%)" font-family="sans-serif">ur. <?= htmlspecialchars($node['year']) ?></text>
                                </g>
                                <?php endforeach; ?>

                                <!-- Level 2 -->
                                <?php foreach ([
                                    ['x' => 55,  'name' => 'Piotr', 'year' => '2001'],
                                    ['x' => 145, 'name' => 'Ola',   'year' => '2003'],
                                    ['x' => 255, 'name' => 'Tomek', 'year' => '2005'],
                                    ['x' => 345, 'name' => 'Kasia', 'year' => '2008'],
                                ] as $node): ?>
                                <g transform="translate(<?= $node['x'] ?>,230)">
                                    <rect x="-35" y="-14" width="70" height="28" rx="6"
                                          fill="hsl(210 40% 96.1%)"
                                          stroke="hsl(214.3 31.8% 91.4%)" stroke-width="1"/>
                                    <text x="0" y="-1" text-anchor="middle" font-size="8.5" font-weight="500" fill="hsl(222.2 84% 4.9%)" font-family="sans-serif"><?= htmlspecialchars($node['name']) ?></text>
                                    <text x="0" y="10" text-anchor="middle" font-size="7.5" fill="hsl(215.4 16.3% 46.9%)" font-family="sans-serif"><?= htmlspecialchars($node['year']) ?></text>
                                </g>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                        <!-- Stats bar -->
                        <div class="border-t border-border px-5 py-3 grid grid-cols-3 gap-2">
                            <?php foreach ([
                                ['val' => '4', 'label' => 'pokolenia'],
                                ['val' => '7', 'label' => 'osób'],
                                ['val' => '3', 'label' => 'zdjęcia'],
                            ] as $stat): ?>
                            <div class="text-center">
                                <div class="text-base font-bold text-primary"><?= $stat['val'] ?></div>
                                <div class="text-[10px] text-muted-foreground"><?= $stat['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Floating badge -->
                    <div class="absolute -bottom-4 -left-4 rounded-xl border border-border bg-background shadow-lg px-4 py-3 flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-check text-green-600 text-xs" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-foreground">Nowe powiązanie!</div>
                            <div class="text-[10px] text-muted-foreground">Znaleziono wspólnego przodka</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ================================================================
     FEATURES
     ================================================================ -->
<section id="features" class="py-20 lg:py-28 bg-background">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <!-- Nagłówek sekcji -->
        <div class="text-center max-w-2xl mx-auto mb-14">
            <span class="text-sm font-semibold text-primary uppercase tracking-wider">Funkcjonalności</span>
            <h2 class="mt-2 text-3xl sm:text-4xl font-bold text-foreground tracking-tight">
                Wszystko czego potrzebujesz do genealogii
            </h2>
            <p class="mt-4 text-muted-foreground text-lg leading-relaxed">
                Od budowy drzewa po eksport do druku — Genealog daje Ci kompletny zestaw narzędzi.
            </p>
        </div>

        <!-- Features grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

            <?php
            $features = [
                [
                    'icon'  => 'fa-solid fa-diagram-project',
                    'color' => 'bg-blue-50 text-blue-600',
                    'title' => 'Interaktywne drzewo',
                    'desc'  => 'Wizualizuj swoją rodzinę w formie interaktywnego drzewa. Zoom, przesuwanie, szczegóły osoby w jednym kliknięciu. Widoki: przodkowie, potomkowie, rodzina.',
                ],
                [
                    'icon'  => 'fa-solid fa-users',
                    'color' => 'bg-emerald-50 text-emerald-600',
                    'title' => 'Współpraca rodziny',
                    'desc'  => 'Zapraszaj bliskich do edytowania drzewa. Role: właściciel, edytor, obserwator. Każda zmiana jest śledzona — wiesz kto co dodał.',
                ],
                [
                    'icon'  => 'fa-solid fa-link',
                    'color' => 'bg-violet-50 text-violet-600',
                    'title' => 'Łącz drzewa rodzin',
                    'desc'  => 'Globalne dopasowanie anonimowych odcisków palca. Gdy inna rodzina ma wspólnego przodka — Genealog Cię powiadomi. Połączenie wymaga Twojej zgody.',
                ],
                [
                    'icon'  => 'fa-solid fa-file-import',
                    'color' => 'bg-amber-50 text-amber-600',
                    'title' => 'Import / Eksport GEDCOM',
                    'desc'  => 'Przywieź swoje dane z Ancestry, MyHeritage lub Gramps. Standard GEDCOM 5.5.1 gwarantuje kompatybilność z innymi platformami.',
                ],
                [
                    'icon'  => 'fa-solid fa-print',
                    'color' => 'bg-rose-50 text-rose-600',
                    'title' => 'Eksport do druku',
                    'desc'  => 'Wydrukuj drzewo jako profesjonalną tablicę genealogiczną. Format A3 lub A4, widok przodków lub rodziny. Idealne na prezent.',
                ],
                [
                    'icon'  => 'fa-solid fa-shield-halved',
                    'color' => 'bg-slate-50 text-slate-600',
                    'title' => 'Prywatność i RODO',
                    'desc'  => 'Żyjące osoby nigdy nie trafiają do publicznego widoku. Każde drzewo ma własne uprawnienia. Twoje dane należą do Ciebie.',
                ],
            ];
            foreach ($features as $i => $f):
            ?>
            <article class="group rounded-xl border border-border bg-card p-6 hover:shadow-md hover:border-primary/20 transition-all duration-200">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg <?= $f['color'] ?>">
                            <i class="<?= $f['icon'] ?> text-lg" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-foreground mb-1.5 group-hover:text-primary transition-colors">
                            <?= htmlspecialchars($f['title']) ?>
                        </h3>
                        <p class="text-sm text-muted-foreground leading-relaxed">
                            <?= htmlspecialchars($f['desc']) ?>
                        </p>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>

        </div>
    </div>
</section>

<!-- ================================================================
     HOW IT WORKS
     ================================================================ -->
<section id="how-it-works" class="py-20 lg:py-28 bg-muted/30 border-y border-border">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <!-- Nagłówek -->
        <div class="text-center max-w-2xl mx-auto mb-14">
            <span class="text-sm font-semibold text-primary uppercase tracking-wider">Szybki start</span>
            <h2 class="mt-2 text-3xl sm:text-4xl font-bold text-foreground tracking-tight">
                Od zera do drzewa genealogicznego<br>w 3 krokach
            </h2>
        </div>

        <!-- Steps -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">

            <!-- Linia łącząca (desktop only) -->
            <div class="hidden md:block absolute top-10 left-[calc(16.67%+1rem)] right-[calc(16.67%+1rem)] h-px bg-border" aria-hidden="true"></div>

            <?php
            $steps = [
                [
                    'num'   => '1',
                    'icon'  => 'fa-solid fa-user-plus',
                    'title' => 'Stwórz konto i drzewo',
                    'desc'  => 'Rejestracja zajmuje 30 sekund. Żadna karta kredytowa nie jest potrzebna. Utwórz swoje pierwsze drzewo i nadaj mu nazwę — np. "Rodzina Kowalskich".',
                    'cta'   => ['label' => 'Zarejestruj się', 'href' => '/register'],
                ],
                [
                    'num'   => '2',
                    'icon'  => 'fa-solid fa-person-circle-plus',
                    'title' => 'Dodaj osoby i relacje',
                    'desc'  => 'Wypełnij dane: imię, nazwisko, data urodzenia, miejsce. Połącz osoby relacjami: rodzic, dziecko, małżonek. Dodaj zdjęcia i notatki.',
                    'cta'   => null,
                ],
                [
                    'num'   => '3',
                    'icon'  => 'fa-solid fa-share-nodes',
                    'title' => 'Zaproś rodzinę i udostępnij',
                    'desc'  => 'Wyślij zaproszenia e-mailem. Bliscy mogą przeglądać lub edytować drzewo. Wydrukuj tablicę genealogiczną lub wyeksportuj do GEDCOM.',
                    'cta'   => null,
                ],
            ];
            foreach ($steps as $step):
            ?>
            <div class="relative flex flex-col items-center text-center">
                <!-- Numer kroku -->
                <div class="relative z-10 flex h-20 w-20 items-center justify-center rounded-full border-4 border-background bg-primary shadow-lg mb-6">
                    <i class="<?= $step['icon'] ?> text-2xl text-primary-foreground" aria-hidden="true"></i>
                    <div class="absolute -top-2 -right-2 h-7 w-7 rounded-full bg-foreground flex items-center justify-center">
                        <span class="text-xs font-bold text-background"><?= $step['num'] ?></span>
                    </div>
                </div>
                <!-- Treść -->
                <h3 class="text-lg font-semibold text-foreground mb-3">
                    <?= htmlspecialchars($step['title']) ?>
                </h3>
                <p class="text-sm text-muted-foreground leading-relaxed max-w-xs">
                    <?= htmlspecialchars($step['desc']) ?>
                </p>
                <?php if ($step['cta']): ?>
                <a href="<?= htmlspecialchars($step['cta']['href']) ?>"
                   class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary/80 transition-colors">
                    <?= htmlspecialchars($step['cta']['label']) ?>
                    <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

        </div>

        <!-- Import tip -->
        <div class="mt-12 mx-auto max-w-2xl rounded-xl border border-blue-200 bg-blue-50 px-6 py-4 flex gap-4 items-start">
            <div class="flex-shrink-0 mt-0.5">
                <i class="fa-solid fa-lightbulb text-blue-500 text-lg" aria-hidden="true"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-blue-900">Masz już dane w Ancestry lub MyHeritage?</p>
                <p class="text-sm text-blue-800 mt-0.5">
                    Wyeksportuj plik GEDCOM (.ged) i importuj go w jednym kroku. Genealog odczyta osoby,
                    relacje i daty automatycznie.
                </p>
            </div>
        </div>

    </div>
</section>

<!-- ================================================================
     PRIVACY SECTION
     ================================================================ -->
<section id="privacy" class="py-20 lg:py-28 bg-background">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">

            <!-- Tekst -->
            <div>
                <span class="text-sm font-semibold text-primary uppercase tracking-wider">Prywatność i bezpieczeństwo</span>
                <h2 class="mt-2 text-3xl sm:text-4xl font-bold text-foreground tracking-tight">
                    Twoje dane należą do Ciebie
                </h2>
                <p class="mt-4 text-muted-foreground text-lg leading-relaxed">
                    Genealog zbudowany jest z myślą o RODO od pierwszego dnia.
                    Dane Twoich bliskich są bezpieczne.
                </p>

                <ul class="mt-8 space-y-4">
                    <?php
                    $privacy = [
                        [
                            'icon'  => 'fa-solid fa-eye-slash',
                            'color' => 'text-blue-500',
                            'title' => 'Żyjące osoby są prywatne',
                            'desc'  => 'Dane żyjących członków rodziny nigdy nie trafiają do globalnego wyszukiwania. Widoczność ustawiasz samodzielnie.',
                        ],
                        [
                            'icon'  => 'fa-solid fa-lock',
                            'color' => 'text-emerald-500',
                            'title' => 'Kontrolujesz dostęp',
                            'desc'  => 'Role: właściciel, edytor, obserwator. Drzewo może być prywatne lub publiczne — Twój wybór.',
                        ],
                        [
                            'icon'  => 'fa-solid fa-database',
                            'color' => 'text-violet-500',
                            'title' => 'Eksport w każdej chwili',
                            'desc'  => 'Pobierz swoje dane w formacie GEDCOM kiedy chcesz. Bez lock-in, bez ukrytych opłat.',
                        ],
                        [
                            'icon'  => 'fa-solid fa-shield-halved',
                            'color' => 'text-amber-500',
                            'title' => 'Zgodność z RODO',
                            'desc'  => 'Zasada 100 lat: osoby urodzone ponad 100 lat temu mogą być udostępniane publicznie. Młodsze — tylko za Twoją zgodą.',
                        ],
                    ];
                    foreach ($privacy as $item):
                    ?>
                    <li class="flex gap-4">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="h-8 w-8 rounded-lg bg-muted flex items-center justify-center">
                                <i class="<?= $item['icon'] ?> <?= $item['color'] ?> text-sm" aria-hidden="true"></i>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-foreground"><?= htmlspecialchars($item['title']) ?></p>
                            <p class="text-sm text-muted-foreground mt-0.5 leading-relaxed"><?= htmlspecialchars($item['desc']) ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="mt-8">
                    <a href="/privacy"
                       class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary/80 transition-colors">
                        Przeczytaj pełną politykę prywatności
                        <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <!-- Visual — privacy card -->
            <div class="flex justify-center lg:justify-end">
                <div class="w-full max-w-sm space-y-3">
                    <!-- Widoczność drzewa -->
                    <div class="rounded-xl border border-border bg-card shadow-sm p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-semibold text-foreground">Ustawienia widoczności</h4>
                            <span class="text-xs text-emerald-600 font-medium bg-emerald-50 px-2 py-0.5 rounded-full">Zabezpieczone</span>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ([
                                ['label' => 'Drzewo publiczne',      'on' => false, 'desc' => 'Wszyscy mogą przeglądać'],
                                ['label' => 'Żyjące osoby ukryte',   'on' => true,  'desc' => 'Automatycznie chronione'],
                                ['label' => 'Globalny indeks',       'on' => true,  'desc' => 'Anonimowe odciski palca'],
                                ['label' => 'Powiadomienia e-mail',  'on' => true,  'desc' => 'Przy nowych dopasowaniach'],
                            ] as $setting): ?>
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-foreground"><?= $setting['label'] ?></p>
                                    <p class="text-[10px] text-muted-foreground"><?= $setting['desc'] ?></p>
                                </div>
                                <!-- Toggle visual -->
                                <div class="flex-shrink-0 h-5 w-9 rounded-full flex items-center px-0.5 <?= $setting['on'] ? 'bg-primary justify-end' : 'bg-muted justify-start' ?>">
                                    <div class="h-4 w-4 rounded-full bg-white shadow-sm"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- GDPR badge -->
                    <div class="rounded-xl border border-border bg-card shadow-sm p-4 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-eu text-primary text-lg" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-foreground">Zgodny z RODO (GDPR)</p>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Art. 13–14 — prawa dostępu i usunięcia danych</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ================================================================
     FINAL CTA
     ================================================================ -->
<section class="py-20 lg:py-28 bg-primary">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">

        <!-- Dekoracja -->
        <div class="mx-auto mb-6 h-16 w-16 rounded-full bg-white/10 flex items-center justify-center">
            <svg class="h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22V12"/>
                <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                <path d="M12 12c0 0-3 1.5-3 5"/>
                <path d="M12 12c0 0 3 1.5 3 5"/>
            </svg>
        </div>

        <h2 class="text-3xl sm:text-4xl font-bold text-white tracking-tight">
            Zacznij budować swoje drzewo już dziś
        </h2>
        <p class="mt-4 text-lg text-white/80 leading-relaxed">
            Rejestracja jest bezpłatna i zajmuje mniej niż minutę.<br>
            Żadna karta kredytowa nie jest wymagana.
        </p>

        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/register"
               class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-8 py-3.5 text-base font-semibold text-primary shadow-lg hover:bg-white/90 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
                <i class="fa-solid fa-tree" aria-hidden="true"></i>
                Utwórz konto za darmo
            </a>
            <a href="/login"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/30 bg-white/10 px-8 py-3.5 text-base font-semibold text-white hover:bg-white/20 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
                Mam już konto — zaloguj
            </a>
        </div>

        <!-- Trust signals -->
        <div class="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-white/70 text-sm">
            <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-check text-white/50 text-xs" aria-hidden="true"></i>
                Bezpłatne
            </span>
            <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-check text-white/50 text-xs" aria-hidden="true"></i>
                Bez karty kredytowej
            </span>
            <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-check text-white/50 text-xs" aria-hidden="true"></i>
                RODO compliant
            </span>
            <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-check text-white/50 text-xs" aria-hidden="true"></i>
                Export GEDCOM
            </span>
        </div>
    </div>
</section>
