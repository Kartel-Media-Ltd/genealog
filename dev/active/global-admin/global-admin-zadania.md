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
