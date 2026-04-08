# Font Awesome — Plan architektoniczny

## Klasyfikacja: STANDARD
**Powód:** Proste asset integration (brak data model, brak backendu, brak RBAC), ale dotyka 19+ plików widoków. Wymaga helpera + masowej migracji + weryfikacji.

---

## Cel

Zastąpić wszystkie inline SVG w widokach (atomy, molekuły, layouty, strony) ikonami Font Awesome, hostowanymi **lokalnie** w `public/vendor/fontawesome/`. Bez CDN, bez kit.fontawesome.com — pełna kontrola, brak zewnętrznych requestów (RODO + offline-friendly).

API dla developera:
```php
// Helper atom
<?php render_icon('user') ?>                    // <i class="fa-solid fa-user" aria-hidden="true"></i>
<?php render_icon('github', 'brands') ?>        // <i class="fa-brands fa-github" aria-hidden="true"></i>
<?php render_icon('user', 'solid', 'h-5 w-5') ?> // z dodatkowymi klasami
```

---

## Stack i wybór wersji

| Aspekt | Wybór | Uzasadnienie |
|---|---|---|
| **Font Awesome wersja** | 6.x Free (najnowsza stabilna 6.7+) | FA 7.x świeżo wydane, jeszcze niestabilne. FA Pro płatne ($99/rok). Free ma 2000+ ikon — wystarcza |
| **Format dystrybucji** | Webfonts CSS (woff2) | Najlepsza wydajność, zerowa zależność od JS. Działa bez JS w przeglądarce (vs Kit). Wsparcie dla `font-display: swap` |
| **Warianty** | Solid + Brands | Solid (1390 ikon) wystarcza dla aplikacji. Brands (467) dla potencjalnego social login. Regular (163) — pomijamy w MVP (większość dubli z solid) |
| **Hostowanie** | `public/vendor/fontawesome/` | Spójne z d3.min.js, alpine.min.js, tailwind.js |
| **Licencja** | SIL OFL 1.1 (fonts) + MIT (CSS) + CC BY 4.0 (ikony) | Wymaga atrybucji w produkcji — dodać do `/about` lub footer |

### Rozmiar bundle'a (FA Free 6.7)
| Plik | Rozmiar (gzip) |
|---|---|
| `css/all.min.css` | ~28KB |
| `webfonts/fa-solid-900.woff2` | ~75KB |
| `webfonts/fa-brands-400.woff2` | ~75KB |
| **Razem** | **~180KB** (jednorazowe pobranie + cache) |

W porównaniu do obecnych inline SVG: ~3KB per ikona × 19 plików = ~57KB rozproszone w HTML. FA daje DRY + rozszerzalność.

---

## Architektura plików

```
public/
└── vendor/
    └── fontawesome/                    ← NOWY katalog
        ├── css/
        │   └── all.min.css             ← jedyny plik CSS który ładujemy
        ├── webfonts/
        │   ├── fa-solid-900.woff2      ← Solid (główny wariant)
        │   ├── fa-solid-900.ttf        ← fallback dla starych browserów
        │   ├── fa-brands-400.woff2     ← Brands
        │   └── fa-brands-400.ttf
        ├── LICENSE.txt                 ← SIL OFL + MIT + CC BY
        └── README.md                   ← skrócony manifest

src/
├── views/
│   └── atoms/
│       └── icon.php                    ← NOWY: helper render_icon()
└── ...

dev/active/font-awesome/
├── font-awesome-plan.md                ← TEN PLIK
├── font-awesome-kontekst.md            ← decyzje, mapowanie ikon
├── font-awesome-zadania.md             ← checklist implementacji
└── icon-mapping.md                     ← tabela: stara SVG → fa-*
```

---

## Helper API: `src/views/atoms/icon.php`

