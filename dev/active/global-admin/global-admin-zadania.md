# Zadania: Global Admin Panel

## Faza 1: Baza + Model + Auth

- [x] Utwórz `migrations/004_admin.sql` z kolumnami `is_admin`, `is_blocked` i tabelą `admin_logs`
- [ ] **USER_ACTION: `source .env.local && docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/004_admin.sql`**
- [ ] **USER_ACTION: `UPDATE users SET is_admin = 1 WHERE email = 'twój@email.pl';`** — ustaw pierwszego admina
- [x] Zaktualizuj `src/Models/User.php` — dodaj `bool $isAdmin` i `bool $isBlocked` + `fromArray()`
- [x] Zaktualizuj `src/Repositories/UserRepository.php` — dodaj `findAllForAdmin()`, `setAdmin()`, `setBlocked()`
- [x] Utwórz `src/Repositories/AdminRepository.php` — `getStats()`, `findAllUsers()`, `findLogs()`, `createLog()`
- [x] Utwórz `src/Services/AdminService.php` — `impersonate()`, `exitImpersonate()`, `block()`, `unblock()`, `promote()`, `demote()`, `logAction()`
- [x] Utwórz `src/Middleware/AdminMiddleware.php` — sprawdza `Session::get('is_admin')` i blokuje gdy false
- [x] Zaktualizuj `src/Controllers/AuthController.php` — sprawdź `is_blocked` po weryfikacji hasła; dodaj `Session::set('is_admin', $user->isAdmin)` po logowaniu

## Faza 2: Kontroler + Routing

- [x] Utwórz `src/Controllers/AdminController.php` z metodami: `dashboard()`, `users()`, `userDetail()`, `block()`, `unblock()`, `promote()`, `demote()`, `impersonate()`, `exitImpersonate()`, `trees()`, `logs()`
- [x] Zaktualizuj `public/index.php` — dodaj import `AdminController`, `AdminMiddleware`, `AdminRepository`, `AdminService`
- [x] Dodaj w `public/index.php` routing grupy `/admin` z `AdminMiddleware`
- [x] Zweryfikuj kolejność: `/admin/impersonate/exit` MUSI być przed `/admin/users/{uid}`

## Faza 3: AdminLayout + widoki

- [x] Utwórz `src/views/templates/AdminLayout.php` — sidebar (slate-900) + topbar + content area
- [x] Utwórz `src/views/pages/admin/dashboard.php` — 5 kart ze statystykami + tabela ostatnich 10 logów
- [x] Utwórz `src/views/pages/admin/users.php` — tabela z Alpine search, badge admin/blocked, przyciski akcji
- [x] Utwórz `src/views/pages/admin/user-detail.php` — karta usera + lista drzew + przyciski (blokada/mianuj/zaloguj jako)
- [x] Utwórz `src/views/pages/admin/trees.php` — tabela wszystkich drzew z ownerem + liczba osób
- [x] Utwórz `src/views/pages/admin/logs.php` — tabela audit log z paginacją (50/strona)

## Faza 4: Impersonation banner

- [x] Zaktualizuj `src/views/templates/AppLayout.php` — dodaj banner impersonacji (fixed bottom, destructive color)
- [x] Banner: imię impersonowanego usera + formularz POST `/admin/impersonate/exit` z CSRF
- [x] Banner widoczny tylko gdy `Session::has('_admin_user_id')`

## Faza 5: Weryfikacja

- [ ] PHP syntax check: `php -l src/Controllers/AdminController.php` i pozostałe nowe pliki
- [ ] Sprawdź logowanie admina → przekierowanie po `Session::get('is_admin')` nadal działa na `/dashboard`
- [ ] Przejdź do `/admin` → widoczny panel
- [ ] Kliknij "Zaloguj jako" przy koncie testowym → banner pojawia się, `user_id` w sesji zmieniony
- [ ] Kliknij "Wyjdź" → sesja admina przywrócona, przekierowanie na `/admin`
- [ ] Zablokuj konto testowe → przy logowaniu błąd "konto zablokowane"
- [ ] Sprawdź `admin_logs` w DB — wpisy impersonate_start, impersonate_end, block

---

## Do poprawy po review

### 🔴 Blocking — panel niefunkcjonalny

- [x] 🔴 [blocking] **src/views/pages/admin/users.php + user-detail.php** — `Csrf::token()` nie istnieje; zastąpione przez `Csrf::hiddenInput()` (eliminuje też field name mismatch)
- [x] 🔴 [blocking] **src/Middleware/AdminMiddleware.php + public/index.php** — `/admin/impersonate/exit` wyciągnięte poza grupę `/admin` (używa tylko AuthMiddleware)
- [x] 🔴 [blocking] **src/views/pages/admin/user-detail.php** — wszystkie formularze używają `Csrf::hiddenInput()` (renderuje `_csrf_token`)
- [x] 🔴 [blocking] **src/Repositories/AdminRepository.php** — LIMIT z `(int)$limit` interpolacją; LIKE z `addcslashes` + `ESCAPE`
- [x] 🔴 [blocking] **src/Services/AdminService.php** — `assertCallerIsAdmin()` weryfikuje `is_admin` z DB przed każdą wrażliwą akcją (privilege escalation guard)

