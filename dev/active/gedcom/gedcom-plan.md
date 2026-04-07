# GEDCOM Import/Eksport — Architektura

## Przegląd

Feature umożliwia użytkownikom import drzewa genealogicznego z pliku `.ged` (standard GEDCOM 5.5.1) oraz eksport własnego drzewa do tego formatu. Import korzysta z biblioteki `fisharebest/gedcom` (MIT). Eksport generowany jest ręcznie jako string zgodny z GEDCOM 5.5.1.

---

## Flow importu

```
POST /trees/{id}/gedcom/import  (multipart/form-data, max 50MB)
│
└─► GedcomController::import(Request $request, int $treeId)
      │  • weryfikacja własności drzewa (TreeRepository::findByIdAndOwner)
      │  • walidacja pliku: rozszerzenie .ged, max 50MB, MIME text/*
      │  • move_uploaded_file() → sys_get_temp_dir()/{uuid}.ged
      │
      └─► GedcomService::import(int $treeId, string $filePath, int $userId): ImportResult
            │
            ├─► GedcomParser::parse($filePath)  — fisharebest/gedcom
            │     • zwraca kolekcję rekordów (individuals, families, ...)
            │
            ├─► beginTransaction()
            │
            ├─► Iteruj po INDI (individuals):
            │     • mapINDIToPerson(IndividualRecord $indi): array
            │         – GIVN → first_name
            │         – SURN → last_name
            │         – _MARN / NICK → maiden_name (jeśli dostępne)
            │         – SEX M/F/U → gender male/female/unknown
            │         – BIRT DATE → birth_date (parsowanie Y-m-d)
            │         – BIRT PLAC → birth_place
            │         – DEAT DATE → death_date
            │         – DEAT PLAC → death_place
            │         – NOTE → notes (concat jeśli wiele)
            │         – xref @I1@ → gedcom_xref
            │         – DEAT obecny → is_living = 0
            │     • PersonRepository::create($personData)
            │     • Buduj mapę: xref → person_id (dla relacji FAM)
            │
            ├─► Iteruj po FAM (families):
            │     • HUSB xref → husband_id (z mapy)
            │     • WIFE xref → wife_id (z mapy)
            │     • CHIL xref → child_ids[] (z mapy)
            │     • Jeśli HUSB + WIFE: RelationshipRepository::create(spouse)
            │     • Jeśli HUSB + CHIL: RelationshipRepository::create(parent/child) × n
            │     • Jeśli WIFE + CHIL: RelationshipRepository::create(parent/child) × n
            │
            ├─► commitTransaction()
            │
            └─► return ImportResult(personsCount, relationshipsCount)

      └─► Flash::success("Zaimportowano {X} osób i {Y} relacji.")
          Redirect → GET /trees/{id}

Błędy:
  • Plik za duży          → Flash::error("Plik przekracza 50 MB.") → redirect back
  • Zły format / parse    → rollback + Flash::error("Nieprawidłowy format GEDCOM (linia {N}).") → redirect back
  • IDOR / brak dostępu   → 403
  • Duplikat gedcom_xref  → skip (INSERT IGNORE) lub update — konfigurowalne w GedcomService::$conflictStrategy
```

---

## Flow eksportu

```
GET /trees/{id}/gedcom/export
│
└─► GedcomController::export(Request $request, int $treeId)
      │  • weryfikacja własności/dostępu do drzewa
      │
      └─► GedcomService::export(int $treeId): string
            │
            ├─► PersonRepository::findByTree($treeId)       — wszystkie osoby
            ├─► RelationshipRepository::findByTree($treeId) — wszystkie relacje
            │
            ├─► buildHeader()       — 0 HEAD, SOUR, GEDC, CHAR UTF-8, DATE
            │
            ├─► Iteruj po osobach → buildINDI(Person $person): string
            │     0 @I{id}@ INDI
            │     1 NAME {first_name} /{last_name}/
            │     2 GIVN {first_name}
            │     2 SURN {last_name}
            │     [2 _MARN {maiden_name}]  — niestandard, akceptowany przez Gramps/Ancestry
            │     1 SEX {M|F|U}
            │     [1 BIRT]
            │     [2 DATE {birth_date sformatowany DD MMM YYYY}]
            │     [2 PLAC {birth_place}]
            │     [1 DEAT {Y jeśli is_living=0, else pomiń}]
            │     [2 DATE {death_date}]
            │     [2 PLAC {death_place}]
            │     [1 NOTE {notes}]
            │
            ├─► Grupuj relacje spouse → pary FAM
            │     Każda para (person_a + person_b, type=spouse) = jeden rekord FAM
            │     Dzieci: type=parent/child → znajdź dzieci każdej pary
            │
            ├─► Iteruj po parach FAM → buildFAM(array $family): string
            │     0 @F{famId}@ FAM
            │     [1 HUSB @I{husb_id}@]
            │     [1 WIFE @I{wife_id}@]
            │     [1 CHIL @I{child_id}@]  — powtórzone dla każdego dziecka
            │     [1 MARR]
            │     [2 DATE {start_date}]
            │
            └─► buildFooter()  — 0 TRLR

      └─► Response headers:
            Content-Type: text/plain; charset=UTF-8
            Content-Disposition: attachment; filename="drzewo-{treeId}.ged"
            Cache-Control: no-store
          echo $gedcomString; exit;
```

---

## Nowe pliki

| Plik | Opis |
|------|------|
| `src/Services/GedcomService.php` | Logika importu i eksportu, mapowanie danych |
| `src/Controllers/GedcomController.php` | HTTP handler: upload, walidacja, odpowiedź |
| `src/Views/pages/trees/gedcom.php` | Strona z formularzem upload + przycisk eksportu |

