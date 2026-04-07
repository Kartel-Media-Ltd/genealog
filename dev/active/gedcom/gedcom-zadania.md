# GEDCOM Import/Eksport — Checklist zadań

## Status ogólny
- [x] Faza 1: Instalacja biblioteki + szkielet serwisu (własny parser, bez zewnętrznej lib)
- [x] Faza 2: Import (parsowanie + INSERT do bazy)
- [x] Faza 3: Eksport (generowanie GEDCOM string)
- [x] Faza 4: Controller + routing + widok
- [ ] Faza 5: Weryfikacja E2E

---

## Faza 1 — Instalacja biblioteki + szkielet GedcomService

> **Decyzja:** `fisharebest/gedcom` nie istnieje jako standalone pakiet Composer.
> Zaimplementowano własny parser GEDCOM w `GedcomService` — bez zewnętrznych zależności.

- [x] Własny parser GEDCOM w `GedcomService::parseGedcom()` — obsługuje INDI, FAM, tagi 0-3
- [x] Utwórz `src/Services/GedcomService.php` z metodami `import()` i `export()`
- [x] Utwórz klasę `App\Services\ImportResult` (value object: `personsCount`, `relationshipsCount`, `skippedCount`, `errors[]`)

---

## Faza 2 — Import: parsowanie + INSERT do bazy

- [x] Zaimplementuj `GedcomService::import(int $treeId, string $filePath, int $userId): ImportResult`
  - [ ] Wywołanie parsera `fisharebest/gedcom` na `$filePath`
  - [ ] Pętla po INDI (individuals) z mapowaniem na tablicę kolumn DB
  - [ ] Obsługa NAME tag: parsowanie `Imię /Nazwisko/` → first_name + last_name
  - [ ] Obsługa GIVN / SURN jako nadrzędnych wobec NAME
  - [ ] Mapowanie SEX → gender enum
  - [ ] Parsowanie dat: BIRT DATE, DEAT DATE → Y-m-d (ekstrakcja roku dla `ABT`/`BEF`/`AFT`)
  - [ ] Ustawienie `is_living = 0` gdy tag DEAT obecny
  - [ ] Concat wielu NOTE z `\n---\n` jako separator
  - [ ] Zapis `gedcom_xref` z pełnym identyfikatorem `@I1@`
  - [ ] Budowanie mapy `xref → person_id` po INSERT
  - [ ] Pętla po FAM (families):
    - [ ] Wyciąganie HUSB / WIFE / CHIL z mapy xref
    - [ ] INSERT relacji spouse dla pary HUSB+WIFE
    - [ ] INSERT relacji parent (HUSB→CHIL) + child (CHIL→HUSB) dla każdego dziecka
    - [ ] INSERT relacji parent (WIFE→CHIL) + child (CHIL→WIFE) dla każdego dziecka
    - [ ] Skip z logiem gdy xref nie w mapie (osoba z poza importu)
  - [ ] Całość owrapped w `$this->pdo->beginTransaction()` / `commit()` / `rollback()`
  - [ ] `unlink($filePath)` po zakończeniu (sukces lub błąd)
- [ ] Dodaj metodę `PersonRepository::findByXref(int $treeId, string $xref): ?Person`
  - Używana przy `conflictStrategy = 'update'` i do sprawdzania duplikatów
- [ ] Dodaj metodę `PersonRepository::createBatch(array $persons): array` — opcjonalnie dla wydajności
- [ ] Testy ręczne: import małego pliku .ged (Gramps sample file)

---

## Faza 3 — Eksport: generowanie GEDCOM string

- [ ] Zaimplementuj `GedcomService::export(int $treeId): string`
  - [ ] Wywołaj `PersonRepository::findByTree($treeId)` — tablica wszystkich osób
  - [ ] Wywołaj `RelationshipRepository::findByTree($treeId)` — wszystkie relacje
  - [ ] Zaimplementuj `buildHeader(): string`
    ```
    0 HEAD
    1 SOUR Genealog
    2 VERS 1.0
    1 GEDC
    2 VERS 5.5.1
    2 FORM LINEAGE-LINKED
    1 CHAR UTF-8
    1 DATE {dzisiaj: DD MMM YYYY}
    ```
  - [ ] Zaimplementuj `buildINDI(Person $person): string` — pełny rekord INDI
    - [ ] Obsługa brakujących pól (opcjonalne tagi tylko gdy wartość != null)
    - [ ] `formatGedcomDate()`: Y-m-d → `DD MMM YYYY` (np. `1850-03-15` → `15 MAR 1850`)
    - [ ] Łamanie długich NOTE na 248 znaków (limit linii GEDCOM)
  - [ ] Zaimplementuj grupowanie relacji → pary FAM:
    - Każda para spouse tworzy jeden FAM
    - Dodaj dzieci: znajdź osoby gdzie oba parents z FAM mają relację `parent` do dziecka
    - Obsługa samotnych rodziców (tylko HUSB lub tylko WIFE w FAM)
  - [ ] Zaimplementuj `buildFAM(array $family, int $famIndex): string`
  - [ ] Concat: header + INDI records + FAM records + footer (`0 TRLR`)
