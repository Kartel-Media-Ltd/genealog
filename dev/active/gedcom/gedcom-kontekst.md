# GEDCOM Import/Eksport — Kontekst i decyzje architektoniczne

## Dlaczego fisharebest/gedcom?

### Alternatywy rozważone

| Biblioteka | Licencja | Stan | Decyzja |
|-----------|----------|------|---------|
| `fisharebest/gedcom` (webtrees parser) | MIT | Aktywna, produkcyjna | WYBRANO |
| Napisanie własnego parsera | — | Ryzykowne (GEDCOM ma wiele edge case) | Odrzucono |
| `mivaecom/gedcom` | MIT | Porzucona (ostatni commit 2016) | Odrzucono |
| `genealogy-tools/gedcom` | ? | Nieznana | Nie sprawdzano |

### Argumenty za fisharebest/gedcom

1. **Dojrzałość**: biblioteka pochodzi z projektu webtrees — najpopularniejszego open-source systemu genealogicznego w PHP. Używana produkcyjnie przez tysiące instancji.
2. **Kompatybilność**: testowana z plikami z Ancestry.com, MyHeritage, FamilySearch, Gramps, MacFamilyTree — pokrywa realne pliki użytkowników.
3. **Obsługa kodowania**: automatyczny detect i konwersja ANSEL → UTF-8. Stare pliki z Ancestry często są ANSEL.
4. **GEDCOM 5.5 i 5.5.1**: obsługuje obie wersje, które są w użyciu.
5. **Licencja MIT**: brak ograniczeń komercyjnych, można zmodyfikować.
6. **Aktywna**: webtrees jest rozwijany aktywnie — biblioteka dostaje poprawki przy okazji.

### Jak zweryfikować API po instalacji

```bash
# Po composer require:
ls vendor/fisharebest/gedcom/src/

# Sprawdź główny parser:
cat vendor/fisharebest/gedcom/src/GedcomParser.php

# Sprawdź typy rekordów:
ls vendor/fisharebest/gedcom/src/Records/
```

Oczekiwane klasy: `IndividualRecord`, `FamilyRecord`, `NoteRecord`, `SourceRecord`.
Oczekiwany entry point: `new GedcomParser()->parse(file_get_contents($filePath))` lub podobny.

---

## Jak mapować GEDCOM FAM na relationships

### Problem: FAM to rodzina, relationships to graf

GEDCOM przechowuje relacje przez encję `FAM` (rodzina):
```
0 @F1@ FAM
1 HUSB @I1@
1 WIFE @I2@
1 CHIL @I3@
1 CHIL @I4@
```

Nasza tabela `relationships` przechowuje dwustronne relacje jako krawędzie grafu:
```
(person_a_id, person_b_id, type)
```

### Mapowanie FAM → relationships

Dla każdej FAM:

**Para małżeńska (HUSB + WIFE):**
```
INSERT relationships (person_a_id=HUSB_id, person_b_id=WIFE_id, type='spouse', tree_id)
```
Relacja `spouse` jest symetryczna — wystarczy jeden rekord w obu kierunkach lub jeden rekord i obsługa w kodzie.
Decyzja projektu: **jeden rekord**, gdzie `person_a_id` < `person_b_id` (mniejszy ID jako A).

**Rodzic-dziecko (HUSB/WIFE + CHIL):**
```
INSERT relationships (person_a_id=HUSB_id, person_b_id=CHIL_id, type='parent', tree_id)
INSERT relationships (person_a_id=CHIL_id, person_b_id=HUSB_id, type='child', tree_id)
INSERT relationships (person_a_id=WIFE_id, person_b_id=CHIL_id, type='parent', tree_id)
INSERT relationships (person_a_id=CHIL_id, person_b_id=WIFE_id, type='child', tree_id)
```

Relacja parent/child jest przechowywana w **obu kierunkach** — ułatwia zapytania (nie trzeba UNION).

**Samotny rodzic (tylko HUSB lub tylko WIFE):**
Traktuj tak samo — jeśli jest CHIL, dodaj relacje parent/child dla obecnego rodzica.

**Rodzeństwo:**
GEDCOM nie ma tagu dla rodzeństwa. Dzieci tej samej FAM są rodzeństwem, ale dla MVP nie dodajemy automatycznie relacji `sibling`. Użytkownik może dodać ręcznie. Powód: N dzieci generuje N*(N-1) relacji — dla dużych rodzin nadmiarowe.

### Pseudokod importu FAM