```php
<?php
declare(strict_types=1);

/**
 * Atom: Font Awesome Icon
 *
 * Renderuje ikonę Font Awesome jako <i class="fa-..."> z domyślnym aria-hidden.
 * Używaj zamiast inline SVG dla spójności i mniejszego HTML.
 *
 * @param string $name    Nazwa ikony bez prefiksu fa- (np. 'user', 'house', 'gear')
 * @param string $variant 'solid' | 'brands' | 'regular' (default: 'solid')
 * @param string $extra   Dodatkowe klasy CSS (np. 'h-5 w-5 text-primary')
 * @param string $title   Opcjonalny title (tooltip + aria-label)
 *
 * @example
 *   <?php render_icon('user') ?>
 *   <?php render_icon('github', 'brands') ?>
 *   <?php render_icon('gear', 'solid', 'h-5 w-5 text-muted-foreground', 'Ustawienia') ?>
 */
if (!function_exists('render_icon')) :
function render_icon(
    string $name,
    string $variant = 'solid',
    string $extra = '',
    string $title = '',
): void {
    // Strip 'fa-' prefix gdyby user go podał
    $clean = ltrim($name, '');
    $clean = str_starts_with($clean, 'fa-') ? substr($clean, 3) : $clean;

    // Whitelist wariantów (chroni przed dowolnym CSS)
    $variantClass = match ($variant) {
        'brands'  => 'fa-brands',
        'regular' => 'fa-regular',
        default   => 'fa-solid',
    };

    $titleAttr = $title !== ''
        ? sprintf(' title="%s" aria-label="%s"', htmlspecialchars($title), htmlspecialchars($title))
        : ' aria-hidden="true"';

    printf(
        '<i class="%s fa-%s %s"%s></i>',
        $variantClass,
        htmlspecialchars($clean, ENT_QUOTES),
        htmlspecialchars($extra),
        $titleAttr
    );
}
endif;
```

### Konwencje użycia
- **Bez tooltipa (dekoracyjna):** `render_icon('user')` → `aria-hidden="true"` (screen reader pomija)
- **Z tooltipem (interaktywna):** `render_icon('gear', 'solid', '', 'Ustawienia')` → `aria-label` + `title`
- **Z rozmiarem:** `render_icon('user', 'solid', 'h-5 w-5')` (tailwind classes)
- **Z kolorem:** `render_icon('check', 'solid', 'text-green-600')`

---

## Integracja w layoutach

### `src/views/templates/AppLayout.php`
```html
<head>
    <!-- ... -->
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
    <!-- ... -->
</head>
```

### `src/views/templates/AdminLayout.php`
Tak samo (osobny layout, własne `<head>`).

### `src/views/templates/AuthLayout.php`
Tak samo.

### `src/views/templates/PrintLayout.php`
**Tak samo** — ikony muszą działać w druku (np. ikona drukarki w toolbarze, gear w nagłówku raportu).

### Globalna optymalizacja: preload font
```html
<link rel="preload" href="/vendor/fontawesome/webfonts/fa-solid-900.woff2"
      as="font" type="font/woff2" crossorigin>
```
Eliminuje FOIT (Flash of Invisible Text) — font zaczyna się ładować natychmiast.

---

## Mapowanie obecnych SVG → Font Awesome

Pełna tabela w `font-awesome-zadania.md`. Przykłady kluczowych:

| Lokacja | Obecne SVG | Font Awesome |
|---|---|---|
| **AppLayout nav** | | |
| Dashboard | (brak ikony — tylko tekst) | `fa-house` |
| Trees | (brak) | `fa-sitemap` |
| Search | (brak) | `fa-magnifying-glass` |
| Bell notifications | inline bell SVG | `fa-bell` |
| **show.php toolbar** | | |
| Drukuj | printer SVG | `fa-print` |
| Dodaj osobę | plus SVG | `fa-user-plus` |
| Osoby | users SVG | `fa-users` |
| Import/Export | file-text SVG | `fa-file-import` |
| Dostęp | users-group SVG | `fa-user-group` |
| Odkrywanie | magnifying glass SVG | `fa-magnifying-glass` |
| Ustawienia | gear SVG | `fa-gear` |
| **AdminLayout sidebar** | | |
| Dashboard | (brak/SVG) | `fa-gauge-high` |
| Users | (brak/SVG) | `fa-users` |
| Trees | (brak/SVG) | `fa-tree` |
| Logs | (brak/SVG) | `fa-list-ul` |
| **Atoms** | | |
| `alert.php` | info/check/warn/error SVG | `fa-circle-info` / `fa-circle-check` / `fa-triangle-exclamation` / `fa-circle-exclamation` |
| `spinner.php` | animate-spin SVG | `fa-spinner fa-spin` |
| **Forms** | | |
| Save | check SVG | `fa-floppy-disk` |
| Delete | trash SVG | `fa-trash` |
| Edit | pencil SVG | `fa-pen-to-square` |
| Close (X) | x SVG | `fa-xmark` |

---

## Fazy implementacji

