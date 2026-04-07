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

### Druk drzewa SVG
- [ ] Print preview w Chrome — A3 landscape, drzewo centrowane
- [ ] Print preview w Firefox — zgodność z Chrome
- [ ] Druk na PDF przez "Zapisz jako PDF" w systemie — czytelny plik
- [ ] Brak elementów UI (nav, toolbar) w wydruku
- [ ] SVG nie jest obcięty — zoom/pan zresetowany do `fit`

### Eksport PNG
- [ ] PNG pobiera się bez błędów
- [ ] Rozdzielczość: minimum 2480×1754 px (A3 @150dpi)
- [ ] Białe tło (nie transparent/czarne)
- [ ] Tekst węzłów czytelny

### Lista osób do druku
- [ ] A4 portrait w print preview
- [ ] Stopka na ostatniej stronie
- [ ] Sortowanie: Jan Kowalski przed Adam Nowak → NIE (Kowalski przed Nowak — sortowanie po nazwisku)
- [ ] Drzewo z 0 osób: tabela pusta z nagłówkami, stopka "0 osób"

### Bezpieczeństwo
- [ ] `/trees/99/print` dla nieistniejącego drzewa → redirect
- [ ] `/trees/1/print` dla użytkownika bez dostępu → redirect
- [ ] `/trees/1/persons/print` bez sesji → redirect do /login
- [ ] Brak `<script>` wstrzykniętego przez dane osoby (htmlspecialchars)

---

## Notatki implementacyjne

- `tree-visualizer.js` musi eksponować lub nasłuchiwać na `TREE_ID` (globalna zmienna lub `data-tree-id` na kontenerze). Upewnij się, że print.php ustawia zmienną **przed** załadowaniem skryptu.
- Jeśli `tree-visualizer.js` auto-inicjalizuje się przez `DOMContentLoaded`, print.php nie wymaga dodatkowego kodu init.
- PNG export działa najlepiej gdy SVG ma jawne `width` i `height` atrybuty lub `viewBox`. Sprawdź czy D3 je ustawia.
- `XMLSerializer` może pominąć zewnętrzne zasoby (fonty, obrazy z `<image href="...">`). Dla MVP to akceptowalne — zdjęcia osób nie trafiają do PNG.
