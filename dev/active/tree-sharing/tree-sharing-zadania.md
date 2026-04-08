# Tree Sharing — Checklist implementacji

**Feature:** Zaproszenia + zarządzanie rolami  
**Data:** 2026-04-07  
**Status:** Fazy 1-8 ukończone; Faza 9 = manualna weryfikacja E2E

Legenda: `[ ]` — do zrobienia | `[x]` — ukończone | `[USER]` — wymaga akcji użytkownika

---

## Faza 1 — Migracja bazy danych

- [x] `[USER]` Uruchom migrację:
  ```bash
  source .env.local && docker exec -i mariadb_docker mariadb \
    -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
    < migrations/005_invitations_indexes.sql
  ```
- [x] Utwórz plik `migrations/005_invitations_indexes.sql` (indeks + kolumna `invited_by` w `tree_members`) ✅ istnieje
- [x] Sprawdź poprawność migracji: `SHOW CREATE TABLE invitations; SHOW CREATE TABLE tree_members;`

---

## Faza 2 — Backend: Repository

### `src/Repositories/InvitationRepository.php` ✅ istnieje

- [x] Szkielet klasy z `__construct(Database $db)`, `declare(strict_types=1)`
- [x] `create(string $id, string $treeId, string $invitedBy, string $invitedEmail, string $token, string $role, string $expiresAt): void`
- [x] `findByToken(string $token): ?array`
- [x] `findActiveByEmailAndTree(string $email, string $treeId): ?array`
- [x] `findActiveByTree(string $treeId): array`
- [x] `getMembers(string $treeId): array`
- [x] `deleteMember(string $treeId, string $userId): void`
- [x] `updateMemberRole(string $treeId, string $userId, string $role): void`

---

## Faza 3 — Backend: Services

### `src/Services/EmailService.php` ✅ istnieje

- [x] Szkielet klasy — brak konstruktora (używa stałych z config.php)
- [x] `sendInvitation(string $toEmail, string $token, string $treeName, string $inviterName): bool`
- [x] Dodaj stałe do `config/config.php`: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `APP_URL`
- [ ] `[USER]` Zweryfikuj czy `php mail()` działa na serwerze (wyślij testowy mail)

### `src/Services/InvitationService.php` ✅ istnieje

- [x] Szkielet klasy z konstruktorem
- [x] Prywatna metoda `generateUuid()`
- [x] `invite(string $treeId, string $email, string $role, string $inviterId): void`
- [x] `findValidByToken(string $token): ?array`
- [x] `accept(string $token, string $userId): string`
- [x] `removeMember(string $treeId, string $targetUserId, string $requesterId): void`
- [x] `changeRole(string $treeId, string $targetUserId, string $newRole, string $requesterId): void`

---

## Faza 4 — Backend: Controller

### `src/Controllers/InvitationController.php` ✅ istnieje

- [x] Konstruktor
- [x] `members(): never`
- [x] `invite(): never`
- [x] `showAccept(): never`
- [x] `processAccept(): never`
- [x] `removeMember(): never`
- [x] `changeRole(): never`

---

## Faza 5 — Routing

### `public/index.php` ✅ skonfigurowane

- [x] Dodaj importy (use): `InvitationController`, `InvitationRepository`, `InvitationService`, `EmailService`
- [x] Dodaj inicjalizację DI
- [x] Dodaj trasy **publiczne** (przed grupą `/trees`)
- [x] Dodaj trasy do grupy `/trees`

---

## Faza 6 — Integracja AuthController

### `src/Controllers/AuthController.php`

- [x] W `processLogin()`: po sukcesie sprawdź `Session::get('pending_invitation')`
  - Jeśli istnieje → `Session::delete('pending_invitation')` → redirect `/invite/{token}`
- [x] W `processRegister()`: to samo — po rejestracji i zalogowaniu redirect do invitation
- [x] W `showAccept()` (InvitationController): gdy niezalogowany → `Session::set('pending_invitation', $token)` ✅ już istniało

---

## Faza 7 — Widoki

### `src/views/pages/trees/members.php` ✅ istnieje

- [x] Breadcrumb: Moje drzewa > [Nazwa] > Zarządzaj dostępem
- [x] Sekcja: Aktualni członkowie
- [x] Sekcja: Oczekujące zaproszenia
- [x] Sekcja: Zaproś osobę (formularz)
- [x] Flash messages
- [x] Wyświetlanie w AppLayout

### `src/views/pages/invite/accept.php` ✅ istnieje

- [x] Katalog `src/views/pages/invite/`
- [x] Layout + nagłówek + informacje o zaproszeniu
- [x] Formularz dla zalogowanego / linki dla niezalogowanego
- [x] Info o czasie wygaśnięcia

### `src/views/pages/trees/show.php` ✅ link "Zarządzaj dostępem" istnieje

---

## Faza 8 — TreeRepository + Dashboard

### `src/Repositories/TreeRepository.php` ✅

- [x] Metoda `findByMember(string $userId): array`

### `src/Controllers/TreeController.php` ✅

- [x] W `index()`: `$sharedTrees` przekazane do widoku

### `src/views/pages/trees/index.php` ✅

- [x] Sekcja "Moje drzewa" (właściciel)
- [x] Sekcja "Udostępnione mi" (member)

---

## Faza 9 — Weryfikacja

- [ ] Scenariusz A: Zaproś nowego użytkownika (nie ma konta) → rejestracja → dołączenie
- [ ] Scenariusz B: Zaproś istniejącego użytkownika → kliknięcie linka → dołączenie
- [ ] Scenariusz C: Kliknięcie wygasłego linku → error message
- [ ] Scenariusz D: Kliknięcie użytego linku (used_at != null) → error "Link już wykorzystany"
- [ ] Scenariusz E: Zmiana roli editor → viewer
- [ ] Scenariusz F: Usunięcie członka
- [ ] Scenariusz G: Próba zaproszenia istniejącego członka → error
- [ ] Scenariusz H: Nie-właściciel próbuje uzyskać dostęp do /members → redirect z błędem
- [ ] `[USER]` Sprawdź że e-mail dochodzi (mail log / mailpit lokalnie)
- [ ] `[USER]` Uruchom migrację na środowisku staging jeśli istnieje
