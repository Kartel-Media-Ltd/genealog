# Code Review — Global Admin Panel

**Data:** 2026-04-07
**Branch:** feature/php-scaffold

## Podsumowanie

Feature ma poprawnie zaplanowaną architekturę (`Repository → Service → Controller → Middleware`), spójny audit log, większość założeń bezpieczeństwa (CSRF, prepared statements, blokada self-actions, regenerate session na impersonacji). **Niestety panel admina jest w obecnej formie niefunkcjonalny** — co najmniej 4 krytyczne błędy uniemożliwiają używanie go w praktyce: brak metody `Csrf::token()`, niezgodność nazw pól CSRF (`csrf_token` vs `_csrf_token`), `AdminMiddleware` blokuje endpoint exit-impersonacji, oraz `LIMIT ?` z natywnymi prepares wywala `PDOException`. Dodatkowo brakuje weryfikacji `is_admin` z DB przed wrażliwymi akcjami (privilege escalation po demote).

---

## Problemy

### 🔴 Blocking

- **`src/views/pages/admin/users.php:6` + `src/views/pages/admin/user-detail.php:6`** — Wywołanie `Csrf::token()` powoduje fatal error: metoda nie istnieje (są `generate()`, `verify()`, `getToken()`, `hiddenInput()`). Strony nie wyrenderują się. **Fix:** zmienić na `Csrf::getToken()` lub dodać alias.

- **`src/Middleware/AdminMiddleware.php` ↔ `public/index.php:200`** — Endpoint `POST /admin/impersonate/exit` jest w grupie `/admin` chronionej `AdminMiddleware`. Podczas impersonacji middleware odrzuca request → użytkownik utknie w impersonacji. **Fix:** wyciągnąć endpoint poza grupę `/admin` (osobna definicja `$router->post('/admin/impersonate/exit', ...)` z samym AuthMiddleware), lub dodać whitelist w middleware.

- **`src/views/pages/admin/user-detail.php:63,72,84,93,104`** — Formularze POST używają `name="csrf_token"`, ale `Request::verifyCsrf()` szuka pola `_csrf_token`. Każde naciśnięcie „Zablokuj/Mianuj/Impersonuj" → 403. **Fix:** zmienić nazwy pól na `_csrf_token` (lub użyć `Csrf::hiddenInput()`).

- **`src/Repositories/AdminRepository.php:37,63,81`** — `LIMIT ? OFFSET ?` z bind parameters; PDO ma `EMULATE_PREPARES => false`, MySQL odrzuca `LIMIT '50'` jako string. `findAllUsers`, `findAllTrees`, `findLogs` rzucą `PDOException`. **Fix:** użyć interpolacji `'LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset` (jak w `TreeRepository:165`).

- **`src/Services/AdminService.php:96-131` (promote/demote/block/unblock/impersonate)** — Brak weryfikacji `is_admin` wywołującego z DB. Plan wymóg #1: „is_admin weryfikowany z DB". Privilege escalation: zdegradowany admin zachowuje uprawnienia w sesji aż do wylogowania. **Fix:** w każdej wrażliwej akcji `findById($adminId)` + sprawdzić `isAdmin && !isBlocked`.

### 🟠 Important

- **`src/Services/AdminService.php:51-61` `exitImpersonate`** — Nie sprawdza `$admin->isAdmin` przed przywróceniem sesji. Jeśli admin zdegradowany podczas impersonacji → po exit dostaje `is_admin = true` na sztywno. **Fix:** weryfikować z DB i destroy session przy braku uprawnień.

- **`src/Controllers/AdminController.php:188`** — `Session::set('is_admin', true)` bezwarunkowo na exit. Powinno być `Session::set('is_admin', $admin->isAdmin)`.

- **`src/Repositories/AdminRepository.php:24-39` `findAllUsers`** — `LIKE '%search%'` bez escape `%`/`_` w `$search` (wildcard injection — nie SQLi, ale pomija filtr). **Fix:** `addcslashes($search, '%_\\')`.

- **`src/Controllers/AdminController.php:72`** — `'title' => 'Użytkownik: ' . htmlspecialchars($user['name'])`, a `AdminLayout.php` znów eskejpuje `$title` → **double escape** (`<` → `&amp;lt;`). **Fix:** usunąć `htmlspecialchars` z controllera.

