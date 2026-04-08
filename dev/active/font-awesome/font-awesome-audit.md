# Font Awesome — Audyt bezpieczeństwa

## Klasyfikacja: STANDARD (frontend asset migration)
- Brak data model changes
- Brak backendu (PHP serwuje tylko static files)
- Brak RBAC changes
- Tylko frontend (CSS + woff2 + helper PHP)

---

## Threat Model — STRIDE

| Threat | Wektor | Mitigacja | Status |
|---|---|---|---|
| **Spoofing** | Atakujący podmienia FA CSS na malicious | Pliki w git, deploy z weryfikacją checksums | ✅ Mitigowane przez vendor in-repo |
| **Tampering** | Linter/formater modyfikuje vendor/fontawesome/ | `.gitattributes` + `.editorconfig` exclusions | ⚠️ Dodać do `.gitignore` lub specjalnego attribute |
| **Repudiation** | Brak — nie loguje akcji | N/A | N/A |
| **Information Disclosure** | FA Kit logował IP użytkowników do analytics | Self-hosted = zero zewnętrznych requestów | ✅ Cel decyzji "lokalnie" |
| **DoS** | Brak — static files serwowane przez Apache/PHP-FPM | Nginx/Apache caching headers (`Cache-Control: max-age=...`) | ⚠️ Sprawdzić config |
| **Elevation of Privilege** | XSS przez `render_icon($name)` jeśli nazwa z user input | `htmlspecialchars` + whitelist wariantów | ✅ Helper bezpieczny |

---

## OWASP Top 10 — sprawdzenia

### A03:2021 — Injection
- **Helper `render_icon`:** parametry escapowane przez `htmlspecialchars`
- **Whitelist `$variant`:** match expression z 3 wartościami (solid/regular/brands), default = solid
- **Strip prefix:** `ltrim($name, 'fa-')` chroni przed injection klas
- **Status:** ✅ Bezpieczne

### A05:2021 — Security Misconfiguration
- **CSP `style-src`:** Wymaga `'self'` dla `all.min.css` (już ustawione w `public/index.php`)
- **CSP `font-src`:** Wymaga `'self'` dla webfonts (dziedziczone z `default-src 'self'`)
- **Status:** ✅ Bez zmian w CSP — domyślne wartości pokrywają

### A06:2021 — Vulnerable and Outdated Components
- **FA 6.7+ CVE history:** 0 znanych CVE (font + CSS, brak JS execution)
- **Update strategy:** Manual przy bumpach FA — nie ma `composer audit` dla static files
- **Mitigacja:** Co kwartał sprawdzić https://github.com/FortAwesome/Font-Awesome/releases
- **Status:** ⚠️ Dodać do TODO comeback co 3 miesiące

### A08:2021 — Software and Data Integrity Failures
- **SRI (Subresource Integrity):** N/A dla local files (żadnego CDN)
- **Vendor source:** Pobrany z oficjalnego https://fontawesome.com/download (nie z third-party CDN/forku)
- **Status:** ✅ Pobranie z zaufanego źródła

### A09:2021 — Security Logging and Monitoring Failures
- **N/A** — feature nie wprowadza nowych endpointów ani akcji do audit log

---

## RODO Art. 25 — Privacy by Design

### Zero kontaktów z third-party
| Aspekt | Status |
|---|---|
| Requesty do `fontawesome.com` | ❌ Brak |
| Requesty do `kit.fontawesome.com` | ❌ Brak (nie używamy Kit) |
| Requesty do `cdnjs.cloudflare.com` | ❌ Brak |
| Cookies third-party | ❌ Brak (statyczne pliki) |
| Tracking pixels | ❌ Brak |
| **Verdict** | ✅ **Pełna prywatność** |

### Cel wybór "lokalnie w vendorze"
Odpowiednia opcja zgodna z **RODO Art. 25 (privacy by design)** + **Schrems II** (problem transferów do USA przez CDN). Self-hosting eliminuje wszystkie te problemy.

---

## NIS2 — Risk Management

| Ryzyko | Prawdopodobieństwo | Wpływ | Mitygacja |
|---|---|---|---|
| FA wydaje krytyczny patch | Niskie | Niski | Manual update co kwartał |
| Repo z malicious vendor file | Bardzo niskie | Średni | Code review + git history |
| Vendor file zmieniony przez błąd | Niskie | Niski | `git diff` przed commit |
| Bundle size rośnie ponad budżet | Niskie | Niski | Lighthouse w CI (later) |

---

## WCAG 2.2 — Accessibility

### Test ikon w kontekście dostępności