```php
foreach ($families as $family) {
    $husbId = $xrefMap[$family->getHusband()?->getXref()] ?? null;
    $wifeId = $xrefMap[$family->getWife()?->getXref()] ?? null;

    // Relacja małżeńska
    if ($husbId && $wifeId) {
        $aId = min($husbId, $wifeId);
        $bId = max($husbId, $wifeId);
        $this->relationshipRepo->createIfNotExists($aId, $bId, 'spouse', $treeId);
    }

    // Relacje rodzic-dziecko
    foreach ($family->getChildren() as $childRef) {
        $childId = $xrefMap[$childRef->getXref()] ?? null;
        if (!$childId) continue; // osoba spoza importu — skip z logiem

        if ($husbId) {
            $this->relationshipRepo->createIfNotExists($husbId, $childId, 'parent', $treeId);
            $this->relationshipRepo->createIfNotExists($childId, $husbId, 'child', $treeId);
        }
        if ($wifeId) {
            $this->relationshipRepo->createIfNotExists($wifeId, $childId, 'parent', $treeId);
            $this->relationshipRepo->createIfNotExists($childId, $wifeId, 'child', $treeId);
        }
    }
}
```

---

## Kodowanie znaków: ANSEL vs UTF-8

### Problem

GEDCOM 5.5 domyślnie używał ANSEL (American National Standard for Extended Latin). Pliki z lat 90. i 2000. z programów takich jak PAF, Legacy, Family Tree Maker często są ANSEL. Pliki z Ancestry/MyHeritage/FamilySearch są UTF-8.

Nagłówek GEDCOM deklaruje kodowanie:
```
1 CHAR ANSEL    ← stare pliki
1 CHAR UTF-8    ← nowoczesne
1 CHAR ASCII    ← bardzo stare, bez polskich znaków
```

### Rozwiązanie

`fisharebest/gedcom` obsługuje auto-detect i konwersję ANSEL → UTF-8 wewnętrznie.

Jeśli biblioteka tego nie robi, przed parsowaniem:
```php
$content = file_get_contents($filePath);
if (str_contains($content, '1 CHAR ANSEL')) {
    // Konwersja przez iconv lub mb_convert_encoding
    // ANSEL nie jest standardowym kodowaniem PHP — potrzebna tablica konwersji
    // Rozważ użycie fisharebest/webtrees AnselString::anselToUtf8()
}
```

**Dla MVP**: zakładaj UTF-8 i wyświetl ostrzeżenie dla ANSEL. Pełna obsługa w fazie 2.

---

## gedcom_xref — dlaczego przechowywać i jak

### Po co przechowywać xref?

1. **Eksport round-trip**: gdy eksportujesz drzewo, xref z oryginalnego importu jest zachowany (`@I1@`, `@I5@`). Programy takie jak Gramps mogą zdeduplikować import/eksport po xref.
2. **Detekcja duplikatów**: przy ponownym imporcie tego samego pliku — sprawdź `WHERE gedcom_xref = ? AND tree_id = ?` zamiast porównywania danych.
3. **Referencje FAM**: podczas parsowania FAM, musisz przetłumaczyć `@I1@` → `person_id` w bazie. Mapa `xref → person_id` budowana jest na podstawie właśnie wstawionych rekordów.

### Format przechowywania

Przechowuj z symbolami `@` — np. `@I1@`, `@I15@`.

Powód: spójność z GEDCOM standard. Eksport może bezpośrednio użyć wartości z kolumny bez transformacji.

```php
// Przy imporcie:
$gedcomXref = $individual->getXref(); // zwraca np. "@I1@" lub "I1" — sprawdź API
// Jeśli bez @, dodaj: '@' . trim($xref, '@') . '@'

// Przy eksporcie:
$xref = $person->gedcom_xref ?? '@I' . $person->id . '@'; // fallback dla osób bez xref
```

### Fallback dla osób bez xref (dodanych ręcznie)

Osoby dodane przez formularz (nie przez GEDCOM) nie mają `gedcom_xref`. Przy eksporcie generuj xref z ID bazy:

```php
$xref = $person->gedcom_xref ?? '@I' . $person->id . '@';
```

To gwarantuje unikalność w obrębie eksportowanego pliku.

---

## Parsowanie dat GEDCOM

GEDCOM ma własny format daty — nie jest to ISO 8601:

