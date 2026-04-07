# Code Review — GEDCOM Import/Eksport

**Data:** 2026-04-07  
**Branch:** feature/php-scaffold  
**Commity:** d29fc18, 583d8e6

## Podsumowanie

Implementacja jest solidna architektonicznie: transakcja atomowa, CSRF i IDOR weryfikowane, upload obsługiwany poprawnie (`is_uploaded_file`, losowa nazwa), UUID przez `random_bytes()`. Parser GEDCOM 5.5.1 obsługuje CONT/CONC, daty przybliżone, GIVN/SURN — dobra jakość jak na system bez zewnętrznych bibliotek. Znaleziono 1 problem blocking (memory + Content-Length przy eksporcie), 3 important i 5 nitów.

---

## Problemy

### 🔴 Blocking

- **GedcomController.php:152-157** — Eksport buforuje cały plik GEDCOM jako string w pamięci PHP (`$output = $gedcom->export(...)`). Dla drzewa z 10 000+ osób string może przekroczyć `memory_limit` (domyślnie 128 MB). Brak nagłówka `Content-Length` utrudnia pobieranie dużych plików. **Fix:** dodać `header('Content-Length: ' . strlen($output))` lub wygenerować output przez `echo` line-by-line (streaming).

### 🟠 Important

- **GedcomService.php:350-351 (importFamilies)** — Canonicalizacja pary spouse: `$aId = ($husbId < $wifeId) ? $husbId : $wifeId` opiera się na porównaniu UUID stringów. Przy ponownym imporcie tego samego pliku (update strategy) nowe UUID powodują odwrócenie kolejności → duplikat relacji `spouse` nie jest wykrywany przez `exists()`. **Fix:** sprawdzać oba kierunki w `exists()` dla relacji `spouse`, lub użyć `min()/max()` na deterministycznym kluczu (np. `gedcom_xref`).

- **GedcomController.php:159** — Błąd eksportu: `'Błąd eksportu: ' . $e->getMessage()` może ujawnić fragmenty SQL (wyjątki PDO). **Fix:** `error_log($e)` + generyczny komunikat dla użytkownika (jak już poprawiono w imporcie).

- **GedcomService.php:617-625** — `parseGedcomDate()` pattern `BET YYYY AND YYYY` nie obsługuje pełnych dat: `BET 15 MAR 1850 AND 20 APR 1852` → zwraca `null` → utrata danych. **Fix:** rozszerzyć regex lub stripować ` AND ...` i parsować pierwszą datę.

### 🟡 Nit

- **GedcomService.php:696-701** — `chunkString()` używa `str_split()` (bajty), nie `mb_str_split()` (znaki UTF-8). Polskie znaki mogą być ucięte w połowie sekwencji wielobajtowej.

- **GedcomService.php:655** — `if (count($parts) < 1)` zawsze fałszywe (`explode` zwraca min. 1 element). Można usunąć.

- **GedcomService.php:550-565** — Logika eksportu notatek z flagą `$first` jest trudna do czytania. Kandydat do ekstrakcji metody `buildNoteLines()`.

- **GedcomService.php:508** — UUID jako GEDCOM xref ma 36 znaków; GEDCOM 5.5.1 preferuje max 22. Większość programów obsługuje dłuższe xrefy, ale formalnie wykracza poza standard.

- **GedcomService.php:244** — Regex NAME nie obsługuje `//` (puste nazwisko) — wynik jest pusty string, co jest poprawne, ale warto dodać komentarz.

### 🔵 Suggestions

- Zwalidować strukturę pliku GEDCOM (`0 HEAD` na początku, `0 TRLR` na końcu) przed importem.
- Wyświetlić `$result->errors` (błędy per-rekord) użytkownikowi — aktualnie są ignorowane.
- Dla dużych importów (>500 osób) rozważyć batch commit lub dokumentować limit memory/timeout.
- `ALLOWED_MIME` zawiera `application/octet-stream` — pragmatyczne, ale warto skomentować dlaczego.

---

## Co działa dobrze

- Atomowość transakcji z cleanup w `finally` — wzorcowe
- Upload security: `is_uploaded_file`, `move_uploaded_file`, finfo MIME, randomowa nazwa temp
- IDOR: `requireTreeAccess()` + `findByXref()` filtruje przez `tree_id`
- CSRF: token w widoku + `verifyCsrf()` w kontrolerze
- UUID v4 przez `random_bytes()` z poprawnymi bitami wersji/wariantu
- Parser GEDCOM: CONT/CONC, daty przybliżone, GIVN/SURN override, multi-NOTE
- HUSB/WIFE przypisanie według płci osoby (nie UUID)
- Session::flash bugfix spójny z `render_flash_messages()`
- XSS: wszystkie zmienne w widoku przez `htmlspecialchars()`
- PSR-12 i `declare(strict_types=1)` w każdym pliku

---

## Statystyki

| Kategoria | Liczba |
|-----------|--------|
| Pliki przejrzane | 8 |
| 🔴 Blocking | 1 |
| 🟠 Important | 3 |
| 🟡 Nit | 5 |
| 🔵 Suggestions | 4 |
