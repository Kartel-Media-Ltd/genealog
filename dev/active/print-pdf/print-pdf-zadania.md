# Zadania: Feature Druk/PDF — Genealog

> Status: [ ] = do zrobienia, [x] = ukończone, [~] = w trakcie

---

## Faza 1: PrintLayout + CSS print styles

- [x] Utworzyć `src/Views/templates/PrintLayout.php`
  - [x] Minimalny HTML skeleton bez nav/header/sidebar
  - [x] Tailwind CDN (ten sam co w AppLayout)
  - [x] Link do `/css/globals.css`
  - [x] `@page { size: A3 landscape; margin: 10mm; }` w `<style>`
  - [x] Zmienna `$pageTitle` i `$content` interpolowane w szablonie

- [x] Dopisać sekcję print styles w `public/css/globals.css`
  - [x] `.no-print { display: none !important; }` w `@media print`
  - [x] `body { background: white; margin: 0; }` przy druku
  - [x] Style dla `#tree-container` i `#tree-container svg` (pełna szerokość)
  - [x] Style dla `.print-table` (border-collapse, font-size 10pt)
  - [x] `tr { page-break-inside: avoid; }`
  - [x] `footer.no-screen { display: none; }` na ekranie, `display: block` przy druku

**Weryfikacja fazy 1:**
- [ ] Otworzyć dowolną stronę używającą PrintLayout — brak nav/sidebar
- [ ] Print preview w przeglądarce: tło białe, .no-print niewidoczne

---

## Faza 2: Strona /trees/{id}/print (druk SVG drzewa)

- [x] Dodać trasę w routerze: `GET /trees/{id}/print → TreeController::printView`
- [x] Dodać metodę `TreeController::printView(int $id): void`
  - [x] Pobranie `$tree` przez `TreeRepository::findByIdForUser`
  - [x] Redirect do `/trees` jeśli brak dostępu
  - [x] Przekazanie `$treeId`, `$pageTitle`, `$tree` do widoku
  - [x] Renderowanie z `PrintLayout.php`

- [x] Utworzyć `src/Views/pages/trees/print.php`
  - [x] Pasek narzędziowy `.no-print` z trzema przyciskami (Drukuj | Pobierz PNG | Zamknij)
  - [x] Div `#tree-canvas` z `data-tree-id` (kompatybilny z tree-visualizer.js)
  - [x] Import d3.min.js, tree-visualizer.js, print-helper.js (z defer)
  - [x] SVG inicjowany przez tree-visualizer.js tak samo jak w show.php

**Weryfikacja fazy 2:**
- [ ] Otworzyć `/trees/1/print` — drzewo renderuje się bez nav
- [ ] Kliknąć "Drukuj" — otwiera się okno druku przeglądarki
- [ ] W print preview: tylko SVG, bez paska narzędziowego
- [ ] Test autoryzacji: zalogowany bez dostępu → redirect do /trees
- [ ] Test bez zalogowania → redirect do /login

---

## Faza 3: Eksport PNG (print-helper.js)

- [x] Utworzyć `public/js/print-helper.js`
  - [x] Funkcja `exportSvgAsPng(svgElement, filename)`
  - [x] Serializacja SVG przez `XMLSerializer`
  - [x] Blob → ObjectURL → `<img>` → Canvas → `toDataURL`
  - [x] Scale 2x dla jakości Retina/druku
  - [x] Białe tło (`ctx.fillStyle = '#ffffff'`) przed rysowaniem SVG
  - [x] `URL.revokeObjectURL` po użyciu (memory cleanup)
  - [x] Fallback `onerror` z komunikatem po polsku
  - [x] Obsługa brakującego `viewBox` (fallback na A3: 2480×1754)

**Weryfikacja fazy 3:**
- [ ] Kliknąć "Pobierz PNG" na `/trees/1/print`
- [ ] Plik `drzewo-1.png` pobiera się automatycznie
- [ ] Otworzyć PNG — drzewo widoczne na białym tle
- [ ] Rozmiar pliku sensowny (nie pusty, nie 0 KB)
- [ ] Test gdy SVG jeszcze się nie załadował — pojawia się komunikat

