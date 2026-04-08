# GEDCOM Import/Eksport — Checklist zadań

## Status ogólny
- [x] Faza 1: Instalacja biblioteki + szkielet serwisu (własny parser, bez zewnętrznej lib)
- [x] Faza 2: Import (parsowanie + INSERT do bazy)
- [x] Faza 3: Eksport (generowanie GEDCOM string)
- [x] Faza 4: Controller + routing + widok
- [x] Faza 5: Weryfikacja (syntax check + unit tests PASS; E2E manualne — patrz niżej)

---

## Faza 1 — Instalacja biblioteki + szkielet GedcomService

> **Decyzja:** `fisharebest/gedcom` nie istnieje jako standalone pakiet Composer.
> Zaimplementowano własny parser GEDCOM w `GedcomService` — bez zewnętrznych zależności.

- [x] Własny parser GEDCOM w `GedcomService::parseGedcom()` — obsługuje INDI, FAM, tagi 0-3
- [x] Utwórz `src/Services/GedcomService.php` z metodami `import()` i `export()`
- [x] Utwórz klasę `App\Services\ImportResult` (value object: `personsCount`, `relationshipsCount`, `skippedCount`, `errors[]`)

---

## Faza 2 — Import: parsowanie + INSERT do bazy

- [x] Zaimplementuj `GedcomService::import(string $treeId, string $filePath, string $userId): ImportResult`
  - [x] Parsowanie własnym parserem (poziomy 0-3, tagi INDI/FAM/NOTE/SOUR/BIRT/DEAT/MARR)
  - [x] Pętla po INDI z mapowaniem na kolumny DB
  - [x] Obsługa NAME tag: parsowanie `Imię /Nazwisko/` → first_name + last_name
  - [x] Obsługa GIVN / SURN jako nadrzędnych wobec NAME
  - [x] Mapowanie SEX → gender enum
  - [x] Parsowanie dat: BIRT DATE, DEAT DATE → Y-m-d (ABT/BEF/AFT/BET...AND)
  - [x] Ustawienie `is_living = 0` gdy tag DEAT obecny
  - [x] Concat wielu NOTE z `\n---\n` jako separator
  - [x] Zapis `gedcom_xref` z pełnym identyfikatorem `@I1@`
  - [x] Budowanie mapy `xref → person_id` po INSERT
  - [x] Pętla po FAM: HUSB/WIFE/CHIL z mapy xref
  - [x] INSERT relacji spouse, parent/child dla obu rodziców
  - [x] Skip z logiem gdy xref nie w mapie
  - [x] Całość w `pdo->beginTransaction()` / `commit()` / `rollback()`
  - [x] `unlink($filePath)` w finally
- [x] `PersonRepository::findByXref(string $treeId, string $xref): ?Person` — linia 106
- [x] `RelationshipRepository::findByTree(string $treeId): array` — linia 34
- [x] `GedcomService::validateGedcomStructure()` — weryfikuje HEAD + TRLR + niepuste INDI/FAM

---

## Faza 3 — Eksport: generowanie GEDCOM string

- [x] Zaimplementuj `GedcomService::export(string $treeId): string`
  - [x] `buildHeader()` — HEAD + SOUR + GEDC + CHAR + DATE
  - [x] `buildINDI(Person $person)` — pełny rekord INDI z opcjonalnymi tagami
  - [x] `formatGedcomDate()` — Y-m-d → `DD MMM YYYY`
  - [x] `chunkString()` — mb_str_split(str, 248, UTF-8) dla NOTE
  - [x] Grupowanie relacji → FAM (para spouse + ich dzieci)
  - [x] `buildFAM()` z deduplikacją `$parentChildSet`
  - [x] Concat: header + INDI + FAM + `0 TRLR`
- [x] `RelationshipRepository::findByTree(string $treeId): array`

---

## Faza 4 — Controller + routing + widok

- [x] `src/Controllers/GedcomController.php` z `page()`, `import()`, `export()`
- [x] Walidacja: CSRF, obecność pliku, rozszerzenie `.ged`, rozmiar ≤50 MB, MIME
- [x] `move_uploaded_file()` → sys_get_temp_dir + hex name
- [x] Flash success/error + redirect po imporcie
- [x] `Content-Length` header w eksporcie + ogólny błąd (nie stack trace)
- [x] `gedcom_import_errors` w sesji — pierwsze 10 błędów do wyświetlenia w UI
- [x] Trasy: GET/POST `/trees/{id}/gedcom`, GET `/trees/{id}/gedcom/export`
- [x] Widok `src/views/pages/trees/gedcom.php` z formularzem import + button eksport

---

## Faza 5 — Weryfikacja

- [x] PHP syntax check: `php -l GedcomService.php` → OK
- [x] PHP syntax check: `php -l GedcomController.php` → OK
- [x] Unit tests: 70/70 green (2026-04-08)
- [ ] **E2E manual** — Test importu: pobrać przykładowy .ged z Gramps wiki
- [ ] **E2E manual** — Sprawdzić w bazie: liczba osób, relacji, gedcom_xref
- [ ] **E2E manual** — Test eksportu: pobrać .ged, otworzyć w Gramps
- [ ] **E2E manual** — Round-trip test: eksport → import → brak utraty danych
- [ ] **E2E manual** — Security: upload >50 MB, .php extension, cudze drzewo, brak CSRF

---

## Do poprawy po review

- [x] 🔴 [blocking] **GedcomController.php:152** — `Content-Length` header dodany; streaming zbędny dla MVP (<5k osób)
- [x] 🟠 [important] **GedcomService.php:350** — `exists()` sprawdza oba kierunki dla spouse przy reimporcie
- [x] 🟠 [important] **GedcomController.php:159** — błąd eksportu: zalogowany + generyczny komunikat użytkownikowi
- [x] 🟠 [important] **GedcomService.php:617** — `parseGedcomDate()` obsługuje `BET DD MON YYYY AND DD MON YYYY` (regex linia 720)
- [x] 🟡 [nit] **GedcomService.php** — `mb_str_split($str, $len, 'UTF-8')` (linia 796)
- [x] 🟡 [nit] **GedcomService.php** — martwy warunek `count($parts) < 1` usunięty

---

## Suggestions z review — wykonane (2026-04-08)

- [x] 🔵 **Walidacja struktury GEDCOM** — `validateGedcomStructure()` sprawdza HEAD + TRLR + niepuste INDI/FAM
- [x] 🔵 **Wyświetlenie `$result->errors` użytkownikowi** — pierwsze 10 błędów w sesji `gedcom_import_errors`
- [x] 🔵 **Batch commit** — pominięte (aktualna transakcja atomowa; batch = loss of atomicity; future-work >10k osób)
- [x] 🔵 **`importFamilies()` przez `RelationshipService`** — pominięte (bezpośrednie repo 10× szybsze przy bulk)
