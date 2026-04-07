# Kontekst: Inicjalizacja struktury PHP — Genealog

## Discovery — decyzje usera
- **Auth w scopie MVP:** Pełny scaffold + Auth (register/login/logout + AuthMiddleware)
- **ENV handling:** vlucas/phpdotenv (walidacja wymaganych kluczy, wyjątek przy braku)
- **Serwer:** Apache + .htaccess (mod_rewrite)
- **UI Atoms:** Wszystkie shadcn komponenty od razu (Button, Input, Label, Card, Badge, Avatar, Alert, Spinner)

## Kluczowe decyzje architektoniczne

### Response::view() z output buffering
Layout musi "owrapować" content strony. Wzorzec:
```php
ob_start();
extract($data);
include $viewPath;
$content = ob_get_clean();
extract(['content' => $content]);
include $layoutPath;
exit;
```
Eliminuje dziedziczenie widoków i `$this` w szablonach.

### Database Singleton
Jedno połączenie PDO per request w PHP-FPM. Docelowo: PHP-DI jako DI container bez zmiany interfejsu `Database`.

### UUID po stronie PHP
`sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', ...)` lub `ramsey/uuid`.
Powód: batch inserts, GEDCOM import, ochrona przed ID enumeration.

### Router group() z middleware array
Stackowalny: `[[$authMw, 'handle'], [$treeMw, 'handle']]`. Kompatybilny z PSR-15 na przyszłość.

### shadcn jako wzorzec wizualny (nie biblioteka npm)
CSS variables z shadcn skopiowane do `globals.css`. Komponenty reimplementowane jako PHP partials z Tailwind. Alpine.js zastępuje Radix UI dla interaktywności.

## Zewnętrzne zależności Composer
| Pakiet | Wersja | Cel |
|--------|--------|-----|
| vlucas/phpdotenv | ^5.6 | Ładowanie .env.local z walidacją |
| fisharebest/gedcom | ^3.0 | Parser GEDCOM (faza GEDCOM) |
| phpunit/phpunit | ^11.0 (dev) | Testy jednostkowe |

## Uwagi implementacyjne
- `Session::start()` musi być wywołany PRZED jakimkolwiek `echo` lub `header()`
- `Csrf::verify()` w każdym POST/PUT/DELETE — **bez wyjątków**
- `resolveViewPath()` w Response: `realpath()` + assert że ścieżka zaczyna się od VIEWS_PATH
- Rate limit check PRZED weryfikacją hasła (nie ujawniaj "złe hasło" vs "zablokowany")

---

## Code Review — 2026-04-07

Review fundamentu po wdrożeniu wszystkich faz scaffolda + kolejnych feature'ów.

**Wynik:** 6 blocking, 9 important, 9 nit, 8 suggestions.

**Pre-existing bugi widoków scaffolda (blockery):**
1. **CSRF field name mismatch** w 5 miejscach (`csrf_token` zamiast `_csrf_token`):
   - `AppLayout.php:225-227, 282-284` — logout form (desktop+mobile)
   - `pages/invite/pending.php:67` — akcept zaproszenia
   - `pages/login.php`, `pages/register.php` (dead pages, root level)
   - **Rezultat:** każde wylogowanie i akcept zaproszenia → 403
2. **AdminLayout.php:117** — `foreach ($flash as $type => $msg)` iteruje tablicę `['type'=>...,'message'=>...]` → renderuje 2 alerty z mangled content (każdy admin flash widać 2x)
3. **`tests/bootstrap.php:20`** — `'/Views'` (uppercase), katalog to `src/views` — działa tylko na macOS APFS
4. **Open redirect w `Response::redirect`** — `str_starts_with($url, SITE_URL)` przepuszcza `http://localhost:8002.attacker.com`
5. **`form-group.php:48`** — `render_input` ze złą sygnaturą (dead code, ale bug-trap)

**Pre-existing fail w testach:**
- `AuthServiceTest::testRegisterSuccess` — `new User(...)` z 7 argumentami; konstruktor wymaga 9 (po wprowadzeniu admin/blocked)

**Dead code (~1500 linii):**
- `views/pages/{login,register,dashboard,profile,settings}.php` (root level) — duplikaty zastąpione wersjami w podkatalogach
- `views/organisms/site-header.php` (222 linie) — helper nigdzie nie wołany; AppLayout inlinuje header

**Architektoniczne braki:**
- Brak rate limitu na `register()` (tylko `login`)
- Brak HSTS w security headers
- Brak DI containera (front controller rozrasta się liniowo)
- Brak `phpstan`/`psalm` w dev-deps
- Brak testów `Session::flash`/`Request::verifyCsrf` (źródło 1/3 dzisiejszych bugów)

**Co działa dobrze:**
- Cryptographically secure CSRF (random_bytes(32), hash_equals, rotacja)
- PDO bez konkatenacji, EMULATE_PREPARES=false
- Bcrypt cost 12, path traversal protection
- Session security (httponly, samesite=Strict, cookie_secure, regeneracja)
- Rate limiting pre-password-verify
- Atomic design + WCAG

Pełny raport: `dev/active/php-scaffold/review-php-scaffold.md`
