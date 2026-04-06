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
