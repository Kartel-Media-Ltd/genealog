# Font Awesome — Checklist zadań

> Oznaczenia: `[ ]` do zrobienia, `[x]` ukończone, `[USER]` wymaga ręcznej akcji

---

## Status ogólny
- [ ] Faza 1: Setup vendor + helper (15 min)
- [ ] Faza 2: Integracja CSS w layoutach (10 min)
- [ ] Faza 3: Migracja AppLayout (20 min)
- [ ] Faza 4: Migracja toolbara show.php (15 min)
- [ ] Faza 5: Migracja AdminLayout sidebar (10 min)
- [ ] Faza 6: Migracja atomów (20 min)
- [ ] Faza 7: Migracja stron (30 min)
- [ ] Faza 8: Verification + dokumentacja (15 min)

**Total estimated time:** ~2h

---

## Faza 1: Setup vendor + helper

### 1.1 Pobieranie Font Awesome
- [ ] **[USER lub Claude]** Pobrać Font Awesome 6 Free Web:
  ```bash
  cd /tmp && \
  curl -L -o fontawesome.zip "https://use.fontawesome.com/releases/v6.7.2/fontawesome-free-6.7.2-web.zip" && \
  unzip -q fontawesome.zip
  ```
  > Alternatywnie: pobrać ręcznie z https://fontawesome.com/download (Free for the Web)

### 1.2 Kopiowanie do vendora
- [ ] Utworzyć katalog `public/vendor/fontawesome/{css,webfonts}`
- [ ] Skopiować pliki:
  ```bash
  mkdir -p public/vendor/fontawesome/{css,webfonts}
  cp /tmp/fontawesome-free-6.7.2-web/css/all.min.css public/vendor/fontawesome/css/
  cp /tmp/fontawesome-free-6.7.2-web/webfonts/fa-solid-900.woff2 public/vendor/fontawesome/webfonts/
  cp /tmp/fontawesome-free-6.7.2-web/webfonts/fa-solid-900.ttf public/vendor/fontawesome/webfonts/
  cp /tmp/fontawesome-free-6.7.2-web/webfonts/fa-brands-400.woff2 public/vendor/fontawesome/webfonts/
  cp /tmp/fontawesome-free-6.7.2-web/webfonts/fa-brands-400.ttf public/vendor/fontawesome/webfonts/
  cp /tmp/fontawesome-free-6.7.2-web/LICENSE.txt public/vendor/fontawesome/
  ```
- [ ] Utworzyć `public/vendor/fontawesome/README.md` z manifestem (wersja, źródło, data, licencja)

### 1.3 Helper atom
- [ ] Utworzyć `src/views/atoms/icon.php` z funkcją `render_icon($name, $variant, $extra, $title)` (kod w `font-awesome-plan.md`)
- [ ] Sprawdzić syntax: `php -l src/views/atoms/icon.php`

### 1.4 Test ładowania
- [ ] **[USER]** Sprawdzić w przeglądarce:
  ```bash
  curl -I http://localhost:8002/vendor/fontawesome/css/all.min.css
  ```
  Oczekiwane: HTTP 200 + Content-Type: text/css

---

## Faza 2: Integracja CSS w layoutach

### 2.1 AppLayout.php
- [ ] Dodać przed `</head>`:
  ```html
  <link rel="preload" href="/vendor/fontawesome/webfonts/fa-solid-900.woff2"
        as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
  ```

### 2.2 AdminLayout.php
- [ ] Dodać `<link>` do FA CSS (jak wyżej)

### 2.3 AuthLayout.php
- [ ] Dodać `<link>` do FA CSS (jak wyżej)

### 2.4 PrintLayout.php
- [ ] Dodać `<link>` do FA CSS (jak wyżej, BEZ preload — print rzadziej używany)

### 2.5 Test renderowania
- [ ] Dodać tymczasowo `<?php render_icon('user') ?>` w dashboard.php
- [ ] Otworzyć /dashboard → powinna pojawić się ikona usera
- [ ] Po weryfikacji: usunąć tymczasową ikonę

---

## Faza 3: Migracja AppLayout

### 3.1 Bell notifications icon (linia ~134)
- [ ] Zastąpić inline `<svg>` (path bell) → `<?php require_once __DIR__ . '/../atoms/icon.php'; render_icon('bell', 'solid', 'h-5 w-5'); ?>`
- [ ] Test: bell wciąż klikalna, dropdown otwiera się

### 3.2 Mobile hamburger
- [ ] Zastąpić SVG hamburgera → `render_icon('bars', 'solid', 'h-5 w-5')`

### 3.3 User dropdown chevron
- [ ] Zastąpić SVG chevron-down → `render_icon('chevron-down', 'solid', 'h-3 w-3')`

### 3.4 Logout icon (desktop dropdown)
- [ ] Zastąpić SVG door/exit → `render_icon('arrow-right-from-bracket', 'solid', 'h-4 w-4')`