| Format GEDCOM | Znaczenie | Konwersja do DB |
|---------------|-----------|-----------------|
| `15 MAR 1850` | 15 marca 1850 | `1850-03-15` |
| `MAR 1850` | marzec 1850 | `1850-03-01` (przybliżenie) |
| `1850` | rok 1850 | `1850-01-01` (przybliżenie) |
| `ABT 1850` | około 1850 | `1850-01-01` + nota w notes |
| `BEF 1850` | przed 1850 | `1849-12-31` lub null |
| `AFT 1850` | po 1850 | `1851-01-01` lub null |
| `BET 1840 AND 1860` | między | `1850-01-01` (środek) |
| `FROM 1840 TO 1860` | zakres | `1840-01-01` jako start |

Implementacja `formatGedcomDate()`:

```php
private function parseGedcomDate(string $gedcomDate): ?string
{
    $months = [
        'JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04',
        'MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08',
        'SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12',
        'POL'=>'??' // GEDCOM może mieć lokalne nazwy
    ];

    $date = strtoupper(trim($gedcomDate));
    // Usuń prefiksy przybliżeń
    $date = preg_replace('/^(ABT|CAL|EST|BEF|AFT|FROM|TO)\s+/', '', $date);
    // Format: DD MON YYYY
    if (preg_match('/^(\d{1,2})\s+([A-Z]{3})\s+(\d{4})$/', $date, $m)) {
        return $m[3] . '-' . ($months[$m[2]] ?? '01') . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }
    // Format: MON YYYY
    if (preg_match('/^([A-Z]{3})\s+(\d{4})$/', $date, $m)) {
        return $m[2] . '-' . ($months[$m[1]] ?? '01') . '-01';
    }
    // Format: YYYY
    if (preg_match('/^(\d{4})$/', $date, $m)) {
        return $m[1] . '-01-01';
    }
    return null; // nierozpoznany format
}
```

---

## Obsługa wielokrotnych małżeństw i adopcji

### Wielokrotne małżeństwa

GEDCOM obsługuje to przez wiele rekordów FAM. Jedna osoba może być HUSB lub WIFE w wielu FAM.

```
0 @I1@ INDI  ← Jan Kowalski
1 FAMS @F1@  ← małżeństwo 1
1 FAMS @F2@  ← małżeństwo 2
```

Nasza tabela `relationships` z typem `spouse` obsługuje to naturalnie — relacja dotyczy dwóch osób, a jedna osoba może mieć wiele relacji spouse.

### Adopcje

GEDCOM 5.5.1 tag: `2 ADOP HUSB` lub `2 ADOP WIFE` lub `2 ADOP BOTH` pod `1 FAMC`.

```
1 FAMC @F1@
2 ADOP HUSB   ← adoptowany przez ojca
```

Dla MVP: traktuj adopcję tak samo jak relację biologiczną — wstaw relację parent/child bez rozróżnienia. Kolumna `notes` może zawierać informację "adopcja" jeśli chcemy to zachować.

Przyszłość: dodaj kolumnę `relationship_type ENUM('biological','adoptive','step','foster')` do tabeli `relationships`.

---

## Decyzje projektowe — podsumowanie

| Kwestia | Decyzja | Uzasadnienie |
|---------|---------|--------------|
| Biblioteka parsera | `fisharebest/gedcom` | MIT, produkcyjna, kompatybilna |
| Strategia duplikatów | `skip` domyślnie | Bezpieczniejsze — nie nadpisuje danych |
| Relacja spouse | 1 rekord (a_id < b_id) | Brak duplikatów, prosta logika |
| Relacja parent/child | 2 rekordy (obu kierunki) | Łatwiejsze zapytania SELECT |
| Rodzeństwo | Nie generowane automatycznie | Eksplozja kombinatoryczna dla dużych rodzin |
| xref format | Z `@` — np. `@I1@` | Spójność z GEDCOM, łatwy eksport round-trip |
| Fallback xref | `@I{id}@` dla ręcznych | Unikalność bez zmiany schematu |
| Daty przybliżone | Ekstrakcja roku + nota | Zachowuje informację bez straty |
| Kodowanie ANSEL | fisharebest auto-detect | Minimalna praca, produkcyjna obsługa |
| Transakcja | Cały import w jednej | Atomowość — brak częściowych importów |
| Plik tymczasowy | `sys_get_temp_dir()` | Poza public/, usuwany w finally |
| Eksport rodzeństwa | Nie eksportujemy FAM dla rodzeństwa | FAM = para małżeńska, nie dowolna rodzina |
