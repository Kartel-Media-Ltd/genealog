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

---

## Code Review — 2026-04-07

Review przeprowadzony po wdrożeniu faz 1-4.

**Wynik:** 5 blocking, 9 important, 7 nit, 7 suggestions.

**Status: panel niefunkcjonalny do czasu fix blockerów.**

**Najważniejsze ustalenia:**
1. **`Csrf::token()` nie istnieje** — fatal error na `/admin/users` i `/admin/users/{uid}`. Powinno być `Csrf::getToken()`.
2. **`AdminMiddleware` blokuje exit-impersonacji** — endpoint `/admin/impersonate/exit` w grupie `/admin` chronionej AdminMiddleware, który wymaga `is_admin=true`. Podczas impersonacji `is_admin=false`, więc user nie wyjdzie z impersonacji. Wymaga refaktoringu routingu.
3. **CSRF field name mismatch** — formularze w `user-detail.php` używają `name="csrf_token"`, ale `Request::verifyCsrf()` szuka `_csrf_token`. Każde naciśnięcie akcji → 403.
4. **`LIMIT ?` z natywnymi prepares** — PDO przesyła jako string, MySQL odrzuca. `findAllUsers/findAllTrees/findLogs` rzucą `PDOException`. Fix: interpolacja `(int)$limit`.
5. **Brak weryfikacji `is_admin` z DB** — privilege escalation: zdegradowany admin zachowuje uprawnienia w sesji aż do wylogowania. Plan wymóg #1 nie został spełniony.

**Co działa dobrze:**
- Spójna warstwa Repository/Service/Controller
- Self-action guards (admin nie zablokuje siebie)
- Session::regenerate(true) na start I exit impersonacji
- Audit log wszystkich wrażliwych akcji z metadanymi
- ON DELETE RESTRICT na admin_logs.admin_id
- Banner impersonacji w AppLayout

Pełny raport: `dev/active/global-admin/review-global-admin.md`
