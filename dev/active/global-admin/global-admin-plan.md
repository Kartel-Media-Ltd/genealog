# Plan: Global Admin Panel

## Kontekst
Genealog nie ma koncepcji globalnego administratora. Każdy user zarządza tylko swoimi drzewami. Cel: dodać rolę `is_admin` + dedykowany panel `/admin/*` z listą użytkowników, statystykami, blokadą kont, impersonacją ("zaloguj jako") i pełnym audit logiem.

**Klasyfikacja: STANDARD** — CRUD + niestandardowy element (session impersonation).

---

## Schemat bazy — Migration 004

```sql
-- migrations/004_admin.sql

ALTER TABLE users
    ADD COLUMN is_admin   TINYINT(1) NOT NULL DEFAULT 0 AFTER email_notifications,
    ADD COLUMN is_blocked  TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;

CREATE TABLE admin_logs (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    admin_id    CHAR(36)     NOT NULL,
    action      VARCHAR(50)  NOT NULL,          -- 'impersonate_start','impersonate_end','block','unblock','promote','demote'
    target_type VARCHAR(30)  NULL,              -- 'user','tree'
    target_id   CHAR(36)     NULL,
    meta        JSON         NULL,              -- np. {"target_email":"x@x.pl"}
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_admin_logs_admin_id  (admin_id),
    INDEX idx_admin_logs_created   (created_at DESC),
    INDEX idx_admin_logs_action    (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**USER_ACTION:** `source .env.local && docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/004_admin.sql`

**Tworzenie pierwszego admina (SQL):**
```sql
UPDATE users SET is_admin = 1 WHERE email = 'twoj@email.pl';
```

---

## Architektura kodu

### Nowe pliki

```
src/Models/User.php                          — dodaj pola isAdmin, isBlocked
src/Repositories/UserRepository.php          — dodaj metody admina
src/Repositories/AdminRepository.php         — statystyki globalne, lista users, logi
src/Services/AdminService.php                — impersonacja, blokada, mianowanie
src/Middleware/AdminMiddleware.php            — sprawdza is_admin w sesji
src/Controllers/AdminController.php          — wszystkie endpointy /admin/*
src/Controllers/AuthController.php           — zaktualizuj: is_admin + is_blocked w sesji
src/views/templates/AdminLayout.php          — dedykowany layout (ciemny sidebar)
src/views/pages/admin/dashboard.php          — statystyki + ostatnie logi
src/views/pages/admin/users.php              — tabela użytkowników z search
src/views/pages/admin/user-detail.php        — karta użytkownika + akcje
src/views/pages/admin/trees.php              — lista wszystkich drzew
src/views/pages/admin/logs.php               — tabela audit log
```

### Zmiany w istniejących plikach

| Plik | Zmiana |
|------|--------|
| `src/Models/User.php` | Dodaj `bool $isAdmin`, `bool $isBlocked` + zaktualizuj `fromArray()` |
| `src/Repositories/UserRepository.php` | Dodaj `findAllForAdmin()`, `setAdmin()`, `setBlocked()` |
| `src/Controllers/AuthController.php` | W `processLogin()`: sprawdź `is_blocked` → error; ustaw `Session::set('is_admin', ...)` |
| `public/index.php` | Nowe zależności + routing `/admin/*` |

---

## Routing

```
GET  /admin                              → AdminController::dashboard
GET  /admin/users                        → AdminController::users
GET  /admin/users/{uid}                  → AdminController::userDetail
POST /admin/users/{uid}/block            → AdminController::block
POST /admin/users/{uid}/unblock          → AdminController::unblock
POST /admin/users/{uid}/promote          → AdminController::promote
POST /admin/users/{uid}/demote           → AdminController::demote
POST /admin/users/{uid}/impersonate      → AdminController::impersonate
POST /admin/impersonate/exit             → AdminController::exitImpersonate
GET  /admin/trees                        → AdminController::trees
GET  /admin/logs                         → AdminController::logs
```

Wszystkie trasy w grupie z `AdminMiddleware`.

---

## Impersonacja — mechanizm sesji

### Start impersonacji (POST /admin/users/{uid}/impersonate)
```php
// 1. Zaloguj akcję
$adminService->logAction($adminId, 'impersonate_start', 'user', $targetUserId, ['target_email' => $target->email]);

// 2. Zapisz dane admina w sesji
Session::set('_admin_user_id',    $adminId);
Session::set('_admin_user_name',  $adminName);
Session::set('_admin_user_email', $adminEmail);

// 3. Podmień sesję na użytkownika
Session::set('user_id',    $target->id);
Session::set('user_name',  $target->name);
Session::set('user_email', $target->email);
Session::set('is_admin',   false);   // admin nie jest adminem gdy impersonuje

// 4. Regeneruj ID sesji (security)
Session::regenerate(true);

// 5. Redirect do /dashboard
```

### Exit impersonacji (POST /admin/impersonate/exit)
```php
// 1. Pobierz dane admina z sesji
$adminId    = Session::get('_admin_user_id');
$adminName  = Session::get('_admin_user_name');
$adminEmail = Session::get('_admin_user_email');

// 2. Zaloguj akcję
$adminService->logAction($adminId, 'impersonate_end', 'user', Session::get('user_id'));

// 3. Przywróć sesję admina
Session::set('user_id',    $adminId);
Session::set('user_name',  $adminName);
Session::set('user_email', $adminEmail);
Session::set('is_admin',   true);
Session::delete('_admin_user_id');
Session::delete('_admin_user_name');
Session::delete('_admin_user_email');

// 4. Regeneruj ID sesji
Session::regenerate(true);

// 5. Redirect do /admin
```

### Banner impersonacji (w AppLayout.php)
```php
<?php if (Session::has('_admin_user_id')): ?>
<div class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3
            rounded-full bg-destructive text-destructive-foreground px-4 py-2 shadow-lg text-sm font-medium">
    <span>Impersonujesz: <?= htmlspecialchars(Session::get('user_name')) ?></span>
    <form method="POST" action="/admin/impersonate/exit">
        <?= Csrf::hiddenInput() ?>
        <button type="submit" class="underline hover:no-underline">Wyjdź</button>
    </form>
</div>
<?php endif; ?>
```

---

## AdminMiddleware

```php
class AdminMiddleware
{
    public function handle(Request $request): bool
    {
        // Nie działa gdy impersonuje (is_admin=false)
        if (!Session::get('is_admin')) {
            $this->response->withFlash('error', 'Brak uprawnień.')->redirect('/dashboard');
        }
        return true;
    }
}
```

---

## AdminRepository — kluczowe zapytania

```php
// Statystyki globalne
public function getStats(): array
{
    return [
        'users'         => $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE is_blocked = 0')['cnt'],
        'blocked_users' => $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE is_blocked = 1')['cnt'],
        'trees'         => $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM trees')['cnt'],
        'persons'       => $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM persons')['cnt'],
        'relationships' => $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM relationships')['cnt'],
    ];
}

// Lista użytkowników z liczbą drzew
public function findAllUsers(string $search = '', int $limit = 50, int $offset = 0): array
{
    // SELECT u.*, COUNT(t.id) AS trees_count FROM users u LEFT JOIN trees t ON t.owner_id = u.id
    // WHERE u.name LIKE ? OR u.email LIKE ?
    // GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?
}

// Audit log z paginacją
public function findLogs(int $limit = 50, int $offset = 0): array
{
    // SELECT al.*, u.name AS admin_name, u.email AS admin_email
    // FROM admin_logs al JOIN users u ON u.id = al.admin_id
    // ORDER BY al.created_at DESC LIMIT ? OFFSET ?
}
```

---

## AdminService

```php
class AdminService
{
    public function impersonate(string $adminId, string $targetUserId): User
    {
        // Walidacja: cel nie może być adminem
        $target = $this->userRepo->findById($targetUserId);
        if ($target === null) throw new \InvalidArgumentException('Użytkownik nie istnieje.');
        if ($target->isAdmin) throw new \InvalidArgumentException('Nie można impersonować admina.');
        if ($target->isBlocked) throw new \InvalidArgumentException('Konto jest zablokowane.');

        $this->logAction($adminId, 'impersonate_start', 'user', $targetUserId, ['target_email' => $target->email]);
        return $target;
    }

    public function block(string $adminId, string $targetUserId): void
    {
        $this->userRepo->setBlocked($targetUserId, true);
        $this->logAction($adminId, 'block', 'user', $targetUserId);
    }

    public function promote(string $adminId, string $targetUserId): void
    {
        $this->userRepo->setAdmin($targetUserId, true);
        $this->logAction($adminId, 'promote', 'user', $targetUserId);
    }

    public function logAction(string $adminId, string $action, ?string $targetType, ?string $targetId, array $meta = []): void
    {
        $id = $this->generateUuid();
        $this->adminRepo->createLog($id, $adminId, $action, $targetType, $targetId, empty($meta) ? null : json_encode($meta));
    }
}
```

---

## AuthController — zmiany przy logowaniu

```php
// W processLogin(), po weryfikacji hasła:
$user = ... // User z UserRepository

// Sprawdź blokadę
if ($user->isBlocked) {
    throw new \InvalidArgumentException('Twoje konto zostało zablokowane. Skontaktuj się z administratorem.');
}

// Ustaw sesję (dodaj is_admin)
Session::set('user_id',    $user->id);
Session::set('user_name',  $user->name);
Session::set('user_email', $user->email);
Session::set('is_admin',   $user->isAdmin);
```

---

## Bezpieczeństwo

| Ryzyko | Mitigacja |
|--------|-----------|
| Eskalacja uprawnień | `is_admin` zawsze odczytywany z DB, nie tylko z sesji — sprawdź przy promote |
| Admin impersonuje admina | Blokada w `AdminService::impersonate()` |
| Sesja admina przechwycona podczas impersonacji | `Session::regenerate(true)` na start i exit impersonacji |
| CSRF na akcje block/promote/impersonate | `$this->request->verifyCsrf()` w każdym POST endpoincie |
| Admin blokuje samego siebie | Blokada w `AdminController::block()` — check `$targetId !== $adminId` |
| XSS w meta JSON logów | Escapowanie przez `htmlspecialchars()` w widoku |
| Widok `/admin` przez impersonującego admina | `AdminMiddleware` blokuje gdy `is_admin=false` |

---

## AdminLayout.php

Dedykowany layout z lewym sidebarem (ciemny panel):
- Logo + "Panel Administratora"
- Nawigacja: Dashboard / Użytkownicy / Drzewa / Logi
- Górny pasek: aktualne konto admina + "Wyjdź z panelu" → redirect `/dashboard`
- Szerokość: full-width sidebar + content area
- Kolory: `bg-slate-900` sidebar, `bg-background` content

---

## Fazy implementacji

### Faza 1: Baza + Model + Auth (20 min)
- `migrations/004_admin.sql` — `is_admin`, `is_blocked`, `admin_logs`
- USER_ACTION: uruchom migrację
- Zaktualizuj `User.php` — dodaj `isAdmin`, `isBlocked`
- Zaktualizuj `UserRepository.php` — `findAllForAdmin()`, `setAdmin()`, `setBlocked()`
- Zaktualizuj `AuthController::processLogin()` — sprawdzaj `is_blocked`, ustaw `is_admin` w sesji
- Dodaj `AdminRepository.php` — `getStats()`, `findAllUsers()`, `findLogs()`, `createLog()`
- Dodaj `AdminService.php` — `impersonate()`, `exitImpersonate()`, `block()`, `promote()`, `logAction()`
- Dodaj `AdminMiddleware.php`

### Faza 2: Kontroler + Routing (15 min)
- `AdminController.php` — 11 metod
- Aktualizacja `public/index.php` — nowe zależności + routing `/admin/*`

### Faza 3: Widoki (30 min)
- `AdminLayout.php` — sidebar + impersonation banner
- `admin/dashboard.php` — liczniki stats + ostatnie 10 logów
- `admin/users.php` — tabela z search, badge admin/blocked, przyciski akcji
- `admin/user-detail.php` — karta usera + historia drzew + przyciski blokada/mianuj/zaloguj jako
- `admin/trees.php` — lista wszystkich drzew z ownerem i liczbą osób
- `admin/logs.php` — tabela audit log z paginacją

### Faza 4: Impersonation banner w AppLayout (5 min)
- Dodaj banner do `AppLayout.php` — widoczny tylko gdy `Session::has('_admin_user_id')`
- Formularz POST `/admin/impersonate/exit` z CSRF

### Faza 5: Testy E2E (10 min)
- Ręczny SQL: ustaw is_admin=1 na koncie testowym
- Zaloguj → sprawdź redirect /admin działa
- Kliknij "Zaloguj jako" → sprawdź banner + sesja zmieniona
- Kliknij "Wyjdź" → sprawdź powrót sesji admina
- Blokada konta → sprawdź error przy logowaniu
- Mianowanie admina → sprawdź is_admin=1 w DB

---

## Krytyczne pliki do modyfikacji

- `migrations/004_admin.sql` — NOWY
- `src/Models/User.php` — dodaj isAdmin, isBlocked
- `src/Controllers/AuthController.php` — is_blocked + is_admin w sesji
- `src/views/templates/AppLayout.php` — banner impersonacji
- `public/index.php` — nowe zależności + routing
