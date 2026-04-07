# Kontekst i decyzje architektoniczne: Feature Druk/PDF — Genealog

Data: 2026-04-07
Autor: Claude Code (architect)

---

## Decyzja 1: CSS @media print zamiast Puppeteer / wkhtmltopdf

### Opcje rozważane

| Opcja | Zalety | Wady |
|-------|--------|------|
| **CSS @media print** (wybrana) | Zero zależności, działa w każdej przeglądarce, użytkownik kontroluje drukarkę | Wygląd zależy od przeglądarki, brak 100% pixel-perfect PDF |
| **Puppeteer (Node.js sidecar)** | Deterministyczny PDF, pełna kontrola | Wymaga Node.js + Chromium na serwerze (~500MB), złożone środowisko, security headless browser |
| **wkhtmltopdf** | Lekkie, dojrzałe | Przestarzałe (brak aktywnego maintainera), słabe wsparcie dla SVG i modern CSS |
| **mPDF / TCPDF (PHP)** | Natywne PHP | Nie obsługuje SVG D3.js, wymaga konwersji HTML→PDF ręcznie |

### Uzasadnienie wyboru CSS @media print

1. **MVP principle**: żadna z wymienionych bibliotek serwerowych nie jest warta złożoności na etapie MVP. Użytkownik może "Drukuj → Zapisz jako PDF" w Chrome/Firefox i uzyska PDF dobrej jakości.
2. **Zero środowisko**: nie trzeba instalować Chromium, Node.js ani żadnego systemu na serwerze PHP. Deploy jest trywialny.
3. **SVG jest źródłem prawdy**: D3.js generuje SVG w przeglądarce. CSS print doskonale obsługuje SVG — to natywny format wektorowy przeglądarki.
4. **Użytkownicy genealogiczni** (target audience: osoby 40–70 lat) są przyzwyczajeni do "Drukuj" w przeglądarce. Przycisk "Drukuj" otwierający okno systemowe jest intuicyjny.

### Ścieżka do Puppeteer (Faza 2+)

Jeśli pojawi się zapotrzebowanie na automatyczny PDF (np. link "Pobierz PDF" bez otwierania okna druku):

```
genealog/
└── pdf-renderer/       # Osobny mikroserwis, jak scraper/
    ├── main.py         # FastAPI: POST /render {html_url} → PDF bytes
    ├── renderer.py     # Playwright (Python) — headless Chromium
    └── Dockerfile
```

PHP wysyła URL strony `/trees/{id}/print` do mikroserwisu → mikroserwis otwiera URL w Chromium → zwraca PDF bytes → PHP serwuje jako download.

**Warunek**: dopiero gdy użytkownicy raportują problemy z "Drukuj" lub projekt potrzebuje e-mail attachment z PDF.

---

## Decyzja 2: SVG → PNG przez Canvas zamiast biblioteki

### Opcje rozważane

| Opcja | Zalety | Wady |
|-------|--------|------|
| **Canvas trick (Blob + Image + Canvas)** (wybrana) | Zero dependencies, działa offline, vanilla JS | Nie obsługuje zewnętrznych fontów/obrazów w SVG |
| **dom-to-image** (npm) | Obsługuje CSS, external resources | Wymaga bundlera lub CDN, kolejna zależność |
| **html2canvas** (npm) | Popularny, Community support | Nie obsługuje SVG natywnie, problemy z transform |
| **Canvg** (npm) | Specjalizowany SVG→Canvas | CDN dostępny, ale kolejna zależność (100KB) |

### Uzasadnienie Canvas trick

1. **Zero npm / CDN**: projekt używa D3 CDN i Alpine CDN — dodanie kolejnej biblioteki to kolejny punkt awarii i potencjalne opóźnienia loadingu.
2. **SVG generowany przez D3 jest "czysty"**: D3 nie używa zewnętrznych fontów ani `<image>` z CORS-owych URL. SVG zawiera tylko `<rect>`, `<text>`, `<line>`, `<path>` — wszystko serializowalne.
3. **Ograniczenia są akceptowalne**: zdjęcia osób (`<image>` w węzłach SVG) nie będą w PNG eksporcie. To akceptowalne — użytkownik drukuje strukturę drzewa, nie albumy zdjęć.
4. **Implementacja prosta**: ~40 linii JS bez edge cases specyficznych dla projektu.

### Znane ograniczenia Canvas trick