### Faza 1: Setup vendor + helper (15 min)
1. Pobrać Font Awesome Free 6.7 (zip)
2. Skopiować do `public/vendor/fontawesome/`:
   - `css/all.min.css`
   - `webfonts/fa-solid-900.woff2` + `.ttf`
   - `webfonts/fa-brands-400.woff2` + `.ttf`
   - `LICENSE.txt`
3. Stworzyć `src/views/atoms/icon.php` z `render_icon()` helper
4. Test: dodać `<?php render_icon('user') ?>` w dashboard, sprawdzić wyświetlanie

### Faza 2: Integracja w layoutach (10 min)
1. Dodać `<link>` do FA CSS w `AppLayout.php` + `AdminLayout.php` + `AuthLayout.php` + `PrintLayout.php`
2. Dodać `<link rel="preload">` dla `fa-solid-900.woff2` (preload optimization)
3. Zweryfikować że ikona testowa z Fazy 1 wciąż działa

### Faza 3: Migracja AppLayout (20 min)
1. Bell notifications icon → `fa-bell`
2. Mobile hamburger → `fa-bars`
3. User dropdown chevron → `fa-chevron-down`
4. Logout icon → `fa-arrow-right-from-bracket`
5. Settings menuitem → `fa-gear`
6. Profile menuitem → `fa-user`
7. Dodać ikony do nav links: Dashboard (`fa-house`), Trees (`fa-sitemap`), Search (`fa-magnifying-glass`)
8. Test wizualny + screen reader

### Faza 4: Migracja toolbara show.php (15 min)
1. Drukuj → `fa-print`
2. Dodaj osobę → `fa-user-plus`
3. Osoby → `fa-users`
4. Import/Export → `fa-file-import`
5. Dostęp → `fa-user-group`
6. Odkrywanie → `fa-magnifying-glass`
7. Ustawienia → `fa-gear`
8. Test responsive (mobile = tylko ikona, md+ = ikona + label)

### Faza 5: Migracja AdminLayout sidebar (10 min)
1. Dashboard → `fa-gauge-high`
2. Users → `fa-users`
3. Trees → `fa-tree`
4. Logs → `fa-list-ul`
5. Logout (top bar) → `fa-arrow-right-from-bracket`

### Faza 6: Migracja atomów (20 min)
1. `alert.php` — info/success/warning/error ikony → odpowiednie `fa-circle-*`
2. `spinner.php` — animate-spin SVG → `fa-spinner fa-spin`
3. `avatar.php` — fallback chevron (jeśli jest) → `fa-user`
4. `button.php` — opcjonalny `$icon` parameter (jeśli warto)

### Faza 7: Migracja stron (30 min)
1. `pages/trees/persons-print.php` — printer SVG → `fa-print`
2. `pages/trees/persons/show.php` — wszystkie inline SVG (edit, delete, photo, etc.)
3. `pages/trees/members.php` — invite/remove/role icons
4. `pages/admin/users.php` — block/promote/impersonate icons
5. `pages/admin/user-detail.php` — analogicznie
6. Pozostałe strony: Grep `<svg` i zamienić

### Faza 8: Verification + dokumentacja (15 min)
1. Grep `'<svg xmlns="http"'` w `src/views/` — powinno być **0 wyników** (poza specyficznymi przypadkami jak loader-spinnery z animacjami)
2. Manual test:
   - AppLayout nav → wszystkie ikony renderują się
   - Toolbar show.php → ikony + responsive
   - Admin panel → sidebar nav
   - Print preview → ikony nie znikają w druku
3. Sprawdzić rozmiar requestów (Network tab):
   - `all.min.css` powinien być ~28KB gzip
   - `fa-solid-900.woff2` powinien być cached po pierwszym requeście
4. Atrybucja licencji: dodać sekcję "Credits" w `/about` lub footer (pkt RODO Art. 25)
5. Dodać do `CLAUDE.md` notatkę:
   ```markdown
   ### Ikony
   - **Font Awesome 6 Free** (lokalnie w `public/vendor/fontawesome/`)
   - Helper: `<?php render_icon('user', 'solid', 'h-5 w-5') ?>`
   - NIE używaj inline SVG dla nowych komponentów
   ```

---

## Bezpieczeństwo i RODO