### 3.5 Settings menuitem
- [ ] Zastąpić SVG gear → `render_icon('gear', 'solid', 'h-4 w-4')`

### 3.6 Profile menuitem
- [ ] Zastąpić SVG user → `render_icon('user', 'solid', 'h-4 w-4')`

### 3.7 Nav links — DODAJ ikony (obecnie tylko tekst)
- [ ] Dashboard: `render_icon('house', 'solid', 'h-4 w-4 mr-2')` przed labelem
- [ ] Trees: `render_icon('sitemap', 'solid', 'h-4 w-4 mr-2')` lub `tree`
- [ ] Search: `render_icon('magnifying-glass', 'solid', 'h-4 w-4 mr-2')`

### 3.8 Test
- [ ] Wszystkie ikony renderują się
- [ ] Bell polling działa (ikona nie blokuje JS)
- [ ] Mobile hamburger otwiera menu
- [ ] Dropdown user pokazuje ikonki w menuitems

---

## Faza 4: Migracja toolbara show.php

### 4.1 Drukuj drzewo
- [ ] `render_icon('print', 'solid', 'h-4 w-4')`

### 4.2 Dodaj osobę (primary)
- [ ] `render_icon('user-plus', 'solid', 'h-4 w-4')`

### 4.3 Osoby
- [ ] `render_icon('users', 'solid', 'h-4 w-4')`

### 4.4 Import/Export
- [ ] `render_icon('file-import', 'solid', 'h-4 w-4')` (lub `file-arrow-up`)

### 4.5 Dostęp (members)
- [ ] `render_icon('user-group', 'solid', 'h-4 w-4')`

### 4.6 Odkrywanie
- [ ] `render_icon('magnifying-glass', 'solid', 'h-4 w-4')`

### 4.7 Ustawienia
- [ ] `render_icon('gear', 'solid', 'h-4 w-4')`

### 4.8 Test
- [ ] Toolbar wyświetla 7 ikon w nowej kolejności
- [ ] Mobile: tylko ikony (bez label)
- [ ] Desktop: ikony + label
- [ ] Wszystkie linki działają

---

## Faza 5: Migracja AdminLayout sidebar

### 5.1 Dashboard
- [ ] `render_icon('gauge-high', 'solid', 'fa-fw h-4 w-4')`
  > `fa-fw` = fixed width — wyrównuje wszystkie ikony w kolumnie

### 5.2 Users
- [ ] `render_icon('users', 'solid', 'fa-fw h-4 w-4')`

### 5.3 Trees
- [ ] `render_icon('tree', 'solid', 'fa-fw h-4 w-4')` (lub `sitemap`)

### 5.4 Logs
- [ ] `render_icon('list-ul', 'solid', 'fa-fw h-4 w-4')` (lub `clipboard-list`)

### 5.5 Top bar logout (admin)
- [ ] `render_icon('arrow-right-from-bracket', 'solid', 'h-4 w-4')`

### 5.6 Test
- [ ] Sidebar z 4 ikonami wyrównanymi w kolumnie
- [ ] Aktywny link wciąż ma highlight
- [ ] Topbar logout działa

---

## Faza 6: Migracja atomów

### 6.1 alert.php
- [ ] info → `render_icon('circle-info', 'solid', 'h-5 w-5')`
- [ ] success → `render_icon('circle-check', 'solid', 'h-5 w-5')`
- [ ] warning → `render_icon('triangle-exclamation', 'solid', 'h-5 w-5')`
- [ ] error → `render_icon('circle-exclamation', 'solid', 'h-5 w-5')`
- [ ] dismiss (X) → `render_icon('xmark', 'solid', 'h-4 w-4')`

### 6.2 spinner.php
- [ ] Zamienić animate-spin SVG → `render_icon('spinner', 'solid', 'fa-spin h-5 w-5')`
  > FA ma wbudowaną klasę `fa-spin` (rotacja CSS)

### 6.3 button.php
- [ ] **OPCJONALNIE:** Dodać parameter `$icon` aby `render_button(['label' => 'Save', 'icon' => 'floppy-disk'])` automatycznie dodawał ikonę przed labelem

### 6.4 avatar.php
- [ ] Fallback gdy brak inicjałów → `render_icon('user', 'solid', 'h-full w-full p-2')` (rzadko, można pominąć)

### 6.5 Test
- [ ] Flash messages: 4 typy z różnymi ikonami
- [ ] Spinner kręci się poprawnie
- [ ] Button z ikoną renderuje się ok

---

## Faza 7: Migracja stron

### 7.1 pages/trees/persons-print.php
- [ ] Drukuj listę → `fa-print`
- [ ] Zamknij → `fa-xmark`

### 7.2 pages/trees/persons/show.php
- [ ] Edit → `fa-pen-to-square`
- [ ] Delete → `fa-trash`
- [ ] Add photo → `fa-camera`
- [ ] Pozostałe inline SVG → odpowiednie FA