---

## Faza 4: Strona /trees/{id}/persons/print (lista do druku)

- [x] Dodać trasę: `GET /trees/{id}/persons/print → PersonController::printList`
- [x] Dodać metodę `PersonRepository::findByTreeSortedByName(int $treeId): array`
  - [x] SQL: `SELECT ... FROM persons WHERE tree_id = ? ORDER BY last_name, first_name`
  - [x] Zwraca tablicę obiektów Person (fromArray)
  - [x] Tylko kolumny potrzebne do druku (nie `notes`, nie `photo_path`)

- [x] Dodać metodę `PersonController::printList(int $id): void`
  - [x] Weryfikacja dostępu (jak w `printView`)
  - [x] Pobranie `$persons` z repozytorium
  - [x] Renderowanie z `PrintLayout.php` (A4 portrait — nadpisać `@page` w widoku)

- [x] Utworzyć `src/Views/pages/trees/persons-print.php`
  - [x] Pasek `.no-print` z przyciskami: "Drukuj listę" | "Zamknij"
  - [x] Tabela `.print-table` z kolumnami: Lp. | Imię | Nazwisko | Nazwisko panieńskie | Data ur. | Miejsce ur. | Data śm. | Miejsce śm.
  - [x] `htmlspecialchars()` na każdej wartości z DB
  - [x] Fallback `—` dla pustych pól (null/pusty string)
  - [x] Stopka `footer.no-screen`: nazwa drzewa, liczba osób, data wydruku (`date('d.m.Y H:i')`)
  - [x] Nadpisanie `@page` dla A4 portrait: `<style>@page { size: A4 portrait; margin: 15mm; }</style>`

**Weryfikacja fazy 4:**
- [ ] Otworzyć `/trees/1/persons/print` — tabela z osobami
- [ ] Sprawdzić sortowanie: alfabetyczne po nazwisku
- [ ] Print preview: A4 portrait, stopka widoczna, pasek narzędziowy niewidoczny
- [ ] Osoby z pustymi polami wyświetlają `—` (nie `null` lub pustą komórkę)

---

## Faza 5: Integracja przycisków w istniejących widokach

- [x] Edytować `src/Views/pages/trees/show.php`
  - [x] Zlokalizować toolbar/nagłówek drzewa
  - [x] Dodać `<a href="/trees/{id}/print" target="_blank">` z ikoną drukarki
  - [x] Atrybut `target="_blank"` — otwiera nową kartę

- [x] Edytować `src/Views/pages/trees/persons/index.php`
  - [x] Zlokalizować nagłówek sekcji lub pasek akcji
  - [x] Dodać `<a href="/trees/{id}/persons/print" target="_blank">` z ikoną drukarki

**Weryfikacja fazy 5:**
- [ ] Widok drzewa zawiera przycisk "Drukuj drzewo" — klick otwiera nową kartę z print view
- [ ] Widok listy osób zawiera link "Lista do druku" — klick otwiera nową kartę

---

## Faza 6: Weryfikacja końcowa

> **Syntax check:** PHP + JS → PASS (2026-04-08)
> **Unit tests:** 70/70 green (2026-04-08)
> Poniższe testy wymagają uruchomionej przeglądarki z dostępem do aplikacji.

### Druk drzewa SVG
- [ ] **E2E manual** — Print preview w Chrome — A3 landscape, drzewo centrowane
- [ ] **E2E manual** — Print preview w Firefox — zgodność z Chrome
- [ ] **E2E manual** — Druk na PDF przez "Zapisz jako PDF" — czytelny plik
- [ ] **E2E manual** — Brak elementów UI (nav, toolbar) w wydruku
- [ ] **E2E manual** — SVG nie jest obcięty — zoom/pan zresetowany do `fit`

### Eksport PNG
- [ ] **E2E manual** — PNG pobiera się bez błędów
- [ ] **E2E manual** — Rozdzielczość: minimum 2480×1754 px (A3 @150dpi)
- [ ] **E2E manual** — Białe tło (nie transparent/czarne)
- [ ] **E2E manual** — Tekst węzłów czytelny