- **`src/views/pages/admin/user-detail.php:62-110`** — Można promować zablokowane konto. **Fix:** `AdminService::promote` rzuca wyjątek gdy `$target->isBlocked`.

- **`src/Controllers/AdminController.php:80-167`** — Brak walidacji formatu UUID w `$uid`. **Fix:** regex check lub `strlen() === 36`.

- **`src/Services/AdminService.php` — block/unblock/promote/demote** — Brak idempotencji (powtarzalne wywołania logują duplikaty). Niski priorytet, ale śmieci w `admin_logs`.

- **`src/Repositories/AdminRepository.php:30,46`** — Lista pokazuje też `is_active = 0`, ale brak oznaczenia statusu w UI. **Fix:** dodać kolumnę „Status: Aktywny/Nieaktywny/Zablokowany".

### 🟡 Nit

- **`migrations/004_admin.sql`** — `meta TEXT` powinno być `JSON` (nie zmieniać po deploy).
- Brak indeksu `(target_type, target_id)` w `admin_logs`.
- **`src/views/templates/AdminLayout.php:61`** — Logika klasy aktywnej dla `/admin` jest niejasna; lepiej `$currentPath === '/admin'` (exact match).
- **`src/views/pages/admin/users.php:8`** — `x-data="{ search: ... }"` deklaruje zmienną nieużywaną w widoku.
- **`src/views/pages/admin/logs.php:67-71`** — `array_map(fn($k,$v) => "$k:$v", ...)` może rzucić błąd przy zagnieżdżonych tablicach. Defensive: `is_string($v) ? $v : json_encode($v)`.
- **`src/Services/AdminService.php:145-151`** — `generateUuid()` duplikat z `AuthService`. Wyciągnąć do `App\Core\Uuid::v4()`.
- **`src/Middleware/AdminMiddleware.php:14`** — Sygnatura `: bool` ale w praktyce zawsze `redirect()` lub `true`.

### 🔵 Suggestions

- Audit log dla view-actions (`'admin_view_user'`) — RODO compliance.
- Filtry w `/admin/logs` (po action/admin_id/dacie).
- Rate limit `/admin/users/{uid}/impersonate`.
- Session invalidation on demote/block (revoked_user_ids).
- Notify impersonowanego użytkownika po fakcie (RODO Art. 5(1)(a)).
- Test e2e/PHPUnit dla full impersonate cycle.
- Wszędzie `Csrf::hiddenInput()` zamiast ręcznych pól (eliminuje bug field name).

---

## Co działa dobrze

- Spójna warstwa Repository/Service/Controller z jasną odpowiedzialnością
- Self-action guards: block/demote/impersonate/promote sprawdzają `$adminId === $targetUserId`
- `Session::regenerate(true)` na start I exit impersonacji (best practice)
- CSRF na każdym POST endpointie (`verifyCsrf()` jako pierwsza instrukcja) — niestety zniweczone bugiem field name
- Audit log wszystkich wrażliwych akcji z metadanymi (target email/name)
- Cleanup `_admin_user_*` przy exit
- Banner impersonacji w AppLayout (czerwony pasek + form exit)
- `admin_logs.admin_id` z `ON DELETE RESTRICT` — historia logów nie znika
- Wszystkie zapytania używają prepared statements
- `htmlspecialchars` na wszystkich danych w widokach (poza double-escape w title)
- JOIN+COUNT w `findAllUsers/findAllTrees` zamiast N+1
- Komentarz o kolejności tras `/impersonate/exit` przed `/users/{uid}`

---

## Statystyki

| Kategoria | Liczba |
|-----------|--------|
| Plików reviewed | 16 |
| 🔴 Blocking | 5 |
| 🟠 Important | 9 |
| 🟡 Nit | 7 |
| 🔵 Suggestions | 7 |

**Gotowość do merge: NIE.** 4 z 5 blockerów to fix-and-test (~30-60 min). Blocker #2 (AdminMiddleware blokuje exit) wymaga refaktoringu routingu. Po naprawie blockerów dodać minimum 1 test e2e dla full impersonate cycle.
