<?php declare(strict_types=1); ?>
<main id="main-content" class="max-w-3xl mx-auto py-10 px-4 prose prose-sm">
    <nav class="mb-6 text-sm text-muted-foreground" aria-label="Nawigacja">
        <a href="/" class="hover:text-foreground transition-colors">Strona główna</a>
        <span class="mx-2">/</span>
        <span class="text-foreground font-medium">Polityka Prywatności</span>
    </nav>

    <h1 class="text-3xl font-bold tracking-tight text-foreground mb-2">Polityka Prywatności</h1>
    <p class="text-sm text-muted-foreground mb-8">
        Ostatnia aktualizacja: <?= htmlspecialchars(date('Y-m-d')) ?>
    </p>

    <div class="rounded-md border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900 mb-6">
        <strong>Dokument roboczy.</strong> Treść merytoryczna wymaga weryfikacji przez radcę prawnego
        przed uruchomieniem produkcyjnym. Szablon oparty o RODO Art. 13-14.
    </div>

    <!-- ZAD-3.8 (D8): Table of Contents — WCAG 2.4.5 (multiple ways) -->
    <nav aria-label="Spis treści" class="my-6 rounded-md border border-border bg-muted/30 p-4">
        <h2 class="text-sm font-semibold mb-2">Spis treści</h2>
        <ol class="text-sm space-y-1 list-decimal list-inside text-muted-foreground">
            <li><a href="#sec-1"  class="hover:underline hover:text-foreground">Administrator danych</a></li>
            <li><a href="#sec-2"  class="hover:underline hover:text-foreground">Cel i podstawa przetwarzania</a></li>
            <li><a href="#sec-3"  class="hover:underline hover:text-foreground">Zakres przetwarzanych danych</a></li>
            <li><a href="#sec-4"  class="hover:underline hover:text-foreground">Okres przechowywania</a></li>
            <li><a href="#sec-5"  class="hover:underline hover:text-foreground">Twoje prawa</a></li>
            <li><a href="#sec-6"  class="hover:underline hover:text-foreground">Odbiorcy danych</a></li>
            <li><a href="#sec-7"  class="hover:underline hover:text-foreground">Dane osób trzecich</a></li>
            <li><a href="#sec-8"  class="hover:underline hover:text-foreground">Przekazywanie poza EOG</a></li>
            <li><a href="#sec-9"  class="hover:underline hover:text-foreground">Zewnętrzne rejestry (Discovery)</a></li>
            <li><a href="#sec-10" class="hover:underline hover:text-foreground">Bezpieczeństwo</a></li>
            <li><a href="#sec-11" class="hover:underline hover:text-foreground">Zmiany polityki</a></li>
            <li><a href="#sec-12" class="hover:underline hover:text-foreground">Kontakt</a></li>
        </ol>
    </nav>

    <section class="mb-6" id="sec-1">
        <h2 class="text-xl font-semibold mt-6 mb-3">1. Administrator danych</h2>
        <p>
            Administratorem danych osobowych jest <strong><?= htmlspecialchars(defined('COMPANY_NAME') ? COMPANY_NAME : '[TODO]') ?></strong>,
            z siedzibą <?= htmlspecialchars(defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '[TODO]') ?>,
            NIP: <?= htmlspecialchars(defined('COMPANY_NIP') ? COMPANY_NIP : '[TODO]') ?>.
        </p>
        <p>Kontakt w sprawach ochrony danych: <strong><?= htmlspecialchars(defined('DPO_EMAIL') ? DPO_EMAIL : '[TODO]') ?></strong>.</p>
    </section>

    <section class="mb-6" id="sec-2">
        <h2 class="text-xl font-semibold mt-6 mb-3">2. Cel i podstawa przetwarzania (Art. 13 RODO)</h2>
        <ul class="list-disc list-inside space-y-1">
            <li><strong>Prowadzenie konta użytkownika</strong> — podstawa: art. 6(1)(b) RODO (wykonanie umowy).</li>
            <li><strong>Przechowywanie danych genealogicznych</strong> (drzewa, osoby, relacje, zdjęcia) —
                podstawa: art. 6(1)(b) RODO (wykonanie umowy).</li>
            <li><strong>Zapewnienie bezpieczeństwa systemu</strong> (logi audytowe, rate limiting) —
                podstawa: art. 6(1)(f) RODO (prawnie uzasadniony interes administratora).</li>
            <li><strong>Powiadomienia e-mail o aktywnościach w drzewach</strong> —
                podstawa: art. 6(1)(a) RODO (zgoda). Możesz wycofać w ustawieniach w każdym momencie.</li>
            <li><strong>Obsługa zgłoszeń i kontakt</strong> — podstawa: art. 6(1)(f) RODO.</li>
        </ul>
    </section>

    <section class="mb-6" id="sec-3">
        <h2 class="text-xl font-semibold mt-6 mb-3">3. Zakres przetwarzanych danych</h2>
        <ul class="list-disc list-inside space-y-1">
            <li><strong>Dane konta:</strong> imię i nazwisko, adres e-mail, hasło (przechowywane
                wyłącznie w formie skrótu bcrypt), język interfejsu.</li>
            <li><strong>Dane genealogiczne dodane przez użytkownika:</strong> dane osób (imiona,
                daty urodzenia/śmierci, miejsca, relacje rodzinne), zdjęcia, dokumenty.</li>
            <li><strong>Dane techniczne:</strong> adres IP, data i godzina logowania,
                identyfikator sesji, typ przeglądarki (logi dostępu).</li>
            <li><strong>Logi audytowe:</strong> akcje administracyjne, importy i eksporty danych,
                zmiany uprawnień.</li>
        </ul>
    </section>

    <section class="mb-6" id="sec-4">
        <h2 class="text-xl font-semibold mt-6 mb-3">4. Okres przechowywania (Art. 5(1)(e) RODO)</h2>
        <ul class="list-disc list-inside space-y-1">
            <li>Dane konta — do czasu jego usunięcia przez użytkownika.</li>
            <li>Dane genealogiczne — do czasu usunięcia drzewa lub konta właściciela.</li>
            <li>Logi audytowe — <strong>3 lata</strong> (wymogi NIS2 + bezpieczeństwo).</li>
            <li>Tokeny resetu hasła — <strong>1 godzina</strong> (TTL), kasowanie po 24h od wygaśnięcia.</li>
            <li>Logi rate limitera — 15 minut.</li>
        </ul>
    </section>

    <section class="mb-6" id="sec-5">
        <h2 class="text-xl font-semibold mt-6 mb-3">5. Twoje prawa (Art. 15-22 RODO)</h2>
        <p>Jako osoba, której dane dotyczą, masz prawo do:</p>
        <ul class="list-disc list-inside space-y-1">
            <li><strong>Dostępu do danych</strong> — eksport pełnego archiwum w formacie ZIP
                (JSON + GEDCOM) z poziomu ustawień konta.</li>
            <li><strong>Sprostowania</strong> — edycja danych profilu i osób w drzewach.</li>
            <li><strong>Usunięcia</strong> — pełna anonimizacja konta z poziomu ustawień
                (Strefa Niebezpieczna).</li>
            <li><strong>Ograniczenia przetwarzania</strong> — tymczasowe zawieszenie konta
                bez utraty danych.</li>
            <li><strong>Przenoszenia danych</strong> — format GEDCOM jest standardem branżowym,
                umożliwia import do dowolnego systemu genealogicznego (Ancestry, MyHeritage,
                FamilySearch, Gramps).</li>
            <li><strong>Sprzeciwu</strong> wobec marketingu — toggle w ustawieniach.</li>
            <li><strong>Niepodlegania decyzjom zautomatyzowanym</strong> — system nie stosuje
                profilowania automatycznego z istotnym wpływem prawnym.</li>
            <li><strong>Wniesienia skargi</strong> do Prezesa Urzędu Ochrony Danych Osobowych
                (<a href="https://uodo.gov.pl" target="_blank" rel="noopener" class="underline">uodo.gov.pl</a>).</li>
        </ul>
    </section>

    <section class="mb-6" id="sec-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">6. Odbiorcy danych</h2>
        <p>Dane nie są udostępniane stronom trzecim z wyjątkiem:</p>
        <ul class="list-disc list-inside space-y-1">
            <li>Podmiotów hostingowych — w zakresie niezbędnym do utrzymania usługi.</li>
            <li>Organów państwowych — wyłącznie na podstawie obowiązującego prawa.</li>
            <li>Innych użytkowników Genealog — wyłącznie w zakresie danych udostępnionych
                przez właściciela drzewa (zaproszeni współpracownicy).</li>
        </ul>
    </section>

    <section class="mb-6" id="sec-7">
        <h2 class="text-xl font-semibold mt-6 mb-3">7. Dane osób trzecich w drzewach genealogicznych</h2>
        <p>
            Genealog umożliwia wprowadzanie danych osób trzecich (przodków, krewnych). Użytkownik
            wprowadzający takie dane jest odpowiedzialny za posiadanie podstawy prawnej
            przetwarzania tych danych, w szczególności:
        </p>
        <ul class="list-disc list-inside space-y-1">
            <li>Dane <strong>historyczne</strong> (osoby zmarłe) nie podlegają RODO.</li>
            <li>Dane <strong>żyjących członków rodziny</strong> wymagają zgody lub innej
                podstawy prawnej (art. 6 RODO).</li>
            <li>Genealog domyślnie oznacza osoby jako <strong>żyjące</strong> i ogranicza ich
                widoczność w globalnym indeksie (privacy by design — art. 25 RODO).</li>
        </ul>
    </section>

    <section class="mb-6" id="sec-8">
        <h2 class="text-xl font-semibold mt-6 mb-3">8. Przekazywanie danych poza EOG</h2>
        <p>
            <strong>Domyślnie</strong> dane osobowe nie są przekazywane poza Europejski Obszar
            Gospodarczy. Infrastruktura Genealog znajduje się w
            <?= htmlspecialchars(defined('SERVER_LOCATION') ? SERVER_LOCATION : '[TODO]') ?> (UE).
        </p>
        <p class="mt-3">
            <strong>Wyjątek — Discovery Sources:</strong> Na Twoje świadome żądanie, Genealog może
            inicjować wyszukiwanie osób w zewnętrznych rejestrach genealogicznych (patrz sekcja 9).
            W przypadku rejestru <strong>FamilySearch</strong> (USA) wysyłane są ograniczone dane
            (imię, nazwisko, rok urodzenia osoby której szukasz). Podstawa transferu: Standardowe
            Klauzule Umowne (<em>Standard Contractual Clauses</em>) zgodnie z Decyzją Wykonawczą
            Komisji 2021/914. Transfer następuje wyłącznie po włączeniu przez Ciebie opcji
            Discovery w ustawieniach drzewa.
        </p>
    </section>

    <section class="mb-6" id="sec-9">
        <h2 class="text-xl font-semibold mt-6 mb-3">9. Zewnętrzne rejestry (Discovery Sources)</h2>
        <p>
            Genealog umożliwia wyszukiwanie osób w zewnętrznych bazach genealogicznych.
            Wyszukiwanie wymaga Twojej świadomej decyzji — musisz włączyć opcję Discovery
            w ustawieniach drzewa, a następnie kliknąć "Szukaj w [nazwa rejestru]" dla konkretnej
            osoby.
        </p>
        <div class="overflow-x-auto my-4">
            <table class="min-w-full text-sm border border-border">
                <thead class="bg-muted">
                    <tr>
                        <th class="border border-border px-3 py-2 text-left">Rejestr</th>
                        <th class="border border-border px-3 py-2 text-left">Lokalizacja</th>
                        <th class="border border-border px-3 py-2 text-left">Transfer poza EOG</th>
                        <th class="border border-border px-3 py-2 text-left">Podstawa prawna</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-border px-3 py-2">Local / Cross-tree</td>
                        <td class="border border-border px-3 py-2">Genealog (UE)</td>
                        <td class="border border-border px-3 py-2">Nie</td>
                        <td class="border border-border px-3 py-2">Zgoda — opt-in Discovery</td>
                    </tr>
                    <tr>
                        <td class="border border-border px-3 py-2">Geneteka (PTG)</td>
                        <td class="border border-border px-3 py-2">Polska</td>
                        <td class="border border-border px-3 py-2">Nie</td>
                        <td class="border border-border px-3 py-2">Zgoda — opt-in Discovery</td>
                    </tr>
                    <tr>
                        <td class="border border-border px-3 py-2">FamilySearch</td>
                        <td class="border border-border px-3 py-2">USA</td>
                        <td class="border border-border px-3 py-2"><strong>Tak (SCC)</strong></td>
                        <td class="border border-border px-3 py-2">Zgoda + Standard Contractual Clauses</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p>
            <strong>Zasady:</strong>
        </p>
        <ul class="list-disc list-inside space-y-1">
            <li>Wyniki wyszukiwania są prezentowane Tobie — <strong>nie są automatycznie importowane</strong>.</li>
            <li>Import danych z zewnętrznego rejestru wymaga Twojej decyzji (klik „Importuj").</li>
            <li>Osoby importowane z zewnętrznych rejestrów są oznaczane jako <strong>historyczne</strong>
                (niezyjące) — privacy by design (RODO Art. 25).</li>
            <li>Możesz w każdym momencie wyłączyć konkretne źródła w ustawieniach Discovery.</li>
            <li>Każde wywołanie zewnętrznego rejestru jest zapisywane w Twoim rejestrze
                czynności przetwarzania (RODO Art. 30) — dostępne w eksporcie danych.</li>
        </ul>
        <p class="mt-3 text-xs text-muted-foreground">
            Jeśli nie chcesz korzystać z Discovery, pozostaw opcję wyłączoną w ustawieniach
            drzewa. Bez aktywnego Discovery Twoje dane nigdy nie opuszczają infrastruktury
            Genealog w UE.
        </p>
    </section>

    <section class="mb-6" id="sec-10">
        <h2 class="text-xl font-semibold mt-6 mb-3">10. Bezpieczeństwo (Art. 32 RODO)</h2>
        <p>Stosowane środki techniczne i organizacyjne:</p>
        <ul class="list-disc list-inside space-y-1">
            <li>Szyfrowanie transmisji (HTTPS/TLS).</li>
            <li>Hasła przechowywane w postaci skrótów bcrypt (cost factor 12).</li>
            <li>Kontrola dostępu oparta na rolach (RBAC) — owner, editor, viewer.</li>
            <li>Ochrona przed atakami: CSRF tokens, rate limiting, prepared statements (ochrona
                przed SQL injection), walidacja inputu.</li>
            <li>Regularne testy bezpieczeństwa (OWASP Top 10, pentesty).</li>
            <li>Logowanie zdarzeń bezpieczeństwa (audit trail).</li>
        </ul>
    </section>

    <section class="mb-6" id="sec-11">
        <h2 class="text-xl font-semibold mt-6 mb-3">11. Zmiany polityki</h2>
        <p>
            W przypadku istotnych zmian niniejszej polityki użytkownicy zostaną poinformowani
            e-mailem na adres powiązany z kontem. Aktualna wersja jest zawsze dostępna pod adresem
            <a href="/privacy" class="underline">/privacy</a>.
        </p>
    </section>

    <section class="mb-6" id="sec-12">
        <h2 class="text-xl font-semibold mt-6 mb-3">12. Kontakt</h2>
        <p>
            W sprawach związanych z ochroną danych osobowych prosimy o kontakt na adres:
            <strong><?= htmlspecialchars(defined('DPO_EMAIL') ? DPO_EMAIL : '[TODO]') ?></strong>.
        </p>
    </section>
</main>
