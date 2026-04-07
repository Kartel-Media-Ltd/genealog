# Code Review — Print/PDF

**Data:** 2026-04-07
**Branch:** feature/php-scaffold

## Podsumowanie

Feature jest generalnie dobrze zaprojektowany: kontrola dostępu działa przez `TreeService::getForUser` + `requireTreeAccess`, `htmlspecialchars` jest na wszystkich polach z DB, route'y `print` poprawnie wyprzedzają `{pid}`. Najważniejsze problemy to race condition w `print-helper.js` (inline `onclick` przed załadowaniem `defer` skryptu), nieuchronny **canvas taint / utrata kolorów** przy rasterze SVG z `<foreignObject>` i `hsl(var(--border))`, oraz duplikacja reguł print w dwóch miejscach.

---

## Problemy

### 🔴 Blocking

- **public/js/print-helper.js + tree-visualizer.js** — `canvas.toDataURL('image/png')` zadziała TYLKO jeśli wszystkie obrazki są same-origin ORAZ przeglądarka rasteryzuje `<foreignObject>` z HTML. Tree visualizer używa `<foreignObject>` z `<img src="/media.php?...">`. W Chrome/Safari taint canvas przez obrazki w `foreignObject` to znany problem (SecurityError przy `toDataURL`). Nawet bez taintu, Chrome często renderuje pustą zawartość ([crbug.com/294129](https://crbug.com/294129)). **Fix:** opakować `canvas.toDataURL` w try/catch z fallback message; rozważyć tryb "PNG bez zdjęć".

- **public/js/print-helper.js + tree-visualizer.js (style)** — SVG używa `hsl(var(--border))`, `hsl(var(--primary))` itd. Custom properties są w `globals.css :root`. Po `XMLSerializer` i renderowaniu z odłączonego `blob:` URL **zmienne CSS przestają istnieć** → linie/ramki będą czarne lub niewidoczne w PNG. **Fix:** post-process SVG stringa zastępując `var(--X)` rzeczywistymi wartościami z `getComputedStyle(document.documentElement)`, albo inline wartości w tree-visualizer.

- **src/views/pages/trees/print.php:24** — Przycisk „Pobierz PNG" ma inline `onclick="exportSvgAsPng(...)"`, a `print-helper.js` ładowane z `defer`. Race condition: użytkownik kliknie zanim skrypt się załaduje → `ReferenceError`. **Fix:** event listener po `DOMContentLoaded` + `disabled` na przycisku do czasu załadowania.

### 🟠 Important

- **src/Controllers/TreeController.php:129-141** — `printView()` używa ręcznego `include VIEWS_PATH . '/...'` zamiast `$this->response->view(...)` co jest niespójne z resztą kontrolera i pomija walidację `realpath()` w `Response::view()`. **Fix:** użyć `$this->response->view('pages/trees/print', [...], 'templates/PrintLayout')`.

- **src/Controllers/PersonController.php:347-351** — Ten sam wzorzec w `printList()`. Refaktor jak wyżej.

- **src/Controllers/TreeController.php:131** — `catch (\Exception)` traci rozróżnienie błędów (zawsze ten sam komunikat); analizator może oznaczyć `$tree` jako possibly undefined po `redirect()`. **Fix:** propaguj `$e->getMessage()` jak w `show()`, dodaj `return;` po redirect lub wczesny return w try.

- **src/views/pages/trees/print.php + globals.css** — Reguły `@media print` są zduplikowane między `PrintLayout.php` (inline `<style>`) i `globals.css`. **Fix:** zostaw tylko w jednym miejscu (preferowane `globals.css`); w PrintLayout zostaw tylko `@page`.

- **src/views/pages/trees/persons-print.php** — `@page { size: A4 portrait }` jest w `<style>` w `<body>`, podczas gdy PrintLayout ustawia A3 landscape w `<head>`. CSS cascade preferuje drugą regułę, ale `@page` w body jest słabo specyfikowane. **Fix:** przekaż `$pageSize` do PrintLayout i tam warunkowo wyrenderuj `@page`.

- **src/views/pages/trees/show.php + persons/index.php** — Linki `target="_blank"` bez `rel="noopener noreferrer"`. Nowoczesne przeglądarki dodają `noopener` automatycznie, ale konwencja warta utrzymania.

### 🟡 Nit

- **public/js/print-helper.js:32-33** — `svgElement.width.baseVal.value` zwraca 0 dla `width='100%'`. Fallbacki działają tylko przez przypadek (zero jest falsy). Lepiej: `vb?.width ?? svgElement.getBoundingClientRect().width ?? 2480`.

- **public/js/print-helper.js** — Brak `'use strict';`.

- **public/js/print-helper.js:13,59** — `alert(...)` jako UX feedback — prymitywne. Rozważ toast.

- **src/views/pages/trees/persons-print.php** — `new \DateTime(substr(...))` może rzucić `\DateMalformedStringException` (PHP 8.3+) dla nieprawidłowych dat. Opakować w try/catch lub `DateTime::createFromFormat`.

- **src/Repositories/PersonRepository.php** — `findByTreeSortedByName` jest duplikatem `findByTree($treeId, 'last_name')`. Można usunąć i użyć istniejącej.

- **src/views/pages/trees/print.php:48** — Inline `style="height:calc(100vh - 48px);"` — magic number. Lepiej flexbox.

- **src/views/templates/PrintLayout.php** — Brak `print-color-adjust: exact` (bez prefiksu) dla Firefox 97+.

- **src/Controllers/TreeController.php:131** — `catch (\Exception)` bez logowania; rozważ `error_log($e->getMessage())`.

### 🔵 Suggestions

- **tree-visualizer + print-helper** — Tryb `renderForPrint()` z natywnymi `<text>`/`<rect>` zamiast `<foreignObject>`. Rozwiązuje blockery #1 i #2 jednocześnie.
- **print-helper.js** — Progressive enhancement: `disabled` button do czasu DOMContentLoaded.
- **Response::view()** — Już akceptuje parametr `layout` — wystarczy go używać.
- **CSP** — `unsafe-inline` + `unsafe-eval` w `script-src` ze względu na Tailwind CDN. Backlog: prekompilowany Tailwind.
- **persons-print.php** — Liczba osób w nagłówku (jest w stopce).
- **print.php** — Radio "A3 landscape vs A4 landscape" do zmiany orientacji.
- **printList** — Opcja `?cognatic=1` / `?patrilinear=1` dla wydruku rejestru.

---

## Co działa dobrze

- **Kontrola dostępu**: `printView` i `printList` używają `TreeService::getForUser` + `requireTreeAccess` (właściciel + tree_members)
- **XSS**: wszystkie wartości z DB (`$tree->name`, `$p->firstName`, `$treeId`) są przez `htmlspecialchars`
- **SQL injection**: tylko prepared statements; whitelist na `$sortBy` w `findByTree`
- **Route ordering**: `/print` i `/new` poprawnie PRZED `{pid}`
- **Memory leak**: `URL.revokeObjectURL` w obu ścieżkach (success + error)
- **Fallback viewBox**: łańcuch fallbacków `vb.width || ... || 2480`
- **`page-break-inside: avoid`** na wierszach tabeli
- **`<footer class="no-screen">`**: elegancki wzorzec dla metadanych tylko do druku
- **Sortowanie**: `ORDER BY last_name, first_name` z utf8mb4_unicode_ci (case-insensitive)
- **Sanityzacja filename**: tylko UUID + `.png`

---

## Statystyki

| Kategoria | Liczba |
|-----------|--------|
| Plików reviewed | 11 |
| 🔴 Blocking | 3 |
| 🟠 Important | 6 |
| 🟡 Nit | 8 |
| 🔵 Suggestions | 7 |

**Gotowość do merge:** Funkcjonalnie OK dla samego druku (window.print). PNG export wymaga fix blockerów (canvas taint + CSS variables + race condition) — bez tego eksport PNG nie działa lub wygląda źle.