- [ ] Dodaj metodę `RelationshipRepository::findByTree(int $treeId): array`
- [ ] Testy ręczne: eksport drzewa → import do Gramps/Ancestry (weryfikacja kompatybilności)

---

## Faza 4 — Controller + routing + widok

- [ ] Utwórz `src/Controllers/GedcomController.php`
  - [ ] Metoda `page(Request $request, int $treeId)`:
    - Pobierz dane drzewa, sprawdź dostęp
    - Render widoku `pages/trees/gedcom.php`
  - [ ] Metoda `import(Request $request, int $treeId)`:
    - [ ] Weryfikacja CSRF tokena
    - [ ] Sprawdzenie `$_FILES['gedcom_file']` — obecność, brak błędu uploadu
    - [ ] Walidacja rozszerzenia: `strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'ged'`
    - [ ] Walidacja rozmiaru: `$_FILES['gedcom_file']['size'] <= 50 * 1024 * 1024`
    - [ ] Walidacja MIME przez `finfo_file()` — akceptowane: `text/plain`, `text/x-gedcom`
    - [ ] `move_uploaded_file()` → `sys_get_temp_dir() . '/' . bin2hex(random_bytes(8)) . '.ged'`
    - [ ] Wywołanie `GedcomService::import()`
    - [ ] Flash success/error + redirect
  - [ ] Metoda `export(Request $request, int $treeId)`:
    - [ ] Sprawdzenie dostępu do drzewa
    - [ ] Wywołanie `GedcomService::export()`
    - [ ] Ustawienie headerów HTTP i wysłanie stringa
- [ ] Zarejestruj trasy w Routerze:
  ```php
  $router->get('/trees/{id}/gedcom', [GedcomController::class, 'page']);
  $router->post('/trees/{id}/gedcom/import', [GedcomController::class, 'import']);
  $router->get('/trees/{id}/gedcom/export', [GedcomController::class, 'export']);
  ```
- [ ] Utwórz widok `src/Views/pages/trees/gedcom.php`:
  - [ ] Sekcja importu: formularz `enctype="multipart/form-data"`, pole `<input type="file" accept=".ged">`, CSRF hidden, przycisk wyślij
  - [ ] Sekcja eksportu: przycisk/link do `GET /trees/{id}/gedcom/export`
  - [ ] Flash messages (sukces/błąd)
  - [ ] Informacja o limicie 50 MB
  - [ ] Opcja `conflict_strategy` (radio: skip/update) dla zaawansowanych

---

## Faza 5 — Weryfikacja E2E

- [ ] Test importu: pobierz przykładowy plik .ged z Gramps wiki lub wygeneruj ręcznie
- [ ] Sprawdź w bazie: poprawna liczba osób, relacji, wartości gedcom_xref
- [ ] Test eksportu: pobierz .ged, otwórz w Gramps — zweryfikuj poprawność
- [ ] Test importu własnego eksportu (round-trip): brak utraty danych
- [ ] Test bezpieczeństwa:
  - [ ] Upload pliku > 50 MB → komunikat błędu
  - [ ] Upload pliku z rozszerzeniem .php → odrzucenie
  - [ ] Próba importu do cudzego drzewa → 403
  - [ ] Import bez CSRF tokena → odrzucenie
- [ ] Test duplikatów: import tego samego pliku dwukrotnie — skip lub update wg strategii
- [ ] Test pliku ANSEL-encoded — weryfikacja konwersji znaków
- [ ] Code review przez `/dev-docs-review`

---

## Notatki implementacyjne

- Plik tymczasowy: zawsze usuwaj `unlink()` po przetworzeniu — nawet przy wyjątku (try/finally)
- Ograniczenie pamięci PHP: dla dużych plików (>10k osób) rozważ streaming parsera zamiast ładowania całości do RAM
- Timeout: dla dużych importów rozważ `set_time_limit(300)` lub przeniesienie do tła (job queue w `search_jobs`)
- Logowanie: każdy import loguj do tabeli `audit_log` (opcjonalnie) z user_id, tree_id, timestamp, wynikiem

---

## Do poprawy po review

- [x] 🔴 [blocking] **GedcomController.php:152** — Eksport buforuje cały plik w RAM; dodać `Content-Length` + rozważyć streaming dla dużych plików
- [x] 🟠 [important] **GedcomService.php:350** — Relacja `spouse` duplikuje się przy reimporcie gdy UUID zmienią kolejność; sprawdzać oba kierunki w `exists()` dla spouse
- [x] 🟠 [important] **GedcomController.php:159** — Błąd eksportu ujawnia wiadomość wyjątku PDO; zalogować + generyczny komunikat (analogicznie jak w imporcie)
- [x] 🟠 [important] **GedcomService.php:617** — `parseGedcomDate()` nie obsługuje `BET DD MON YYYY AND DD MON YYYY`; rozszerzyć regex
- [x] 🟡 [nit] **GedcomService.php:697** — `str_split()` tnie UTF-8 na bajtach; zmienić na `mb_str_split()`
- [x] 🟡 [nit] **GedcomService.php:655** — `if (count($parts) < 1)` zawsze false; usunąć martwy warunek