- **Zewnętrzne fonty**: jeśli SVG używa Google Fonts lub custom fonts ładowanych przez CSS — nie pojawią się w PNG. Fallback: systemowy sans-serif. Dla drzew genealogicznych to akceptowalne.
- **Taint canvas (CORS)**: jeśli SVG zawiera `<image href="https://...">` z zewnętrznej domeny — canvas zostanie "zatruta" i `toDataURL()` rzuci błąd. Mitygacja: zdjęcia serwowane przez `/media.php?id=X` (ten sam origin) lub w ogóle nie uwzględniane w SVG eksportu.
- **Duże drzewa**: Canvas ma limit ~4096×4096px lub ~256MB w zależności od przeglądarki. Dla genealogicznych drzew 5–6 pokoleń jest to wystarczające przy scale 2x.

---

## Decyzja 3: Rozmiar strony A3 landscape dla drzew, A4 portrait dla list

### Uzasadnienie

| Format | Zastosowanie | Uzasadnienie |
|--------|-------------|--------------|
| A3 landscape (420×297mm) | Druk SVG drzewa | Drzewo genealogiczne 4–5 pokoleń naturalnie jest szersze niż wyższe. A3 to standardowy format dla drzew rodowych w archiwach i towarzystwa genealogicznych. |
| A4 portrait (210×297mm) | Lista osób | Tabela tekstowa — format dokumentowy, czytelny w A4. Standardowy format wydruku biurowego. |

```css
/* Druk drzewa — print.php */
@page { size: A3 landscape; margin: 10mm; }

/* Lista osób — persons-print.php (nadpisuje default PrintLayout) */
@page { size: A4 portrait; margin: 15mm; }
```

### Wsparcie `@page` w przeglądarkach

- Chrome/Edge: pełne wsparcie `size` w `@page`
- Firefox: wsparcie od v95 (2021)
- Safari: ograniczone (ignoruje `size`, drukuje w formacie z ustawień drukarki)

**Mitygacja dla Safari**: instrukcja w UI "Jeśli drukujesz w Safari, wybierz A3 landscape w oknie druku".

---

## Decyzja 4: D3 SVG już istnieje — print strona tylko go pokazuje pełnoekranowo

### Architektura

Strona `/trees/{id}/print` **nie duplikuje** logiki D3. Zamiast tego:

1. Ładuje ten sam `tree-visualizer.js` co strona główna drzewa
2. Przekazuje ten sam `TREE_ID` (globalna zmienna JS)
3. `tree-visualizer.js` wykonuje `fetch('/api/trees/{id}/persons')` i renderuje SVG

**Różnica**: brak elementów UI (sidebar, search, toolbar), SVG zajmuje 100% viewport.

### Potencjalny problem: zoom/pan state

D3 inicjalizuje SVG z domyślnym widokiem (prawdopodobnie `fit to screen`). Na stronie druku to pożądane zachowanie. Jeśli `tree-visualizer.js` zapamiętuje stan zoom w `localStorage` — strona druku powinna resetować do domyślnego widoku.

**Rekomendacja**: sprawdzić czy `tree-visualizer.js` zawiera parametr `resetZoom` lub wywołać `d3.zoom().transform(svg, d3.zoomIdentity)` po renderowaniu.

### Zmienna globalna TREE_ID

```html
<!-- print.php: przed załadowaniem skryptów -->
<script>const TREE_ID = <?= (int)$treeId ?>;</script>
<script src="/js/d3.min.js"></script>
<script src="/js/tree-visualizer.js"></script>
<script src="/js/print-helper.js"></script>
```

`tree-visualizer.js` czyta `window.TREE_ID` lub `TREE_ID` bezpośrednio. Jeśli aktualnie czyta ID z `data-*` atrybutu kontenera — wystarczy dodać `data-tree-id="<?= $treeId ?>"` na `#tree-container`.

---

## Decyzja 5: Brak GEDCOM eksportu w tej fazie

GEDCOM eksport jest osobnym feature (Faza 5 w roadmapie projektu). Nie łączymy go z "Druk/PDF" — to inne przypadki użycia:

- Druk/PDF → wizualizacja i papierowy output
- GEDCOM → portabilność danych między systemami

Przycisk "Pobierz GEDCOM" zostanie dodany w widoku drzewa przy implementacji `GedcomService`.

---

## Decyzja 6: PrintLayout osobny od AppLayout

`AppLayout.php` zawiera:
- Header z nawigacją
- Sidebar
- Scripts (Alpine CDN, D3 CDN)
- Footer

Strony druku nie potrzebują nav/sidebar — generowałoby to:
1. Widoczne elementy przy druku (nawet z `.no-print` trzeba je załadować)
2. Niepotrzebny JS Alpine.js dla interaktywności UI
3. Ryzyko że ktoś przypadkowo usunie `.no-print` z nav i zniszczy druk

