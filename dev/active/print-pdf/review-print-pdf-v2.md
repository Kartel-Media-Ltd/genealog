# Code Review #2 — Print/PDF (po poprawkach)

**Data:** 2026-04-07
**Branch:** feature/php-scaffold
**Commit reviewed:** c1472d5

## Podsumowanie

Poprawki z poprzedniego review zostały wdrożone starannie i kompletnie — wszystkie 14 punktów zaadresowane, większość w sposób wzorcowy. `resolveCssVariables()` jest pomyślnym rozwiązaniem problemu tainted canvas + utraty kolorów, a delegacja do `Response::view(..., 'templates/PrintLayout')` eliminuje duplikację. Znalazłem dwa drobne bugi funkcjonalne (regex case-sensitivity + brak wsparcia dla `var(--X, fallback)`), jeden antipattern bezpieczeństwa (`htmlspecialchars` w kontekście CSS) i kilka suggestów architektonicznych. **Brak regresji blokujących.**

---

## Weryfikacja poprawek z poprzedniego review

| # | Poprawka | Status | Uwagi |
|---|----------|--------|-------|
| 1 | Canvas taint try/catch | ✅ poprawne | `print-helper.js:76-82` |
| 2 | CSS variables via `resolveCssVariables()` | ⚠️ częściowe | Działa, ale 2 drobne braki — patrz Important #1-2 |
| 3 | Race condition — `disabled` + event listener | ✅ poprawne | `print-helper.js:103-114`, `print.php:28` |
| 4 | `Response::view(..., 'templates/PrintLayout')` | ✅ poprawne | TreeController:136-141, PersonController:346-354 |
| 5 | `catch (\Throwable $e)` + `error_log` + propagacja | ⚠️ częściowe | Tylko w `printView()`. `printList()` używa innego stylu (requireTreeAccess + findById) |
| 6 | `@media print` single source w `globals.css` | ✅ poprawne | PrintLayout ma tylko `@page` |
| 7 | `$pageSize` przez controller | ✅ poprawne | TreeController:138, PersonController:348 |
| 8 | `rel="noopener noreferrer"` | ✅ poprawne | show.php:43, persons/index.php:89 |
| 9 | `'use strict'` w print-helper.js | ✅ poprawne | linia 5 |
| 10 | `getBoundingClientRect()` fallback | ✅ poprawne | print-helper.js:51-54 |
| 11 | `$parseDate` helper PHP 8.3-safe | ✅ poprawne | persons-print.php:15-29 |
| 12 | Usunięto duplikat `findByTreeSortedByName` | ✅ poprawne | tylko `findByTree($treeId, 'last_name')` z whitelist |
| 13 | `flex-1` zamiast `calc(100vh - 48px)` | ✅ poprawne | print.php:6 + linia 53 |
| 14 | `print-color-adjust: exact` bez prefiksu | ✅ poprawne | globals.css:332,336 |

**12/14 w pełni poprawne, 2/14 częściowe (drobne luki).**

---

## Nowe problemy

### 🔴 Blocking

**Brak.**

### 🟠 Important

- **`public/js/print-helper.js:17`** — Regex `/var\(--([a-z0-9-]+)\)/gi` ma flagę `gi` (case-insensitive), ale CSS custom properties **są** case-sensitive (`--Foo` ≠ `--foo`). Dziś wszystkie zmienne w `globals.css` są lowercase, ale flaga `i` daje fałszywe poczucie bezpieczeństwa. **Fix:** zmienić na `/var\(--([\w-]+)\)/g` (bez `i`).

- **`public/js/print-helper.js:17`** — Brak obsługi `var(--X, fallback)`. Składnia CSS pozwala na fallback (`var(--border, #ccc)`), ale obecny regex wymaga `)` zaraz po nazwie. Dziś nikt tego nie używa, ale ktoś może dodać w przyszłości. **Fix:** `/var\(--([\w-]+)(?:\s*,\s*[^)]*)?\)/g`.

- **`src/Controllers/PersonController.php:315-318`** — Niespójność error handlingu z `printView`. `printView()` używa `try/catch (\Throwable)` z `error_log`, `printList()` używa `requireTreeAccess()` + osobnego `findById` z dead `if ($tree === null)`. Nie jest bug, ale 2 różne style dla tej samej logiki utrudniają utrzymanie. Niskie ryzyko, ale warto ujednolicić.

