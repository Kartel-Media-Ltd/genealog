# Font Awesome — Checklist zadań

> Oznaczenia: `[ ]` do zrobienia, `[x]` ukończone, `[USER]` wymaga ręcznej akcji

---

## Status ogólny
- [x] Faza 1: Setup vendor + helper
- [x] Faza 2: Integracja CSS w layoutach
- [x] Faza 3: Migracja AppLayout
- [x] Faza 4: Migracja toolbara show.php
- [x] Faza 5: Migracja AdminLayout sidebar
- [x] Faza 6: Migracja atomów
- [x] Faza 7: Migracja stron
- [x] Faza 8: Verification + dokumentacja

**Status: UKOŃCZONE** ✅ (141 wywołań render_icon(), 0 inline SVG)

---

## Faza 1: Setup vendor + helper ✅

- [x] `[USER]` Pobrano Font Awesome 6.7.2 Free Web
- [x] `public/vendor/fontawesome/{css,webfonts}` — istnieje
- [x] `all.min.css`, fonty woff2/ttf, LICENSE.txt — skopiowane
- [x] `public/vendor/fontawesome/README.md` — manifest z wersją
- [x] `src/views/atoms/icon.php` z `render_icon()` — istnieje
- [x] Syntax OK: `php -l src/views/atoms/icon.php`
- [ ] `[USER]` Sprawdzić HTTP 200 w przeglądarce: `curl -I http://localhost:8002/vendor/fontawesome/css/all.min.css`

---

## Faza 2: Integracja CSS w layoutach ✅

- [x] `AppLayout.php` — `<link>` do FA CSS + preload woff2
- [x] `AdminLayout.php` — `<link>` do FA CSS
- [x] `AuthLayout.php` — `<link>` do FA CSS
- [x] `PrintLayout.php` — `<link>` do FA CSS

---

## Faza 3: Migracja AppLayout ✅

- [x] Bell notifications icon → `render_icon('bell', 'solid', 'h-5 w-5')`
- [x] Mobile hamburger → `render_icon('bars', ...)`
- [x] User dropdown chevron → `render_icon('chevron-down', ...)`
- [x] Logout icon → `render_icon('arrow-right-from-bracket', ...)`
- [x] Settings menuitem → `render_icon('gear', ...)`
- [x] Profile menuitem → `render_icon('user', ...)`
- [x] Nav links — ikony przed labelem

---

## Faza 4: Migracja toolbara show.php ✅

- [x] Drukuj → `fa-print`
- [x] Dodaj osobę → `fa-user-plus`
- [x] Osoby → `fa-users`
- [x] Import/Export → `fa-file-import`
- [x] Dostęp → `fa-user-group`
- [x] Odkrywanie → `fa-magnifying-glass`
- [x] Ustawienia → `fa-gear`

---

## Faza 5: Migracja AdminLayout sidebar ✅

- [x] Dashboard → `fa-gauge-high` + `fa-fw`
- [x] Users → `fa-users` + `fa-fw`
- [x] Trees → `fa-tree` + `fa-fw`
- [x] Logs → `fa-list-ul` + `fa-fw`
- [x] Top bar logout → `fa-arrow-right-from-bracket`

---

## Faza 6: Migracja atomów ✅

- [x] `alert.php` — 4 typy z ikonami (circle-info, circle-check, triangle-exclamation, circle-exclamation) + dismiss (xmark)
- [x] `spinner.php` — `fa-spinner` + `fa-spin`
- [x] `button.php` — parametr `$icon` (opcjonalny)
- [x] `avatar.php` — fallback `fa-user`

---

## Faza 7: Migracja stron ✅

- [x] `persons-print.php` — `fa-print`, `fa-xmark`
- [x] `persons/show.php` — `fa-pen-to-square`, `fa-trash`, `fa-camera`
- [x] `persons/index.php` — `fa-magnifying-glass`, sort arrows, view toggle
- [x] `trees/members.php` — `fa-paper-plane`, `fa-user-minus`, `fa-shield-halved`
- [x] `admin/users.php` — `fa-ban`, `fa-circle-check`, `fa-arrow-up/down`, `fa-user-secret`
- [x] `admin/user-detail.php` — jak users.php
- [x] `trees/gedcom.php` — `fa-upload`, `fa-download`
- [x] `auth/login.php` + `register.php` — `fa-envelope`, `fa-lock`, przyciski

---

## Faza 8: Verification + dokumentacja ✅

- [x] `grep -rn 'render_icon(' src/views/` → 141 wywołań
- [x] `grep -rn '<svg xmlns="http' src/views/` → 0 wyników (oprócz JS)
- [x] Atrybucja dodana do `AppLayout.php` footer: "Icons: Font Awesome Free (CC BY 4.0)"
- [x] Sekcja "Ikony" w `CLAUDE.md` — istnieje
- [x] PHPUnit: 71/71 ✅
- [ ] `[USER]` Testy manualne w przeglądarce (nav, toolbar, admin, print, alerts)
- [ ] `[USER]` Network tab: sprawdź all.min.css ~28KB + woff2 ~75KB