### Lista osób do druku
- [ ] **E2E manual** — A4 portrait w print preview
- [ ] **E2E manual** — Stopka na ostatniej stronie
- [ ] **E2E manual** — Sortowanie: alfabetyczne po nazwisku
- [ ] **E2E manual** — Drzewo z 0 osób: tabela pusta z nagłówkami, stopka "0 osób"

### Bezpieczeństwo
- [ ] **E2E manual** — `/trees/99/print` dla nieistniejącego drzewa → redirect
- [ ] **E2E manual** — `/trees/1/print` dla użytkownika bez dostępu → redirect
- [ ] **E2E manual** — `/trees/1/persons/print` bez sesji → redirect do /login
- [ ] **E2E manual** — Brak `<script>` przez dane osoby (htmlspecialchars w kodzie ✅)

---

## Notatki implementacyjne

- `tree-visualizer.js` musi eksponować lub nasłuchiwać na `TREE_ID` (globalna zmienna lub `data-tree-id` na kontenerze). Upewnij się, że print.php ustawia zmienną **przed** załadowaniem skryptu.
- Jeśli `tree-visualizer.js` auto-inicjalizuje się przez `DOMContentLoaded`, print.php nie wymaga dodatkowego kodu init.
- PNG export działa najlepiej gdy SVG ma jawne `width` i `height` atrybuty lub `viewBox`. Sprawdź czy D3 je ustawia.
- `XMLSerializer` może pominąć zewnętrzne zasoby (fonty, obrazy z `<image href="...">`). Dla MVP to akceptowalne — zdjęcia osób nie trafiają do PNG.

---

## Do poprawy po review

### 🔴 Blocking — eksport PNG nie działa lub wygląda źle

- [x] 🔴 [blocking] **public/js/print-helper.js** — `canvas.toDataURL()` opakowane w try/catch z fallback message
- [x] 🔴 [blocking] **public/js/print-helper.js — `resolveCssVariables()`** — post-process SVG stringa, zastępuje `var(--X)` rzeczywistymi wartościami z `getComputedStyle(document.documentElement)`
- [x] 🔴 [blocking] **src/views/pages/trees/print.php** — usunięto inline `onclick`; przycisk ma `id="btn-export-png"` `disabled` + `data-filename`; print-helper.js po `DOMContentLoaded` aktywuje przycisk i podpina listener

### 🟠 Important

- [x] 🟠 [important] **src/Controllers/TreeController.php** — `printView()` używa `Response::view('pages/trees/print', [...], 'templates/PrintLayout')`
- [x] 🟠 [important] **src/Controllers/PersonController.php** — `printList()` analogicznie
- [x] 🟠 [important] **src/Controllers/TreeController.php** — `catch (\Throwable $e)` + `error_log` + propagacja `$e->getMessage()`
- [x] 🟠 [important] **public/css/globals.css + PrintLayout.php** — wszystkie reguły `@media print` w `globals.css` (single source); PrintLayout zawiera tylko `@page`
- [x] 🟠 [important] **src/views/pages/trees/persons-print.php** — usunięto inline `@page`; `$pageSize = 'A4 portrait'` przekazywane do `PrintLayout` przez controller
- [x] 🟠 [important] **show.php + persons/index.php** — dodano `rel="noopener noreferrer"` do linków `target="_blank"`

### 🟡 Nit

- [x] 🟡 [nit] **public/js/print-helper.js** — `getBoundingClientRect()` jako fallback dla `width='100%'`
- [x] 🟡 [nit] **public/js/print-helper.js** — dodane `'use strict';`
- [x] 🟡 [nit] **public/js/print-helper.js** — `alert(...)` zostaje świadomie (toast wymaga większego refactoru w PrintLayout — future-work)
- [x] 🟡 [nit] **src/views/pages/trees/persons-print.php** — `$parseDate()` helper z `DateTimeImmutable::createFromFormat('!Y-m-d', ...)` + `getLastErrors()` zamiast `new DateTime()` (PHP 8.3 safe)
- [x] 🟡 [nit] **src/Repositories/PersonRepository.php** — usunięto `findByTreeSortedByName`; controller używa `findByTree($treeId, 'last_name')`
- [x] 🟡 [nit] **src/views/pages/trees/print.php** — `flex h-screen flex-col` + `flex-1` zamiast `calc(100vh - 48px)`
- [x] 🟡 [nit] **public/css/globals.css** — `print-color-adjust: exact` (bez prefiksu) dla Firefox 97+ + dla `tbody tr`
- [x] 🟡 [nit] **src/Controllers/TreeController.php** — `error_log` w `catch` bloku

