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

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">1. Administrator danych</h2>
        <p>
            Administratorem danych osobowych jest <strong>[NAZWA ADMINISTRATORA]</strong>,
            z siedzibą w [ADRES], NIP: [NIP].
        </p>
        <p>Kontakt w sprawach ochrony danych: <strong>[EMAIL DPO]</strong>.</p>
    </section>

    <section class="mb-6">
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

    <section class="mb-6">
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

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">4. Okres przechowywania (Art. 5(1)(e) RODO)</h2>
        <ul class="list-disc list-inside space-y-1">
            <li>Dane konta — do czasu jego usunięcia przez użytkownika.</li>
            <li>Dane genealogiczne — do czasu usunięcia drzewa lub konta właściciela.</li>
            <li>Logi audytowe — <strong>3 lata</strong> (wymogi NIS2 + bezpieczeństwo).</li>
            <li>Tokeny resetu hasła — <strong>1 godzina</strong> (TTL), kasowanie po 24h od wygaśnięcia.</li>
            <li>Logi rate limitera — 15 minut.</li>
        </ul>
    </section>

    <section class="mb-6">
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

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">6. Odbiorcy danych</h2>
        <p>Dane nie są udostępniane stronom trzecim z wyjątkiem:</p>
        <ul class="list-disc list-inside space-y-1">
            <li>Podmiotów hostingowych — w zakresie niezbędnym do utrzymania usługi.</li>
            <li>Organów państwowych — wyłącznie na podstawie obowiązującego prawa.</li>
            <li>Innych użytkowników Genealog — wyłącznie w zakresie danych udostępnionych
                przez właściciela drzewa (zaproszeni współpracownicy).</li>
        </ul>
    </section>

    <section class="mb-6">
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

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">8. Przekazywanie danych poza EOG</h2>
        <p>
            Dane nie są przekazywane poza Europejski Obszar Gospodarczy. Infrastruktura Genealog
            znajduje się w [LOKALIZACJA SERWERA].
        </p>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">9. Bezpieczeństwo (Art. 32 RODO)</h2>
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

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">10. Zmiany polityki</h2>
        <p>
            W przypadku istotnych zmian niniejszej polityki użytkownicy zostaną poinformowani
            e-mailem na adres powiązany z kontem. Aktualna wersja jest zawsze dostępna pod adresem
            <a href="/privacy" class="underline">/privacy</a>.
        </p>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">11. Kontakt</h2>
        <p>
            W sprawach związanych z ochroną danych osobowych prosimy o kontakt na adres:
            <strong>[EMAIL DPO]</strong>.
        </p>
    </section>
</main>