| Aspekt | Status |
|---|---|
| **External requests** | ❌ ŻADNE — pełnie lokalnie. Brak kontaktu z fontawesome.com / Cloudflare CDN |
| **CSP `font-src`** | Wymaga `'self'` (domyślne — już ok) |
| **CSP `style-src`** | Wymaga `'self'` dla `all.min.css` (już ok) |
| **Subresource Integrity** | N/A (lokalne pliki) |
| **RODO** | ✅ Zero tracking, zero kontaktów z third-party |
| **Licencja** | SIL OFL + MIT + CC BY 4.0 — atrybucja w stopce/about wymagana |
| **CVE** | FA 6.x ma 0 znanych CVE (font + CSS, brak JS execution) |

---

## Edge cases

| Scenariusz | Obsługa |
|---|---|
| **Browser bez WOFF2** (IE11) | Fallback na `.ttf` (ładowane przez `@font-face` w `all.min.css`) |
| **JS wyłączony** | Nie wpływa — FA webfonts są pure CSS |
| **Print preview** | Ikony renderują się; FA ma `print:` media support |
| **Screen reader** | `aria-hidden="true"` na dekoracyjnych, `aria-label` na interaktywnych |
| **Slow connection** | `font-display: swap` w FA CSS — text wyświetla się od razu, ikona później |
| **Cache invalidation** | Pliki wersjonowane w nazwie folderu (`vendor/fontawesome-6.7/` w przyszłości) |
| **Brak FA CSS** (404) | Ikony nie renderują się ale layout nie pęka. Zaleca się dodać `text-content` jako fallback w krytycznych miejscach |

---

## Migration strategy

**Big bang vs gradual:** Wybieramy **gradual w jednym sprincie** — wszystkie 7 faz w jednej sesji `/ultra-workaholic`. Ryzyko regresji minimalne (dotyka tylko widoków, nie logiki). Po każdej fazie testujemy wizualnie.

**Backward compatibility:** Inline SVG które zostaną w `tree-visualizer.js` (D3 generuje SVG dynamicznie) — **nie ruszamy**. Te ikony są częścią rendererów drzewa, nie UI.

**Breaking changes:** Brak. Helper `render_icon()` to nowy plik, layouty zyskują tylko `<link>`. Stare SVG działają obok nowych przez cały okres migracji.

---

## Ryzyka

| Ryzyko | Prawdopodobieństwo | Mitygacja |
|---|---|---|
| Nie znajdziemy odpowiedniej ikony FA dla każdego SVG | Niskie | FA Free ma 2000+ ikon, pokrywa wszystkie popularne case'y |
| Migracja stron zajmie więcej niż 30 min | Średnie | Atomizować — robić po jednym pliku, testować |
| FA CSS koliduje z istniejącymi klasami | Bardzo niskie | FA używa prefiksu `fa-`, brak konfliktów z Tailwind/custom |
| FOIT (flash of invisible text) | Niskie | Preload + `font-display: swap` w FA CSS |
| Rozmiar bundle'a obciąża mobile | Niskie | 180KB jednorazowo + cache; daje DRY na ~19 plikach |

---

## Verification checklist (Faza 8)

- [ ] `find public/vendor/fontawesome -type f` → wszystkie 4 pliki obecne
- [ ] `curl -I http://localhost:8002/vendor/fontawesome/css/all.min.css` → 200 OK
- [ ] `grep -rn '<svg xmlns="http' src/views/` → 0 wyników (lub tylko `<svg>` w decorative-static gdzie zostają celowo)
- [ ] Test responsive: `<md` ikony, `≥md` ikony + labele (toolbar show.php)
- [ ] Test print: Cmd+P na `/trees/{id}` → ikony widoczne w preview
- [ ] Test screen reader: VoiceOver/NVDA czyta `aria-label` interaktywnych ikon
- [ ] Network tab: `fa-solid-900.woff2` ładuje się raz i jest w cache
- [ ] Atrybucja: footer lub `/about` zawiera "Icons by Font Awesome (CC BY 4.0)"

---

## Następne kroki po MVP

1. **FA Pro** (jeśli kiedyś) — dodatkowe ikony genealogiczne, light variant
2. **Icon subsetting** — narzędzie `fontawesome-subset` może wyciąć tylko używane ikony i zmniejszyć bundle do ~30KB
3. **Migracja D3 SVG** — opcjonalnie zastąpić ikony w drzewie (ale to wymaga `<text>` z FA Unicode codepoints, nie `<i>`)
4. **Custom icon set** — własne SVG dla genealogicznych specjalizacji (np. herb rodu, drzewo rodzinne) jako addon do FA
