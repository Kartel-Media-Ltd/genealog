# Font Awesome — Kontekst i decyzje

## Discovery — wymagania usera

**Z prompta:** "zaimplementuj mi biblioteke font-awesome ikony, i chce uzywac zamiast SVG te ikony gdzie bede podawal fa-*. chce, aby to bylo lokalnie w vendorze, nie pobierane z internetu. wtedy nalezy w menu podmienic te ikonki"

### Interpretacja
1. **Library:** Font Awesome (nieokreślona wersja → wybieramy najnowszą stabilną Free)
2. **Składnia:** `fa-*` → standardowa konwencja FA (`fa-user`, `fa-house`, etc.)
3. **Hosting:** lokalnie w `vendor/` → spójne z `public/vendor/` (alpine, d3, tailwind)
4. **Scope:** "w menu podmienic" → minimum AppLayout nav, ale logiczne rozszerzenie na cały toolbar + atomy

### Założenia
- User chce **DRY** (helper zamiast duplikowania `<i class="fa-solid fa-...">`)
- User chce **offline-friendly** (zero CDN, zero tracking)
- User akceptuje migrację wszystkich SVG (nie tylko nawigacji) — naturalny zakres

---

## Decyzje architektoniczne

### Decyzja 1: FA 6 Free (nie 7, nie Pro)

**Wybór:** Font Awesome 6.7 Free (najnowsza stabilna 6.x z 2025).

**Alternatywy:**
| Opcja | Plus | Minus | Decyzja |
|---|---|---|---|
| FA 6 Free 6.7+ | Stabilne, dojrzałe, 2000+ ikon, MIT/SIL OFL | Brak niektórych nowych ikon | ✅ WYBRANO |
| FA 7.x (świeże) | Najnowsze, ulepszone duotone | Wczesna wersja, mniej testów | ❌ Ryzyko dla MVP |
| FA Pro 6/7 | 16000+ ikon, Sharp variants, Light/Thin | $99/rok, wymaga konta | ❌ Płatne — przeciwko duchowi MVP |
| Lucide Icons | Open-source, 1500+, używane przez shadcn/ui | Konwencja `lucide-*` (nie `fa-*` jak chciał user) | ❌ Niezgodne z wymaganiem |
| Heroicons | 300+, hand-crafted | Mała liczba ikon | ❌ Za mało |

### Decyzja 2: Webfonts (nie SVG, nie SVG-with-JS)

**Wybór:** Webfonts CSS (`all.min.css` + `.woff2` files).

**Alternatywy:**
| Format | Plus | Minus | Decyzja |
|---|---|---|---|
| **Webfonts** | Pure CSS, 0 JS, działa wszędzie, najszybsze | Trudniejsze stylowanie pojedynczych ikon | ✅ WYBRANO |
| SVG inline | Pełna kontrola CSS per-ikona | Każda ikona to osobny request lub większy CSS | ❌ Cel migracji to ELIMINACJA inline SVG |
| SVG with JS | Lazy loading, drobne dynamic | Wymaga JS, większy bundle, FOIC | ❌ Cięższe i wolniejsze |
| SVG sprites | Małe requesty | Wymaga preprocessing (sprite sheet) | ❌ Złożoność bez korzyści |

### Decyzja 3: Tylko Solid + Brands (bez Regular)

**Wybór:** `fa-solid` (default) + `fa-brands`. Bez Regular.

**Powód:**
- FA Free Regular ma tylko 163 ikony (większość = duble Solid z cieńszą kreską)
- Solid pokrywa 95% potrzeb aplikacji
- Bundle bez Regular = -75KB
- Można dodać Regular później jeśli pojawi się potrzeba

### Decyzja 4: Helper `render_icon()` zamiast bezpośrednich `<i>`

**Wybór:** Atom `src/views/atoms/icon.php` z funkcją `render_icon($name, $variant, $extra, $title)`.

**Korzyści:**
1. **DRY** — jedna prawda, łatwa zmiana całej aplikacji (np. dodanie domyślnego `aria-hidden`)
2. **Bezpieczeństwo** — `htmlspecialchars` na nazwie ikony chroni przed wstrzyknięciem
3. **Whitelist wariantów** — tylko `solid|brands|regular` dozwolone
4. **Konwencja** — atomic design, spójne z innymi atomami (`button.php`, `input.php`, `avatar.php`)
5. **Refactor-friendly** — Grep `render_icon` znajduje wszystkie użycia

**Alternatywa odrzucona:** Bezpośrednie `<i class="fa-solid fa-user">` w widokach
- Wymagałoby Grep+sed na każdej zmianie
- Brak walidacji nazw ikon
- Nie pasuje do atomic design

### Decyzja 5: Lokalizacja `public/vendor/fontawesome/`

**Wybór:** Spójne z istniejącymi `public/vendor/{alpine,d3,tailwind}.min.js`.

**Struktura:**
```
public/vendor/fontawesome/
├── css/all.min.css
├── webfonts/fa-solid-900.{woff2,ttf}
├── webfonts/fa-brands-400.{woff2,ttf}
├── LICENSE.txt
└── README.md (skrócony manifest, źródło, wersja)
```