| Wytyczna WCAG | Wymóg | Nasza implementacja |
|---|---|---|
| **1.1.1 Non-text Content** | Każdy element nie-tekstowy ma alt text | Helper dodaje `aria-hidden="true"` (dekoracyjne) lub `aria-label` (z `$title` parametr) |
| **1.3.1 Info and Relationships** | Strukturalna informacja zachowana programatycznie | Ikony interaktywne mają `aria-label` opisujący akcję |
| **1.4.3 Contrast (Minimum)** | Kontrast 4.5:1 dla tekstu | Ikony dziedziczą `color` z parent — używamy semantic Tailwind colors (`text-foreground`, `text-muted-foreground`) z high contrast |
| **2.1.1 Keyboard** | Wszystkie funkcje dostępne z klawiatury | Ikony są wewnątrz `<button>` lub `<a>` — focus naturally |
| **2.4.4 Link Purpose** | Cel linku jest jasny | Ikony interaktywne mają `aria-label` (np. "Zarządzaj dostępem") |
| **2.5.5 Target Size (AAA)** | Klikalne 44×44px minimum | Toolbar buttons `h-10 w-10` = 40×40px ⚠️ — poniżej AAA, ale powyżej AA |

### Best practice dla helperów ikon

```php
// ✅ DOBRZE — dekoracyjna ikona
render_icon('user', 'solid', 'mr-2'); // aria-hidden="true"

// ✅ DOBRZE — interaktywna ikona z aria-label
<button>
    <?php render_icon('trash', 'solid', 'h-4 w-4', 'Usuń osobę'); ?>
</button>

// ❌ ŹLE — interaktywna ikona BEZ context
<button>
    <?php render_icon('trash'); ?>  <!-- screen reader nic nie usłyszy -->
</button>
```

**Rekomendacja:** Code review każdego użycia `render_icon` w przyciskach — musi mieć `$title` parameter LUB `<span class="sr-only">` w parent.

---

## Wnioski audytu

### Mocne strony
1. ✅ **Zero zewnętrznych requestów** — pełna RODO compliance
2. ✅ **Helper z whitelist + escapowaniem** — bezpieczeństwo XSS
3. ✅ **Brak JS** — eliminuje całą klasę bugów (FA Kit ma JS, my nie)
4. ✅ **Brak CVE w FA 6** — dojrzała biblioteka
5. ✅ **CSP nie wymaga zmian** — `'self'` wystarcza
6. ✅ **Backward compat** — stare SVG działają obok nowych w trakcie migracji

### Słabe strony
1. ⚠️ **Update strategy manual** — brak `composer audit` dla static files; trzeba pamiętać o sprawdzaniu releases co 3 miesiące
2. ⚠️ **A11y zależne od dyscypliny** — helper umożliwia zarówno dobre jak i złe użycie; trzeba code review
3. ⚠️ **Bundle 180KB** — minimalnie, ale można subset down do 30KB później

### Rekomendacje
1. **Pre-deploy:** Dodać `vendor/fontawesome/` do `.gitattributes`:
   ```
   public/vendor/fontawesome/** -text
   public/vendor/fontawesome/** linguist-vendored
   ```
   To zapobiegnie linterom modyfikacji i wykluczy z statystyk języków
2. **Dokumentacja code review:** Dodać do `CLAUDE.md` pod sekcją Ikony:
   > Każde użycie `render_icon` w `<button>` MUSI mieć `$title` parameter (a11y)
3. **Lighthouse w CI** (later) — pilnowanie performance score
4. **Cache headers**: Sprawdzić `.htaccess` że `*.woff2` i `*.css` mają `Cache-Control: max-age=31536000` (1 rok)

---

## Verdict: PASS WITH CONDITIONS

**Bezpieczeństwo:** ✅ PASS (0 krytycznych, 0 poważnych)
**RODO:** ✅ PASS (zero third-party)
**A11y:** ⚠️ PASS WITH CONDITIONS (zależne od dyscypliny developera)
**Performance:** ✅ PASS (180KB jednorazowo + cache)

### Warunki przed merge
1. Helper `render_icon` musi być code-reviewed (whitelist + escape)
2. Wszystkie interaktywne `render_icon` w widokach mają `$title` lub `sr-only` w parent
3. `vendor/fontawesome/` w `.gitattributes` jako vendored
4. Atrybucja licencji w stopce lub `/about`

### Po merge
1. Manual smoke test wszystkich layoutów (App, Admin, Auth, Print)
2. Lighthouse run na 3 stronach (dashboard, /trees/{id}, /admin)
3. NetworkTab check: woff2 ładowane raz i cached