### 7.3 pages/trees/persons/index.php
- [ ] Search → `fa-magnifying-glass`
- [ ] Sort arrows → `fa-arrow-up-a-z` / `fa-arrow-down-a-z`
- [ ] View toggle (lista/hierarchia) → `fa-list` / `fa-folder-tree`

### 7.4 pages/trees/members.php
- [ ] Invite → `fa-paper-plane`
- [ ] Remove → `fa-user-minus`
- [ ] Change role → `fa-shield-halved`

### 7.5 pages/admin/users.php
- [ ] Block → `fa-ban`
- [ ] Unblock → `fa-circle-check`
- [ ] Promote → `fa-arrow-up`
- [ ] Demote → `fa-arrow-down`
- [ ] Impersonate → `fa-user-secret` lub `fa-masks-theater`

### 7.6 pages/admin/user-detail.php
- [ ] Te same co users.php (Block/Unblock/Promote/Demote/Impersonate)

### 7.7 pages/trees/gedcom.php
- [ ] Upload → `fa-upload`
- [ ] Download → `fa-download`

### 7.8 pages/auth/login.php + register.php
- [ ] Email field → `fa-envelope` (jeśli używany jako prefix)
- [ ] Password → `fa-lock`
- [ ] Login button → `fa-arrow-right-to-bracket`
- [ ] Register button → `fa-user-plus`

### 7.9 Test każdej strony po migracji
- [ ] Wszystkie ikony renderują się
- [ ] Brak złamanego layoutu (rozmiar ikony zgadza się ze starym SVG)

---

## Faza 8: Verification + dokumentacja

### 8.1 Audit grep
- [ ] `grep -rn '<svg xmlns="http' src/views/` → 0 wyników (oprócz dynamicznie generowanych w `tree-visualizer.js`)
- [ ] `grep -rn '<i class="fa-' src/views/ | wc -l` → liczba migrowanych ikon (powinno być >50)
- [ ] `grep -rn 'render_icon(' src/views/ | wc -l` → liczba użyć helpera

### 8.2 Testy manualne
- [ ] AppLayout (zalogowany): nav + bell + dropdown user
- [ ] Toolbar /trees/{id}: 7 ikon w nowej kolejności + responsive
- [ ] Admin panel: sidebar + topbar + actions
- [ ] Print preview /trees/{id}: ikony widoczne
- [ ] Flash messages: 4 typy z ikonami
- [ ] Login/register: ikony w formularzach

### 8.3 Performance check
- [ ] Network tab (DevTools):
  - [ ] `all.min.css` → ~28KB gzip, status 200
  - [ ] `fa-solid-900.woff2` → ~75KB, ładowane raz, cache na kolejnych stronach
  - [ ] `fa-brands-400.woff2` → ładowane TYLKO jeśli używane brand icons
- [ ] Lighthouse score nie spadł (Performance >= 90)

### 8.4 Atrybucja
- [ ] Dodać do `AppLayout.php` footer (lub dedykowanego `/about`):
  ```html
  <p class="text-xs text-muted-foreground">
    Icons by <a href="https://fontawesome.com/license/free" class="underline">Font Awesome Free</a>
    (CC BY 4.0)
  </p>
  ```

### 8.5 Dokumentacja
- [ ] Dodać do `CLAUDE.md` (sekcja "Stack technologiczny" lub nowa "Ikony"):
  ```markdown
  ### Ikony
  - **Font Awesome 6 Free** (lokalnie w `public/vendor/fontawesome/`)
  - Helper: `<?php render_icon('user', 'solid', 'h-5 w-5') ?>`
  - Warianty: `solid` (default), `brands`, `regular`
  - Lista ikon: https://fontawesome.com/search?o=r&m=free&s=solid
  - NIE używaj inline SVG dla nowych komponentów — używaj `render_icon()`
  ```

### 8.6 Final checks
- [ ] `php vendor/bin/phpunit tests/` → wszystkie testy zielone
- [ ] `composer phpstan` → brak nowych errorów
- [ ] `php -l` na kluczowych zmienionych plikach
- [ ] Git commit: `feat(ui): migracja na Font Awesome 6 Free (lokalnie)`

---

## Notatki implementacyjne

- **Plik vendor:** Po pobraniu Font Awesome zip, NIE commitujemy całego archiwum tylko wybrane pliki (4 webfonts + 1 CSS + LICENSE)
- **Wersjonowanie:** W przyszłości przy update FA — utworzyć `vendor/fontawesome-6.8/` zamiast nadpisać, dla łatwego rollback
- **fa-fw (fixed width):** Używać w sidebarach i listach gdzie ikony muszą być wyrównane w kolumnie
- **Animowane:** `fa-spin` (rotacja), `fa-pulse` (skokowa), `fa-beat` (puls), `fa-shake` (drżenie)
- **Rozmiary:** FA ma wbudowane klasy `fa-xs`, `fa-sm`, `fa-lg`, `fa-xl`, `fa-2x`...`fa-10x` — ALE w naszym projekcie używamy Tailwind (`h-4 w-4` itd.) dla spójności