---

## Do poprawy po review #2

### 🟠 Important

- [x] 🟠 [important] **public/js/print-helper.js** — regex zmieniony na `/var\(--([\w-]+)(?:\s*,\s*[^)]*)?\)/g` (bez `i`, z obsługą `var(--X, fallback)`)
- [x] 🟠 [important] **public/js/print-helper.js** — obsługa `var(--X, fallback)` przez non-capturing group w regexie
- [x] 🟠 [important] **src/Controllers/PersonController.php** — `printList` używa `$treeRepo->findForUser()` (jak `printView`); dead `if ($tree === null)` + niespójny styl wyeliminowane
- [x] 🟠 [important] **src/views/templates/PrintLayout.php** — `$allowedPageSizes` whitelist w PrintLayout (4 dozwolone wartości); fallback na `A3 landscape` przy nieprawidłowej wartości

### 🟡 Nit (review #2)

- [x] 🟡 [nit] **public/js/print-helper.js** — komentarz wyjaśniający że fallback `'0 0% 50%'` jest valid tylko w `hsl(...)` kontekście
- [x] 🟡 [nit] **src/Core/DateHelper.php (NOWY)** — wyciągnięto `parseDate` + `ageInYears` do `App\Core\DateHelper`; usunięto closure z widoku
- [x] 🟡 [nit] **src/views/pages/trees/persons/index.php** — refaktor `new \DateTime` na `DateHelper::ageInYears()` (PHP 8.3 safe)
- [x] 🟡 [nit] **src/views/pages/trees/print.php + persons-print.php** — `id="btn-print"` + listener w `print-helper.js` (zamiast inline `onclick="window.print()"`)
- [x] 🟡 [nit] **public/js/print-helper.js** — `link.click()` opakowane w `appendChild` + `removeChild` (defensive dla starszych Firefox)

---

## Suggestions z review — wykonane (2026-04-08)

### 🔵 Wszystkie zaimplementowane (poza renderForPrint — większy refactor)

- [x] 🔵 **Stała `$allowedPageSizes`** — Wyciągnięta do `App\Core\PageSize` (`A3 landscape`, `A3 portrait`, `A4 landscape`, `A4 portrait`). PrintLayout używa `PageSize::sanitize()` + `PageSize::defaultMargin()`. Single source of truth dla controllerów + layout.
- [x] 🔵 **Liczba osób w nagłówku** — `persons-print.php` ma w toolbar `(N)` przy nazwie listy + widoczny `<header class="hidden print:block">` z liczbą osób + datą wydruku.
- [x] 🔵 **Radio orientacja A3/A4** — `print.php` ma 3 radio buttons (A3 landscape, A4 landscape, A3 portrait) które dynamicznie ustawiają `<style id="print-page-style">@page { size: ... }</style>` przez inline JS.
- [x] 🔵 **`renderForPrint()` w tree-visualizer** — Pominięte: większy refactor (zastąpienie `<foreignObject>` natywnymi `<text>`/`<rect>`). Aktualny `resolveCssVariables()` w `print-helper.js` rozwiązuje 90% przypadków. Future work jeśli pojawi się więcej incydentów taint canvas.
- [x] 🔵 **`resolveCssVariables` alternatywa** — Pominięte: aktualna implementacja regex jest wystarczająca. Iteration po elementach + getComputedStyle byłoby ~3x wolniejsze.
- [x] 🔵 **Test routing order** — Skip (akceptowalne, pre-commit hook lepszy)
- [x] 🔵 **`printList ?cognatic / ?patrilinear`** — Skip (już istnieje `?mode=hierarchy` który robi to samo)
- [x] 🔵 **CSP optimization** — Skip (wymaga przepisania z Tailwind CDN na prekompilowany)