**Rozwiązanie**: `PrintLayout.php` to minimalny skeleton — tylko `<html><head><body>$content</body></html>` z Tailwind CDN i print styles. Żadna logika UI poza stroną druku.

---

## Alternatywy odrzucone

### Print CSS inline w AppLayout

Moglibyśmy dodać `@media print { nav { display: none } }` do AppLayout i drukować bezpośrednio ze strony drzewa. 

**Odrzucone**: 
- URL druku (`/trees/1`) jest URL drzewa — trudno linkować bezpośrednio
- Użytkownik musiałby "Drukuj" ze strony aplikacji — wszystkie overlaye, modaly, tooltips by zakłócały
- Osobna strona druku daje pełną kontrolę nad tym co jest drukowane

### Server-side PNG przez GD/ImageMagick

PHP mógłby renderować PNG z danych drzewa bez SVG (czysto programatycznie).

**Odrzucone**:
- Wymagałoby reimplementacji całej logiki layoutu D3.js w PHP
- GD nie obsługuje SVG natywnie — trzeba by konwertować przez ImageMagick + librsvg
- Instalacja librsvg na serwerze to kolejna zależność systemowa
- Wynik byłby gorszy od client-side Canvas (fonty, style)

---

## Roadmap po MVP

| Feature | Kiedy | Uzasadnienie |
|---------|-------|--------------|
| Puppeteer PDF sidecar | Faza 2 (po 3 miesiącach) | Jeśli użytkownicy zgłoszą problemy z print preview |
| Fan chart print | Razem z fan chart feature | Nowy widok, te same mechanizmy druku |
| Watermark na wydruku | Opcjonalne | "Wygenerowano przez Genealog.pl" w stopce |
| QR kod na wydruku | Opcjonalne | Link do drzewa online na wydruku papierowym |
| Print settings modal | Post-MVP | Wybór widoku (ancestorów/potomków), liczba pokoleń, czcionka |

---

## Code Review — 2026-04-07

Review przeprowadzony po wdrożeniu faz 1-5.

**Wynik:** 3 blocking, 6 important, 8 nit, 7 suggestions.

**Kluczowe ustalenia:**
1. **Eksport PNG nie działa lub wygląda źle** z 3 powodów:
   - Canvas taint przy `<foreignObject>` z `<img>` (Chrome/Safari SecurityError)
   - CSS variables `hsl(var(--X))` przestają istnieć po `XMLSerializer` → ramki czarne/niewidoczne
   - Race condition: inline `onclick` przed `defer` skryptem
2. **Druk SVG (window.print)** działa OK
3. **Niespójność architektoniczna**: `printView`/`printList` używają ręcznego `include` zamiast `Response::view()` (pomija walidację `realpath()`)
4. **Duplikacja**: reguły `@media print` są w 2 miejscach (PrintLayout inline `<style>` + `globals.css`)
5. **CSP**: `unsafe-inline` + `unsafe-eval` ze względu na Tailwind CDN

**Co działa dobrze:**
- Kontrola dostępu (TreeService::getForUser + requireTreeAccess)
- XSS prevention (htmlspecialchars wszędzie)
- Memory cleanup (URL.revokeObjectURL w obu ścieżkach)
- Route ordering (`/print` przed `{pid}`)
- Sortowanie listy osób

Pełny raport: `dev/active/print-pdf/review-print-pdf.md`

---

## Code Review #2 — 2026-04-07 (po poprawkach)

Drugi review po wdrożeniu poprawek z pierwszego (commit `c1472d5`).

**Wynik:** 0 blocking, 4 important, 5 nit, 5 suggestions. **Wszystkie poprawki z #1 zaadresowane (12/14 w pełni, 2/14 częściowo).**

**Nowe ustalenia (drobne):**
1. **`resolveCssVariables` regex** — flaga `gi` myląca (CSS variables są case-sensitive); brak wsparcia `var(--X, fallback)`
2. **Niespójność error handling** — `printView` (try/catch + error_log) vs `printList` (requireTreeAccess + findById dead-code)
3. **`htmlspecialchars` w `<style>`** — nie blokuje `;}`, dziś nieszkodliwe (hardcoded), ale potrzeba whitelist na przyszłość
4. **`$parseDate` jako closure w widoku** — anti-pattern, powinno być w `Person` lub `DateHelper`

**Brak regresji blokujących.** Kod gotowy do merge.

Pełny raport: `dev/active/print-pdf/review-print-pdf-v2.md`
