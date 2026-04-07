# Kontekst: Global Admin Panel

## Wymagania (Discovery)

- **Przełączanie po projektach**: Impersonacja ("zaloguj jako") — admin wchodzi w sesję dowolnego użytkownika, może edytować jego drzewa, może z niej wyjść przez banner
- **Panel admina**: Lista użytkowników + statystyki globalne + blokada kont + audit log
- **Tworzenie admina**: Pierwszy — ręcznie przez SQL; kolejni — przez UI panel (przycisk "Mianuj adminem")
- **Audit log**: Pełna tabela `admin_logs` z akcjami: impersonate_start/end, block, unblock, promote, demote

## Kluczowe decyzje architektoniczne

### Dlaczego dedykowany AdminLayout zamiast AppLayout?
Panel admina ma inne UX niż aplikacja dla userów — sidebar zamiast topnav, inne kolory (slate-900), brak menu drzew. Osobny layout = czysta separacja.

### Dlaczego `is_admin` w sesji zamiast sprawdzania w DB przy każdym request?
Kurs trade-off: weryfikacja z DB przy każdym request = dodatkowe query. Dla MVP: `is_admin` w sesji + `Session::regenerate()` po promote/demote. Ryzyko okna (sesja aktywna po degradacji) jest akceptowalne.

### Dlaczego `_admin_user_id` jako prefix z podkreślnikiem?
Konwencja projektu — klucze systemowe mają prefix `_` (np. `_csrf_token`, `_created_at`, `_flash`). Odróżnia od kluczy aplikacji.

### Impersonacja — nie POST /admin/impersonate/{uid}?
POST wymaga CSRF — ważne dla bezpieczeństwa. GET `/admin/users/{uid}/impersonate` byłoby niebezpieczne (CSRF przez img tag).

## Istniejące wzorce wykorzystane

- `AuthMiddleware::handle()` → wzorzec dla `AdminMiddleware::handle()` (zwraca bool, redirect on fail)
- `Session::get('user_id')` → dodajemy `Session::get('is_admin')`
- `Session::regenerate(true)` → już istnieje w Session.php — używamy przy impersonacji
- `Router::group()` z middleware array → taki sam wzorzec jak `/dashboard` i `/trees`

## Stack techniczny (bez zmian)

PHP 8.2+ / PDO / MariaDB (Docker) / Tailwind CDN / Alpine.js / shadcn/ui tokens

## Zależności

Brak nowych paczek Composer.