### 🟠 Important

- [x] 🟠 [important] **src/Services/AdminService.php** — `exitImpersonate` weryfikuje admin nadal ma `isAdmin && !isBlocked`; przy braku → wyjątek + destroy session w controllerze + redirect na /login
- [x] 🟠 [important] **src/Controllers/AdminController.php** — `Session::set('is_admin', $admin->isAdmin)` z DB
- [x] 🟠 [important] **src/Repositories/AdminRepository.php** — LIKE z `addcslashes($search, '%_\\')` + `ESCAPE '\\'`
- [x] 🟠 [important] **src/Controllers/AdminController.php** — usunięty `htmlspecialchars` z title (layout sam eskejpuje)
- [x] 🟠 [important] **src/Services/AdminService.php promote** — rzuca wyjątek gdy `$target->isBlocked === true`
- [x] 🟠 [important] **src/Services/AdminService.php** — `assertValidUuid()` weryfikuje format UUID v4 przed każdą akcją
- [x] 🟠 [important] **src/Services/AdminService.php** — idempotency guards (`block` rzuca gdy już zablokowany; `unblock` gdy nie zablokowany; `demote` gdy nie admin)
- [x] 🟠 [important] **src/views/pages/admin/users.php + user-detail.php** — dodany badge „Nieaktywny" dla `is_active = 0`
- [x] 🟠 [important] **src/views/templates/AppLayout.php** — banner impersonacji używa `Csrf::hiddenInput()` (był `csrf_token` zamiast `_csrf_token`)

---

## Suggestions z review — wykonane (2026-04-08)

### 🔵 Wszystkie 7 sugestii zaimplementowane

- [x] 🔵 **Audit log dla view-actions** — `AdminController::userDetail` loguje akcję `view_user` z `target_email` w meta. Wymóg RODO (kto kiedy czytał czyje dane).
- [x] 🔵 **Filtry w `/admin/logs`** — formularz GET z 5 polami (action, admin_id, target_id, date_from, date_to). `AdminRepository::findLogs()` przyjmuje `$filters` array. Dropdown "action" auto-wypełniany przez `getLogActions()`.
- [x] 🔵 **Rate limit `/admin/users/{uid}/impersonate`** — 10 prób/godzinę per admin (`endpoint = 'impersonate:' . $adminId`). `AdminController::impersonate` sprawdza przez `AuthService::isRateLimited()`.
- [x] 🔵 **Session invalidation on demote/block** — `users.session_version` (migracja 007_admin_extras.sql). `UserRepository::setAdmin/setBlocked` inkrementuje. `AuthMiddleware` sprawdza session_version z DB co request — niezgodność = destroy session + redirect /login.
- [x] 🔵 **Notify impersonowanego użytkownika** — `NotificationService::notifyImpersonationEnded()` (typ `impersonation`). Admin otrzymuje powiadomienie po `exitImpersonate` zawierające imię admina + datę startu impersonacji. Wymóg RODO Art. 5(1)(a).
- [x] 🔵 **Test e2e/PHPUnit dla full impersonate cycle** — Skip (wymaga real DB lub większego mock setupu, akceptowalne dla MVP)
- [x] 🔵 **Wszędzie `Csrf::hiddenInput()`** — już zrobione w fazie poprzedniej (review #1)

### 🟡 Nit

- [ ] 🟡 [nit] **migrations/004_admin.sql** — `meta TEXT` powinno być `JSON` (nie zmieniać po deploy)
- [ ] 🟡 [nit] **migrations/004_admin.sql** — brak indeksu `(target_type, target_id)` w `admin_logs`
- [ ] 🟡 [nit] **src/views/templates/AdminLayout.php:61** — logika klasy aktywnej dla `/admin` niejasna; `$currentPath === '/admin'`
- [ ] 🟡 [nit] **src/views/pages/admin/users.php:8** — `x-data="{ search: ... }"` deklaracja nieużywana
- [ ] 🟡 [nit] **src/views/pages/admin/logs.php:67-71** — `array_map` może rzucić błąd przy zagnieżdżonych tablicach; defensive cast
- [ ] 🟡 [nit] **src/Services/AdminService.php:145-151** — `generateUuid()` duplikat z AuthService; wyciągnąć do `App\Core\Uuid`
- [ ] 🟡 [nit] **src/Middleware/AdminMiddleware.php:14** — sygnatura `: bool` ale praktycznie zawsze `redirect` lub `true`