**Alternatywa odrzucona:** `public/css/fontawesome/`
- Niespójne z `vendor/` dla Alpine/D3/Tailwind
- Sugeruje że to nasz CSS, a to third-party

### Decyzja 6: Preload `fa-solid-900.woff2`

**Wybór:** Dodać `<link rel="preload" href="..." as="font" type="font/woff2" crossorigin>` w layoutach.

**Powód:**
- Eliminuje FOIT (Flash of Invisible Text) — przeglądarka zaczyna ładować font przed parsem CSS
- Oszczędność ~100ms na pierwszym renderze
- Brands NIE preloadowany (rzadko używany, można czekać)

### Decyzja 7: Migracja "gradual w jednym sprincie"

**Wybór:** Wszystkie 7 faz w jednej sesji `/ultra-workaholic`, ale po pliku/sekcji (nie wszystko naraz).

**Powód:**
- Brak data model changes → minimalne ryzyko regresji
- Każdy plik testowany od razu po migracji
- Helper + layouty muszą być first (Faza 1-2), inaczej nic nie działa

**Alternatywa odrzucona:** Stopniowa migracja przez kilka tygodni
- Pozostawia mieszankę inline SVG + FA przez długi czas (chaos)
- User chce "podmienić" — sugeruje pełną migrację

---

## Mapowanie SVG → FA (referencyjne)

Pełna tabela w `font-awesome-zadania.md` przy każdej fazie.

### Wzorce nazewnictwa (FA 6 vs starsze)

FA 6 zmieniło nazwy niektórych ikon. Najczęstsze pułapki:
| Stara nazwa (FA 5) | Nowa (FA 6) |
|---|---|
| `fa-search` | `fa-magnifying-glass` |
| `fa-trash-alt` | `fa-trash` |
| `fa-edit` | `fa-pen-to-square` |
| `fa-times` | `fa-xmark` |
| `fa-cog` | `fa-gear` |
| `fa-save` | `fa-floppy-disk` |
| `fa-tachometer-alt` | `fa-gauge-high` |
| `fa-sign-out-alt` | `fa-arrow-right-from-bracket` |
| `fa-info-circle` | `fa-circle-info` |
| `fa-check-circle` | `fa-circle-check` |
| `fa-exclamation-triangle` | `fa-triangle-exclamation` |
| `fa-exclamation-circle` | `fa-circle-exclamation` |
| `fa-home` | `fa-house` |

### Aliasy zachowywane
FA 6 zachowuje aliasy starych nazw przez `fa-icon-aliases.json`, ale nowe nazwy są **zalecane**. Używamy nowych w całym kodzie.

---

## Rozważania bezpieczeństwa

### Dlaczego "lokalnie w vendorze"?

User explicit: "nie pobierane z internetu". Powody:

1. **RODO Art. 25 (privacy by design)** — żadne IP użytkownika nie wycieka do CDN third-party (Cloudflare, Fastly, fontawesome.com)
2. **Zero tracking** — FA Kit (kit.fontawesome.com) loguje requesty, które ikony są pobierane (analytics)
3. **Offline development** — działa bez internetu (genealog może być deployowany na intranecie / NAS)
4. **No supply chain attack** — nikt nie podmienia naszego CSS/font na malicious wersję
5. **Cache stable** — nie zależymy od CDN downtime
6. **CSP simplicity** — `font-src 'self'` wystarcza, brak `cdnjs.cloudflare.com`

### Atrybucja licencji

FA Free wymaga atrybucji **dla ikon** (CC BY 4.0). Code (CSS) i fonts (SIL OFL) NIE wymagają.

**Plan atrybucji:**
- Stopka `AppLayout.php`: dodać małą notatkę "Icons by Font Awesome Free (CC BY 4.0)"
- Lub dedykowana strona `/about` lub `/credits` z listą wszystkich third-party (FA, D3, Alpine, Tailwind)

---

## Pytania otwarte

1. **Czy user chce widoczną atrybucję w stopce?** Decyzja: **TAK** — drobny napis w footerze (jak D3.js wymaga w wielu projektach). Można też przenieść do `/about`.
2. **Czy migrować ikony w `tree-visualizer.js`?** Decyzja: **NIE** — D3 generuje SVG dynamicznie, FA webfont nie pasuje (potrzeba `fa-icon` jako `<text>` z Unicode codepoint, co komplikuje render). Pozostaje na osobny plan.
3. **Czy używać `fa-fw` (fixed width) w nawigacji?** Decyzja: **TAK** — nawigacja boczna admin layout, gdzie ikony muszą być wyrównane w kolumnie. `fa-fw` daje stałą szerokość.

---

## Linki referencyjne

- **Font Awesome 6 Docs:** https://docs.fontawesome.com/v6/
- **Lista ikon Free:** https://fontawesome.com/search?o=r&m=free&s=solid
- **Self-hosting guide:** https://docs.fontawesome.com/v6/web/setup/host-yourself/webfonts
- **GitHub release:** https://github.com/FortAwesome/Font-Awesome/releases
- **Licencje:** https://fontawesome.com/license/free
