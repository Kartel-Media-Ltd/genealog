<?php declare(strict_types=1); ?>

<div class="bg-muted/30 border-b border-border">
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-10">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-muted-foreground mb-5" aria-label="Nawigacja">
            <a href="/" class="hover:text-foreground transition-colors">Strona główna</a>
            <span aria-hidden="true">/</span>
            <span class="text-foreground font-medium">Polityka prywatności</span>
        </nav>

        <div class="flex items-start gap-4">
            <div class="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-shield-halved text-primary text-xl" aria-hidden="true"></i>
            </div>
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground">Polityka prywatności</h1>
                <p class="mt-1 text-muted-foreground">
                    Ostatnia aktualizacja: <time datetime="<?= date('Y-m-d') ?>"><?= date('d.m.Y') ?></time>
                    &nbsp;·&nbsp; Genealog stosuje RODO Art. 13–14 i 25 (privacy by design).
                </p>
            </div>
        </div>
    </div>
</div>

<div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-10 lg:py-14"
     x-data="{ activeToc: 'sec-1' }">
    <div>

        <!-- Sidebar TOC (desktop — ukryty przy max-w-2xl) -->
        <aside class="hidden">
            <div class="sticky top-24">
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Spis treści</p>
                <nav aria-label="Spis treści">
                    <ol class="space-y-0.5">
                        <?php
                        $sections = [
                            ['id' => 'sec-1',  'n' => '1',  'label' => 'Administrator danych'],
                            ['id' => 'sec-2',  'n' => '2',  'label' => 'Cel i podstawa przetwarzania'],
                            ['id' => 'sec-3',  'n' => '3',  'label' => 'Zakres przetwarzanych danych'],
                            ['id' => 'sec-4',  'n' => '4',  'label' => 'Okres przechowywania'],
                            ['id' => 'sec-5',  'n' => '5',  'label' => 'Twoje prawa'],
                            ['id' => 'sec-6',  'n' => '6',  'label' => 'Odbiorcy danych'],
                            ['id' => 'sec-7',  'n' => '7',  'label' => 'Dane osób trzecich'],
                            ['id' => 'sec-8',  'n' => '8',  'label' => 'Przekazywanie poza EOG'],
                            ['id' => 'sec-9',  'n' => '9',  'label' => 'Discovery Sources'],
                            ['id' => 'sec-10', 'n' => '10', 'label' => 'Bezpieczeństwo'],
                            ['id' => 'sec-11', 'n' => '11', 'label' => 'Zmiany polityki'],
                            ['id' => 'sec-12', 'n' => '12', 'label' => 'Kontakt'],
                        ];
                        foreach ($sections as $s):
                        ?>
                        <li>
                            <a href="#<?= $s['id'] ?>"
                               class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm transition-colors hover:bg-muted hover:text-foreground text-muted-foreground">
                                <span class="flex-shrink-0 text-[10px] font-mono font-semibold w-4 text-muted-foreground/60 group-hover:text-muted-foreground"><?= $s['n'] ?></span>
                                <span><?= htmlspecialchars($s['label']) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <!-- Quick links -->
                <div class="mt-6 pt-6 border-t border-border space-y-2">
                    <a href="/terms" class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors">
                        <i class="fa-solid fa-file-lines text-xs" aria-hidden="true"></i>
                        Regulamin serwisu
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
                            <span class="text-[10px] font-mono text-muted-foreground/60 w-4"><?= $s['n'] ?></span>
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
            function privacy_section(string $id, string $num, string $title, string $content): void {
                echo '<section id="' . $id . '" class="scroll-mt-24">';
                echo '<div class="flex items-center gap-3 mb-4">';
                echo '<div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">';
                echo '<span class="text-xs font-bold text-primary">' . $num . '</span>';
                echo '</div>';
                echo '<h2 class="text-lg font-semibold text-foreground">' . htmlspecialchars($title) . '</h2>';
                echo '</div>';
                echo '<div class="pl-11">' . $content . '</div>';
                echo '</section>';
                echo '<div class="border-b border-border/50 my-6"></div>';
            }
            ?>

            <section id="sec-1" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">1</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Administrator danych</h2>
                </div>
                <div class="pl-11 text-sm text-foreground/90 leading-relaxed space-y-2">
                    <p>
                        Administratorem danych osobowych jest
                        <strong><?= htmlspecialchars(defined('COMPANY_NAME') ? COMPANY_NAME : 'Genealog') ?></strong>,
                        z siedzibą <?= htmlspecialchars(defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '[do uzupełnienia]') ?>.
                    </p>
                    <p>Kontakt w sprawach ochrony danych:
                        <a href="mailto:<?= htmlspecialchars(defined('DPO_EMAIL') ? DPO_EMAIL : 'privacy@genealog.pl') ?>"
                           class="font-medium text-primary hover:underline">
                            <?= htmlspecialchars(defined('DPO_EMAIL') ? DPO_EMAIL : 'privacy@genealog.pl') ?>
                        </a>
                    </p>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-2" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">2</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Cel i podstawa przetwarzania <span class="text-xs font-normal text-muted-foreground ml-1">(Art. 13 RODO)</span></h2>
                </div>
                <div class="pl-11 space-y-2">
                    <?php
                    $purposes = [
                        ['title' => 'Prowadzenie konta użytkownika',       'basis' => 'art. 6(1)(b) — wykonanie umowy'],
                        ['title' => 'Przechowywanie danych genealogicznych','basis' => 'art. 6(1)(b) — wykonanie umowy'],
                        ['title' => 'Bezpieczeństwo systemu (logi, rate limiting)', 'basis' => 'art. 6(1)(f) — prawnie uzasadniony interes'],
                        ['title' => 'Powiadomienia e-mail o aktywnościach','basis' => 'art. 6(1)(a) — zgoda (wycofalna w ustawieniach)'],
                        ['title' => 'Obsługa zgłoszeń i kontakt',          'basis' => 'art. 6(1)(f) — prawnie uzasadniony interes'],
                    ];
                    foreach ($purposes as $p):
                    ?>
                    <div class="flex items-start gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3">
                        <i class="fa-solid fa-circle-check text-primary text-sm mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-foreground"><?= htmlspecialchars($p['title']) ?></p>
                            <p class="text-xs text-muted-foreground mt-0.5">Podstawa: <span class="font-mono"><?= htmlspecialchars($p['basis']) ?></span></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-3" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">3</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Zakres przetwarzanych danych</h2>
                </div>
                <div class="pl-11 space-y-2">
                    <?php
                    $dataTypes = [
                        ['icon' => 'fa-user',         'cat' => 'Dane konta',           'desc' => 'Imię i nazwisko, adres e-mail, hasło (skrót bcrypt), język interfejsu.'],
                        ['icon' => 'fa-sitemap',       'cat' => 'Dane genealogiczne',   'desc' => 'Dane osób (imiona, daty urodzenia/śmierci, miejsca, relacje), zdjęcia, dokumenty — wprowadzone przez użytkownika.'],
                        ['icon' => 'fa-server',        'cat' => 'Dane techniczne',      'desc' => 'Adres IP, data i godzina logowania, identyfikator sesji, typ przeglądarki (logi dostępu).'],
                        ['icon' => 'fa-clipboard-list','cat' => 'Logi audytowe',        'desc' => 'Akcje administracyjne, importy/eksporty danych, zmiany uprawnień.'],
                    ];
                    foreach ($dataTypes as $d):
                    ?>
                    <div class="flex items-start gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3">
                        <div class="h-7 w-7 rounded-md bg-background border border-border flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fa-solid <?= $d['icon'] ?> text-muted-foreground text-xs" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-foreground"><?= htmlspecialchars($d['cat']) ?></p>
                            <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed"><?= htmlspecialchars($d['desc']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-4" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">4</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Okres przechowywania <span class="text-xs font-normal text-muted-foreground ml-1">(Art. 5(1)(e) RODO)</span></h2>
                </div>
                <div class="pl-11">
                    <div class="overflow-x-auto rounded-lg border border-border">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-muted/50 border-b border-border">
                                    <th class="px-4 py-2.5 text-left font-semibold text-foreground">Kategoria danych</th>
                                    <th class="px-4 py-2.5 text-left font-semibold text-foreground">Okres</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-background">
                                <?php foreach ([
                                    ['Dane konta',            'Do usunięcia przez użytkownika'],
                                    ['Dane genealogiczne',    'Do usunięcia drzewa lub konta właściciela'],
                                    ['Logi audytowe',         '3 lata (NIS2 + bezpieczeństwo)'],
                                    ['Tokeny resetu hasła',   '1 godzina (TTL), kasowanie po 24h od wygaśnięcia'],
                                    ['Logi rate limitera',    '15 minut'],
                                ] as [$cat, $period]): ?>
                                <tr>
                                    <td class="px-4 py-2.5 text-foreground/90"><?= htmlspecialchars($cat) ?></td>
                                    <td class="px-4 py-2.5 text-muted-foreground font-mono text-xs"><?= htmlspecialchars($period) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-5" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">5</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Twoje prawa <span class="text-xs font-normal text-muted-foreground ml-1">(Art. 15–22 RODO)</span></h2>
                </div>
                <div class="pl-11 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php
                    $rights = [
                        ['icon' => 'fa-download',      'color' => 'bg-blue-50 text-blue-600',    'title' => 'Dostęp do danych',            'desc' => 'Eksport archiwum ZIP (JSON + GEDCOM) z poziomu ustawień konta.'],
                        ['icon' => 'fa-pen',           'color' => 'bg-emerald-50 text-emerald-600','title' => 'Sprostowanie',               'desc' => 'Edycja danych profilu i osób w drzewach bezpośrednio w aplikacji.'],
                        ['icon' => 'fa-trash',         'color' => 'bg-red-50 text-red-600',       'title' => 'Usunięcie',                  'desc' => 'Pełna anonimizacja konta z poziomu Ustawień → Strefa niebezpieczna.'],
                        ['icon' => 'fa-pause',         'color' => 'bg-amber-50 text-amber-600',   'title' => 'Ograniczenie przetwarzania', 'desc' => 'Tymczasowe zawieszenie konta bez utraty danych.'],
                        ['icon' => 'fa-right-left',    'color' => 'bg-violet-50 text-violet-600', 'title' => 'Przenoszenie danych',        'desc' => 'Format GEDCOM — standard branżowy kompatybilny z Ancestry, MyHeritage, Gramps.'],
                        ['icon' => 'fa-hand',          'color' => 'bg-slate-50 text-slate-600',   'title' => 'Sprzeciw',                   'desc' => 'Wyłączenie marketingu w ustawieniach konta.'],
                        ['icon' => 'fa-robot',         'color' => 'bg-pink-50 text-pink-600',     'title' => 'Brak auto-profilowania',     'desc' => 'System nie stosuje profilowania z istotnym wpływem prawnym.'],
                        ['icon' => 'fa-scale-balanced','color' => 'bg-orange-50 text-orange-600', 'title' => 'Skarga do UODO',             'desc' => 'Prawo wniesienia skargi do Prezesa UODO (uodo.gov.pl).'],
                    ];
                    foreach ($rights as $r):
                    ?>
                    <div class="flex items-start gap-3 rounded-lg border border-border px-3 py-2.5">
                        <div class="h-7 w-7 rounded-md <?= $r['color'] ?> flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fa-solid <?= $r['icon'] ?> text-xs" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-foreground"><?= htmlspecialchars($r['title']) ?></p>
                            <p class="text-[11px] text-muted-foreground mt-0.5 leading-relaxed"><?= htmlspecialchars($r['desc']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-6" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">6</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Odbiorcy danych</h2>
                </div>
                <div class="pl-11 text-sm text-foreground/90 leading-relaxed space-y-2">
                    <p>Dane <strong>nie są udostępniane</strong> stronom trzecim z wyjątkiem:</p>
                    <ul class="space-y-1.5 mt-2">
                        <?php foreach ([
                            'Podmiotów hostingowych — w zakresie niezbędnym do utrzymania usługi.',
                            'Organów państwowych — wyłącznie na podstawie obowiązującego prawa.',
                            'Innych użytkowników Genealog — wyłącznie w zakresie danych udostępnionych przez właściciela drzewa (zaproszeni współpracownicy).',
                        ] as $item): ?>
                        <li class="flex items-start gap-2 text-sm">
                            <i class="fa-solid fa-angle-right text-primary text-xs mt-1 flex-shrink-0" aria-hidden="true"></i>
                            <?= htmlspecialchars($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-7" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">7</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Dane osób trzecich w drzewach genealogicznych</h2>
                </div>
                <div class="pl-11 space-y-3 text-sm text-foreground/90 leading-relaxed">
                    <p>Genealog umożliwia wprowadzanie danych osób trzecich (przodków, krewnych). Użytkownik wprowadzający takie dane jest odpowiedzialny za posiadanie podstawy prawnej.</p>
                    <div class="rounded-lg border border-border overflow-hidden">
                        <?php foreach ([
                            ['icon' => 'fa-hourglass-end', 'color' => 'bg-emerald-50 text-emerald-600 border-emerald-100', 'label' => 'Dane historyczne',         'desc' => 'Osoby zmarłe nie podlegają RODO.'],
                            ['icon' => 'fa-user',          'color' => 'bg-amber-50 text-amber-600 border-amber-100',       'label' => 'Dane żyjących krewnych',    'desc' => 'Wymagają zgody lub innej podstawy prawnej (art. 6 RODO).'],
                            ['icon' => 'fa-lock',          'color' => 'bg-blue-50 text-blue-600 border-blue-100',          'label' => 'Privacy by design',         'desc' => 'Domyślna flaga „żyjący" ogranicza widoczność w globalnym indeksie (art. 25 RODO).'],
                        ] as $row): ?>
                        <div class="flex items-start gap-3 px-4 py-3 border-b border-border last:border-0">
                            <div class="h-6 w-6 rounded-md <?= $row['color'] ?> border flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid <?= $row['icon'] ?> text-[10px]" aria-hidden="true"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-foreground"><?= htmlspecialchars($row['label']) ?></p>
                                <p class="text-xs text-muted-foreground mt-0.5"><?= htmlspecialchars($row['desc']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-8" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">8</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Przekazywanie danych poza EOG</h2>
                </div>
                <div class="pl-11 text-sm text-foreground/90 leading-relaxed space-y-3">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 flex items-start gap-2.5">
                        <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                        <p><strong>Domyślnie</strong> dane osobowe nie są przekazywane poza Europejski Obszar Gospodarczy. Infrastruktura Genealog znajduje się w <?= htmlspecialchars(defined('SERVER_LOCATION') ? SERVER_LOCATION : 'UE') ?>.</p>
                    </div>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 flex items-start gap-2.5">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                        <p><strong>Wyjątek — Discovery Sources:</strong> Na Twoje świadome żądanie, przy włączonej funkcji Discovery, wyszukiwanie w <strong>FamilySearch</strong> (USA) wysyła ograniczone dane (imię, nazwisko, rok urodzenia). Podstawa transferu: Standardowe Klauzule Umowne (SCC, Decyzja 2021/914).</p>
                    </div>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-9" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">9</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Zewnętrzne rejestry (Discovery Sources)</h2>
                </div>
                <div class="pl-11 space-y-4 text-sm text-foreground/90 leading-relaxed">
                    <p>Wyszukiwanie w zewnętrznych bazach genealogicznych wymaga świadomej decyzji — musisz włączyć Discovery w ustawieniach drzewa.</p>

                    <div class="overflow-x-auto rounded-lg border border-border">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-muted/50 border-b border-border">
                                    <th class="px-4 py-2.5 text-left font-semibold text-foreground text-xs">Rejestr</th>
                                    <th class="px-4 py-2.5 text-left font-semibold text-foreground text-xs">Lokalizacja</th>
                                    <th class="px-4 py-2.5 text-left font-semibold text-foreground text-xs">Transfer poza EOG</th>
                                    <th class="px-4 py-2.5 text-left font-semibold text-foreground text-xs">Podstawa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-background text-xs">
                                <?php foreach ([
                                    ['Local / Cross-tree',  'Genealog (UE)', false, 'Zgoda — opt-in Discovery'],
                                    ['Geneteka (PTG)',       'Polska',        false, 'Zgoda — opt-in Discovery'],
                                    ['FamilySearch',        'USA',           true,  'Zgoda + SCC (Decyzja 2021/914)'],
                                ] as [$reg, $loc, $transfer, $basis]): ?>
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-foreground"><?= htmlspecialchars($reg) ?></td>
                                    <td class="px-4 py-2.5 text-muted-foreground"><?= htmlspecialchars($loc) ?></td>
                                    <td class="px-4 py-2.5">
                                        <?php if ($transfer): ?>
                                        <span class="inline-flex items-center gap-1 text-amber-700 font-semibold">
                                            <i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i> Tak (SCC)
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-emerald-700">
                                            <i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i> Nie
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-muted-foreground"><?= htmlspecialchars($basis) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <ul class="space-y-1.5">
                        <?php foreach ([
                            'Wyniki wyszukiwania są prezentowane Tobie — nie są automatycznie importowane.',
                            'Import danych z zewnętrznego rejestru wymaga Twojej decyzji (kliknięcie „Importuj").',
                            'Osoby importowane są oznaczane jako historyczne (privacy by design, Art. 25).',
                            'Możesz wyłączyć konkretne źródła w ustawieniach Discovery w każdej chwili.',
                            'Każde wywołanie zewnętrznego rejestru jest zapisywane w rejestrze czynności (Art. 30) i dostępne w eksporcie danych.',
                        ] as $item): ?>
                        <li class="flex items-start gap-2 text-xs text-muted-foreground">
                            <i class="fa-solid fa-angle-right text-primary text-[10px] mt-1 flex-shrink-0" aria-hidden="true"></i>
                            <?= htmlspecialchars($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-10" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">10</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Bezpieczeństwo <span class="text-xs font-normal text-muted-foreground ml-1">(Art. 32 RODO)</span></h2>
                </div>
                <div class="pl-11 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ([
                        ['fa-lock',          'HTTPS / TLS',          'Szyfrowanie całej transmisji danych.'],
                        ['fa-key',           'bcrypt (cost 12)',      'Hasła przechowywane wyłącznie jako skróty kryptograficzne.'],
                        ['fa-users-gear',    'RBAC',                 'Kontrola dostępu oparta na rolach: owner, editor, viewer.'],
                        ['fa-shield-halved', 'CSRF + Rate limiting',  'Ochrona przed atakami CSRF, brute-force i DDoS.'],
                        ['fa-database',      'Prepared statements',   'Pełna ochrona przed SQL injection.'],
                        ['fa-clipboard-list','Audit trail',           'Logowanie zdarzeń bezpieczeństwa i akcji administracyjnych.'],
                    ] as [$icon, $title, $desc]): ?>
                    <div class="flex items-start gap-3 rounded-lg border border-border px-3 py-2.5">
                        <i class="fa-solid <?= $icon ?> text-primary text-sm mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                        <div>
                            <p class="text-xs font-semibold text-foreground"><?= htmlspecialchars($title) ?></p>
                            <p class="text-[11px] text-muted-foreground mt-0.5"><?= htmlspecialchars($desc) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-11" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">11</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Zmiany polityki</h2>
                </div>
                <div class="pl-11 text-sm text-foreground/90 leading-relaxed">
                    <p>W przypadku istotnych zmian użytkownicy zostaną poinformowani e-mailem. Aktualna wersja jest zawsze dostępna pod adresem <a href="/privacy" class="text-primary hover:underline font-medium">/privacy</a>.</p>
                </div>
            </section>
            <div class="border-b border-border/50 my-6"></div>

            <section id="sec-12" class="scroll-mt-24">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="text-xs font-bold text-primary">12</span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Kontakt</h2>
                </div>
                <div class="pl-11">
                    <div class="rounded-lg border border-border bg-muted/20 px-5 py-4 flex items-center gap-4">
                        <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-envelope text-primary" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">W sprawach ochrony danych osobowych:</p>
                            <a href="mailto:<?= htmlspecialchars(defined('DPO_EMAIL') ? DPO_EMAIL : 'privacy@genealog.pl') ?>"
                               class="text-base font-semibold text-primary hover:underline">
                                <?= htmlspecialchars(defined('DPO_EMAIL') ? DPO_EMAIL : 'privacy@genealog.pl') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

        </article>
    </div>
</div>