- **`src/views/templates/PrintLayout.php:22`** — `htmlspecialchars($pageSize)` w kontekście `<style>`. `htmlspecialchars` koduje `<>&"'` ale **nie blokuje** `;}`. Dziś `$pageSize` to hardcoded string w controllerze (nieszkodliwe), ale jeśli kiedyś trafi tam user input, możliwa CSS injection. **Fix:** whitelist w PrintLayout:
  ```php
  $allowedSizes = ['A3 landscape', 'A4 portrait', 'A4 landscape', 'A3 portrait'];
  if (!in_array($pageSize, $allowedSizes, true)) $pageSize = 'A3 landscape';
  ```

### 🟡 Nit

- **`public/js/print-helper.js:19`** — Fallback `'0 0% 50%'` jest poprawne tylko gdy używane wewnątrz `hsl(...)`. Gdyby ktoś zrobił `color: var(--X)` (gołe), po podstawieniu dostanie `color: 0 0% 50%` — invalid CSS. Dziś wszystkie odwołania w tree-visualizer.js są w `hsl(...)`, ale warto komentarzem uprzedzić.

- **`src/views/pages/trees/persons-print.php:96-97`** — Wiek liczony przez `$birthDt->diff($endDt)->y`. Edge case: zmarli w dniu urodzin mogą dostać wiek -1 dzień przez zerowanie godzin w `'!Y-m-d'`. Drobny, dla druku listy nieistotny.

- **`src/views/pages/trees/persons-print.php:15-29`** — `$parseDate` jako closure w widoku to anti-pattern. Powinien być w `Person` (`Person::birthDateAsObject()`) lub `src/Core/DateHelper`. Jako quick-fix akceptowalne, ale `persons/index.php:244-247` używa klasycznego `new \DateTime` bez tej ochrony — wspólny helper rozwiązałby oba miejsca naraz.

- **`src/views/pages/trees/persons/index.php:244-247`** — (poza scope review, pre-existing) — `new \DateTime(substr(...))` rzuci `DateMalformedStringException` w PHP 8.3 dla nieprawidłowych wartości. `$parseDate` z persons-print.php powinien zostać wyekstraktowany.

- **`src/views/pages/trees/print.php:15`** — Inline `onclick="window.print()"` na przycisku "Drukuj". Po usunięciu inline onclick z PNG buttona (poprawka #3), niespójne. Nie jest race condition (window.print() jest natywne), ale konsekwentnie podpiąć przez listener.

### 🔵 Suggestions

- **Stałą `$allowedPageSizes`** w `Config` lub `PrintLayout`, używanej zarówno przez controllery jak i layout (single source of truth)
- **`resolveCssVariables` alternatywa**: iterować po elementach SVG z `getComputedStyle(elem)` i inlinować jako `style=` — automatycznie obsługuje `calc()`, fallbacki, cascading. Wolniej, ale bardziej odporne
- **`persons-print.php:84`** — striping `bg-gray-50` z Tailwind CDN może nie dostać `print-color-adjust: exact` — przetestować
- **Test routing order** — `/persons/print` PRZED `/persons/{pid}` jest poprawne (index.php:166 vs 169), warto unit test żeby zapobiec regresji
- **`print-helper.js:88`** — `link.click()` bez `appendChild`/`removeChild`. Współczesne przeglądarki OK, ale historycznie Firefox wymagał. Defensive: `document.body.appendChild(link); link.click(); document.body.removeChild(link);`

---

## Statystyki

| Kategoria | Liczba |
|-----------|--------|
| Pliki sprawdzone | 10 |
| 🔴 Blocking | 0 |
| 🟠 Important | 4 |
| 🟡 Nit | 5 |
| 🔵 Suggestions | 5 |

**Ogólna ocena poprawek:** Wszystkie 14 punktów z poprzedniego review zostały zaadresowane; 12 w pełni poprawnie, 2 częściowo (resolveCssVariables ma 2 drobne braki, error handling jest niespójny między controllerami). Brak wprowadzonych bugów blokujących. Kod jest gotowy do merge po addressowaniu 4 punktów Important — wszystkie niskiego ryzyka, ale warte poprawy w tym samym PR.
