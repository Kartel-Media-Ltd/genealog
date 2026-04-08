<?php declare(strict_types=1); ?>
<main id="main-content" class="max-w-2xl mx-auto py-10 px-4 prose prose-sm max-w-none">
    <nav class="mb-6 text-sm text-muted-foreground" aria-label="Nawigacja">
        <a href="/" class="hover:text-foreground transition-colors">Strona główna</a>
        <span class="mx-2">/</span>
        <span class="text-foreground font-medium">Regulamin</span>
    </nav>

    <h1 class="text-3xl font-bold tracking-tight text-foreground mb-2">Regulamin serwisu Genealog</h1>
    <p class="text-sm text-muted-foreground mb-8">
        Ostatnia aktualizacja: <?= htmlspecialchars(date('Y-m-d')) ?>
    </p>

    <div class="rounded-md border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900 mb-6">
        <strong>Dokument roboczy.</strong> Treść merytoryczna wymaga weryfikacji przez radcę prawnego
        przed uruchomieniem produkcyjnym.
    </div>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§1. Postanowienia ogólne</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Niniejszy regulamin określa zasady korzystania z serwisu Genealog (dalej: „Serwis"),
                dostępnego pod adresem <?= htmlspecialchars(defined('SITE_URL') ? SITE_URL : '[TODO]') ?>.</li>
            <li>Usługodawcą jest <strong><?= htmlspecialchars(defined('COMPANY_NAME') ? COMPANY_NAME : '[TODO]') ?></strong>, dalej „Usługodawca".</li>
            <li>Użytkownikiem jest każda osoba fizyczna, która korzysta z Serwisu, zarówno jako
                użytkownik zarejestrowany, jak i niezalogowany.</li>
            <li>Korzystanie z Serwisu oznacza akceptację niniejszego regulaminu.</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§2. Usługi</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Serwis umożliwia budowanie drzew genealogicznych rodziny, zapraszanie innych
                użytkowników do współpracy, import i eksport danych w formacie GEDCOM.</li>
            <li>Podstawowe funkcjonalności Serwisu są dostępne bezpłatnie.</li>
            <li>Usługodawca zastrzega sobie prawo do wprowadzania zmian w zakresie i sposobie
                świadczenia usług z zachowaniem praw nabytych użytkowników.</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§3. Rejestracja</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Rejestracja wymaga podania imienia/nazwiska, adresu e-mail i hasła.</li>
            <li>Użytkownik zobowiązuje się do podawania danych prawdziwych i kompletnych.</li>
            <li>Minimalna długość hasła: 12 znaków, z cyfrą lub znakiem specjalnym.</li>
            <li>Jedno konto odpowiada jednej osobie fizycznej. Dzielenie konta z osobami trzecimi
                jest zabronione.</li>
            <li>Przy rejestracji Użytkownik akceptuje niniejszy Regulamin i Politykę prywatności.
                Akceptacja jest zapisywana w bazie danych wraz z wersją dokumentu (RODO Art. 7(1)).</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§3a. Integracje z zewnętrznymi rejestrami (Discovery)</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Serwis umożliwia opcjonalne wyszukiwanie osób w zewnętrznych rejestrach genealogicznych
                (FamilySearch, Geneteka, inne wewnętrzne źródła). Funkcja jest domyślnie <strong>wyłączona</strong>.</li>
            <li>Włączenie Discovery dla drzewa oznacza zgodę na wysyłanie ograniczonych danych
                (imię, nazwisko, rok urodzenia) do wybranych rejestrów w momencie Twojego ręcznego
                wyszukiwania.</li>
            <li>W przypadku rejestru FamilySearch (USA) obowiązują Standardowe Klauzule Umowne —
                szczegóły w Polityce Prywatności, sekcja 8 i 9.</li>
            <li>Wyniki wyszukiwania są prezentowane Użytkownikowi — nie są automatycznie importowane.
                Import wymaga osobnej decyzji.</li>
            <li>Użytkownik może w każdej chwili wyłączyć Discovery dla drzewa w ustawieniach.</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§4. Dane osób trzecich w drzewach</h2>
        <p class="font-semibold text-foreground">Szczególnie istotne:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Użytkownik wprowadzający dane osób trzecich (krewnych, przodków) oświadcza, że ma
                podstawę prawną do ich przetwarzania zgodnie z RODO.</li>
            <li>Za dane wprowadzone do drzew genealogicznych odpowiada <strong>użytkownik</strong>,
                nie Usługodawca.</li>
            <li>Serwis zapewnia mechanizmy ochrony prywatności żyjących osób:
                <ul class="list-disc list-inside ml-4 mt-1">
                    <li>Domyślna flaga „is_living=1" dla nowych osób.</li>
                    <li>Ograniczenie widoczności w globalnym indeksie wyszukiwania.</li>
                    <li>Filtrowanie eksportu GEDCOM (tylko rok urodzenia dla żyjących).</li>
                </ul>
            </li>
            <li>Jeśli osoba trzecia zgłosi żądanie usunięcia swoich danych, Usługodawca zwróci się
                do właściciela drzewa o podjęcie odpowiednich działań w terminie 14 dni.</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§5. Zabronione użycie</h2>
        <p>Użytkownik zobowiązuje się nie:</p>
        <ul class="list-disc list-inside space-y-1">
            <li>Umieszczać treści niezgodnych z prawem, dyskryminujących, obraźliwych.</li>
            <li>Naruszać praw autorskich lub praw własności intelektualnej osób trzecich.</li>
            <li>Podejmować prób ataków hakerskich, DDoS, exploitu podatności.</li>
            <li>Wykorzystywać Serwisu do masowej rejestracji lub spamu.</li>
            <li>Udostępniać poufnych danych osób żyjących bez ich zgody.</li>
            <li>Wykorzystywać scraping'u lub automatyzowanego pobierania danych z Serwisu
                w sposób obciążający infrastrukturę.</li>
        </ul>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§6. Prawa autorskie</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Prawa autorskie do Serwisu (kod, design, nazwa) przysługują Usługodawcy.</li>
            <li>Użytkownik zachowuje prawa autorskie do danych wprowadzonych przez siebie.</li>
            <li>Udostępniając dane w Serwisie użytkownik udziela Usługodawcy niewyłącznej licencji
                na ich przetwarzanie w zakresie niezbędnym do świadczenia usługi.</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§7. Odpowiedzialność</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Usługodawca dokłada starań, by Serwis był dostępny i bezpieczny, ale nie gwarantuje
                ciągłości działania ani wolności od błędów.</li>
            <li>Usługodawca nie odpowiada za utracone dane w zakresie większym niż wynikający
                z obowiązku zapewnienia kopii zapasowych (RPO 24h, RTO 4h — szczegóły w
                <a href="/privacy" class="underline">Polityce Prywatności</a>).</li>
            <li>Użytkownik jest odpowiedzialny za regularny eksport własnych danych jako kopii
                zapasowej (funkcja dostępna w ustawieniach).</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§8. Zakończenie świadczenia usługi</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Użytkownik może w każdym momencie usunąć swoje konto (funkcja w ustawieniach).</li>
            <li>Usługodawca może zablokować lub usunąć konto użytkownika w przypadku naruszenia
                niniejszego regulaminu.</li>
            <li>Po usunięciu konta dane są anonimizowane zgodnie z polityką prywatności.</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§9. Reklamacje</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Reklamacje należy zgłaszać na adres <?= htmlspecialchars(defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '[TODO]') ?>.</li>
            <li>Usługodawca rozpatrzy reklamację w terminie 14 dni roboczych.</li>
        </ol>
    </section>

    <section class="mb-6">
        <h2 class="text-xl font-semibold mt-6 mb-3">§10. Postanowienia końcowe</h2>
        <ol class="list-decimal list-inside space-y-1">
            <li>Regulamin może zostać zmieniony. O zmianach użytkownicy zostaną poinformowani
                e-mailem na adres podany przy rejestracji z co najmniej 14-dniowym wyprzedzeniem.</li>
            <li>W sprawach nieuregulowanych stosuje się przepisy prawa polskiego, w szczególności
                Kodeksu Cywilnego i RODO.</li>
            <li>Spory wynikające z korzystania z Serwisu rozstrzyga sąd właściwy dla siedziby
                Usługodawcy.</li>
        </ol>
    </section>
</main>