### Pliki bez zmian (rozszerzone)

| Plik | Co się zmienia |
|------|----------------|
| `src/Core/Router.php` | Nowe trasy w grupie `/trees` |
| `src/Repositories/PersonRepository.php` | Metoda `findByXref(treeId, xref)` do sprawdzania duplikatów |
| `src/Repositories/RelationshipRepository.php` | Metoda `findByTree(treeId)` dla eksportu |

---

## Nowe trasy

```
GET  /trees/{id}/gedcom          → GedcomController::page()    — strona import/eksport
POST /trees/{id}/gedcom/import   → GedcomController::import()  — upload + przetwarzanie
GET  /trees/{id}/gedcom/export   → GedcomController::export()  — download .ged
```

Trasy rejestrowane w grupie chronionej `AuthMiddleware` i `TreeAccessMiddleware`.

---

## Wymagania Composer

```bash
composer require fisharebest/gedcom
```

Biblioteka: https://github.com/fisharebest/webtrees — pakiet `fisharebest/gedcom` (MIT).
Alternatywnie `fisharebest/webtrees-gedcom-record-parser` — sprawdź dostępną wersję w Packagist.

---

## Schemat GedcomService

```php
<?php
declare(strict_types=1);

namespace App\Services;

class GedcomService
{
    private string $conflictStrategy = 'skip'; // 'skip' | 'update'

    public function __construct(
        private PersonRepository       $personRepo,
        private RelationshipRepository $relationshipRepo,
        private \PDO                   $pdo,
    ) {}

    public function import(int $treeId, string $filePath, int $userId): ImportResult
    {
        // 1. Parse via fisharebest/gedcom
        // 2. beginTransaction
        // 3. Import INDI → persons
        // 4. Import FAM → relationships
        // 5. commit / rollback
        // 6. unlink($filePath)
        // return ImportResult
    }

    public function export(int $treeId): string
    {
        // 1. Fetch persons + relationships
        // 2. Build GEDCOM string
        // return $gedcom
    }

    private function mapINDIToPerson(mixed $indi, int $treeId, int $userId): array { /* ... */ }
    private function buildINDI(Person $person): string { /* ... */ }
    private function buildFAM(array $family, int $famIndex): string { /* ... */ }
    private function buildHeader(): string { /* ... */ }
    private function buildFooter(): string { return "0 TRLR\n"; }
    private function formatGedcomDate(?string $date): string { /* DD MMM YYYY */ }
}
```

---

## Mapowanie GEDCOM → baza danych

### INDI → persons

| Tag GEDCOM | Kolumna DB | Uwagi |
|------------|------------|-------|
| `@I1@` (xref) | `gedcom_xref` | Przechowywany z @ lub bez — konsekwentnie z @ |
| `1 NAME Imię /Nazwisko/` | `first_name`, `last_name` | Parsowanie slashy |
| `2 GIVN` | `first_name` | Nadrzędny wobec NAME jeśli istnieje |
| `2 SURN` | `last_name` | Nadrzędny wobec NAME jeśli istnieje |
| `1 SEX M` | `gender = 'male'` | |
| `1 SEX F` | `gender = 'female'` | |
| `1 SEX U` / brak | `gender = 'unknown'` | |
| `2 DATE` pod `1 BIRT` | `birth_date` | Parsowanie GEDCOM date → Y-m-d |
| `2 PLAC` pod `1 BIRT` | `birth_place` | |
| `2 DATE` pod `1 DEAT` | `death_date` | Obecność DEAT → `is_living = 0` |
| `2 PLAC` pod `1 DEAT` | `death_place` | |
| `1 NOTE` | `notes` | Concat wielu NOTE newline-separated |
| — | `visibility` | Domyślnie `'private'` |
| — | `tree_id` | Z parametru importu |
| — | `created_by` | `userId` z sesji |

### FAM → relationships

| Tag GEDCOM | Relacja DB | type |
|------------|------------|------|
| FAM z HUSB + WIFE | relationships (a=HUSB, b=WIFE) | `spouse` |
| FAM z HUSB + CHIL | relationships (a=HUSB, b=CHIL) | `parent` |
| FAM z HUSB + CHIL | relationships (a=CHIL, b=HUSB) | `child` |
| FAM z WIFE + CHIL | relationships (a=WIFE, b=CHIL) | `parent` |
| FAM z WIFE + CHIL | relationships (a=CHIL, b=WIFE) | `child` |

Relacja `sibling` — nie wynika wprost z FAM, może być wywnioskowana (dzieci tej samej FAM), ale dla MVP pominąć.

---

## Obsługa błędów i edge cases

| Scenariusz | Zachowanie |
|-----------|-----------|
| Plik > 50MB | Flash error, redirect back, brak przetwarzania |
| Plik nie jest .ged / zły MIME | Flash error, brak przetwarzania |
| Parse error w linii N | rollback, Flash::error z numerem linii |
| `gedcom_xref` już istnieje dla drzewa | `skip` (domyślnie) lub `update` wg `conflictStrategy` |
| Osoba z FAM nie znaleziona w mapie INDI | log warning, skip relacji (nie przerywaj importu) |
| Puste imię i nazwisko | Wstaw `first_name = 'Nieznane'`, kontynuuj |
| Data w formacie `ABT 1850`, `BEF 1900` | Wyciągnij rok, zapisz jako `1850-01-01` + dodaj notatkę |
| GEDCOM ANSEL encoding | fisharebest/gedcom obsługuje auto-detect |
