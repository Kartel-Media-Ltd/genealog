# Tree Sharing — Checklist implementacji

**Feature:** Zaproszenia + zarządzanie rolami  
**Data:** 2026-04-07  
**Status:** Gotowy do implementacji

Legenda: `[ ]` — do zrobienia | `[x]` — ukończone | `[USER]` — wymaga akcji użytkownika

---

## Faza 1 — Migracja bazy danych

- [ ] `[USER]` Uruchom migrację:
  ```bash
  source .env.local && docker exec -i mariadb_docker mariadb \
    -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
    < migrations/005_invitations_indexes.sql
  ```
- [ ] Utwórz plik `migrations/005_invitations_indexes.sql` (indeks + kolumna `invited_by` w `tree_members`)
- [ ] Sprawdź poprawność migracji: `SHOW CREATE TABLE invitations; SHOW CREATE TABLE tree_members;`

---

## Faza 2 — Backend: Repository

### `src/Repositories/InvitationRepository.php` (nowy plik)

- [ ] Szkielet klasy z `__construct(Database $db)`, `declare(strict_types=1)`
- [ ] `create(string $id, string $treeId, string $invitedBy, string $invitedEmail, string $token, string $role, string $expiresAt): void`
  - INSERT INTO invitations
- [ ] `findByToken(string $token): ?array`
  - SELECT * FROM invitations WHERE token = :token LIMIT 1
- [ ] `findActiveByEmailAndTree(string $email, string $treeId): ?array`
  - WHERE used_at IS NULL AND expires_at > NOW() AND invited_email AND tree_id
- [ ] `findActiveByTree(string $treeId): array`
  - Lista niezużytych zaproszeń dla drzewa
- [ ] `getMembers(string $treeId): array`
  - JOIN users: id, name, email, role, invited_at, invited_by
- [ ] `deleteMember(string $treeId, string $userId): void`
  - DELETE ... WHERE role != 'owner' (guard w SQL)
- [ ] `updateMemberRole(string $treeId, string $userId, string $role): void`
  - UPDATE ... WHERE role != 'owner'

---

## Faza 3 — Backend: Services

### `src/Services/EmailService.php` (nowy plik)

- [ ] Szkielet klasy — brak konstruktora (używa stałych z config.php)
- [ ] `sendInvitation(string $toEmail, string $token, string $treeName, string $inviterName): bool`
  - Buduje nagłówki: From, Content-Type: text/html
  - Buduje subject: "Zaproszenie do drzewa genealogicznego: [treeName]"
  - Buduje HTML body z linkiem `/invite/{token}`
  - Plain text fallback (multipart/alternative)
  - Wywołuje `mail()`, zwraca bool
- [ ] Dodaj stałe do `config/config.php`: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `APP_URL`
- [ ] `[USER]` Zweryfikuj czy `php mail()` działa na serwerze (wyślij testowy mail)

### `src/Services/InvitationService.php` (nowy plik)

- [ ] Szkielet klasy z konstruktorem:
  `(TreeRepository $treeRepo, InvitationRepository $invRepo, UserRepository $userRepo, EmailService $emailSvc)`
- [ ] Prywatna metoda `generateUuid()` — skopiować z AuthService
- [ ] `invite(string $treeId, string $email, string $role, string $inviterId): void`
  - Walidacja: isOwner, filter_var email, role in ['editor','viewer']
  - Guard: czy aktywne zaproszenie już istnieje (findActiveByEmailAndTree)
  - Guard: czy user już jest członkiem (treeRepo->getUserRole)
  - INSERT token + send email
  - Odpowiedź "Zaproszenie wysłano" bez względu na to czy email istnieje w systemie
- [ ] `findValidByToken(string $token): ?array`
  - Pobiera, sprawdza used_at IS NULL i expires_at > NOW()
  - Zwraca null jeśli cokolwiek nie pasuje
- [ ] `accept(string $token, string $userId): string`
  - findValidByToken → null → wyjątek
  - Guard: czy userId już jest memberem → idempotentne (tylko UPDATE used_at, redirect)
  - INSERT tree_members, UPDATE used_at
  - Zwraca treeId
- [ ] `removeMember(string $treeId, string $targetUserId, string $requesterId): void`
  - Walidacja: requester = owner, target != owner
- [ ] `changeRole(string $treeId, string $targetUserId, string $newRole, string $requesterId): void`
  - Walidacja: requester = owner, target != owner, role in ['editor','viewer']

---

## Faza 4 — Backend: Controller

### `src/Controllers/InvitationController.php` (nowy plik)

- [ ] Konstruktor:
  `(Request, Response, TreeRepository, InvitationService)`
- [ ] `members(): never`
  - isOwner check → redirect /trees jeśli nie
  - Pobiera: listę członków + aktywne zaproszenia
  - Renderuje `pages/trees/members`
- [ ] `invite(): never`
  - verifyCsrf()
  - isOwner check
  - Sanitizacja: email, role
  - Wywołuje InvitationService::invite()
  - try/catch → withFlash → redirect /trees/{id}/members
- [ ] `showAccept(): never`
  - findValidByToken → null → withFlash('error') → redirect /login
  - Pobiera dane drzewa (treeName, inviterName)
  - Renderuje `pages/invite/accept` z danymi
- [ ] `processAccept(): never`
  - verifyCsrf()
  - Sprawdź zalogowanie (Session::has('user_id')) → jeśli nie → zapisz token w sesji → redirect /login
  - InvitationService::accept($token, $userId) → treeId
  - withFlash('success', 'Dołączyłeś do drzewa') → redirect /trees/{treeId}
- [ ] `removeMember(): never`
  - verifyCsrf()
  - InvitationService::removeMember()
  - redirect /trees/{id}/members
- [ ] `changeRole(): never`
  - verifyCsrf()
  - InvitationService::changeRole()
  - redirect /trees/{id}/members

---

## Faza 5 — Routing

### `public/index.php`

- [ ] Dodaj importy (use): `InvitationController`, `InvitationRepository`, `InvitationService`, `EmailService`
- [ ] Dodaj inicjalizację DI:
  ```php
  $invRepo  = new InvitationRepository($db);
  $emailSvc = new EmailService();
  $invSvc   = new InvitationService($treeRepo, $invRepo, $userRepo, $emailSvc);
  ```
- [ ] Dodaj trasy **publiczne** (przed grupą `/trees`):
  ```php
  $router->get('/invite/{token}',         [new InvitationController(...), 'showAccept']);
  $router->post('/invite/{token}/accept', [new InvitationController(...), 'processAccept']);
  ```
- [ ] Dodaj trasy do grupy `/trees`:
  ```php
  $r->get('/{id}/members',              [$invCtrl, 'members']);
  $r->post('/{id}/invite',              [$invCtrl, 'invite']);
  $r->post('/{id}/members/{uid}/remove',[$invCtrl, 'removeMember']);
  $r->post('/{id}/members/{uid}/role',  [$invCtrl, 'changeRole']);
  ```

---

## Faza 6 — Integracja AuthController

### `src/Controllers/AuthController.php`

- [ ] W `processLogin()`: po sukcesie sprawdź `Session::get('pending_invitation')`
  - Jeśli istnieje → `Session::delete('pending_invitation')` → redirect `/invite/{token}`
- [ ] W `processRegister()`: to samo — po rejestracji i zalogowaniu redirect do invitation
- [ ] W `showAccept()` (InvitationController): gdy niezalogowany → `Session::set('pending_invitation', $token)`

---

## Faza 7 — Widoki

### `src/views/pages/trees/members.php` (nowy plik)

- [ ] Breadcrumb: Moje drzewa > [Nazwa] > Zarządzaj dostępem
- [ ] Sekcja: Aktualni członkowie
  - Tabela: Avatar (atom/avatar.php), Imię, Email, Rola (badge.php), Data dołączenia
  - Dla każdego nie-właściciela: dropdown "Edytor/Widz" (Alpine.js + form POST), przycisk "Usuń" (confirm dialog)
  - Właściciel: badge 'Właściciel', brak przycisków
- [ ] Sekcja: Oczekujące zaproszenia
  - Tabela: Email, Rola, Wygasa za X dni
  - Badge "Oczekuje"
- [ ] Sekcja: Zaproś osobę (formularz)
  - `<form method="POST" action="/trees/{id}/invite">`
  - CSRF hidden input
  - Input email (atom/input.php + atom/label.php)
  - Select rola: Edytor / Widz (atom/select.php)
  - Przycisk Zaproś (atom/button.php)
- [ ] Flash messages (molecule/flash-messages.php include)
- [ ] Wyświetlanie w AppLayout

### `src/views/pages/invite/accept.php` (nowy plik)

- [ ] Utwórz katalog `src/views/pages/invite/`
- [ ] Layout: AuthLayout (strona bez pełnej nawigacji) lub AppLayout jeśli zalogowany
- [ ] Nagłówek: "[Imię] zaprasza Cię do współpracy przy drzewie [Nazwa]"
- [ ] Podtytuł: "Rola: Edytor/Widz — [opis roli]"
- [ ] Jeśli zalogowany:
  - Form POST /invite/{token}/accept + CSRF + button "Dołącz do drzewa"
- [ ] Jeśli nie zalogowany:
  - Dwa linki: "Zaloguj się i dołącz" + "Utwórz konto i dołącz"
  - Wyjaśnienie że token jest jednorazowy (ważny 7 dni)
- [ ] Info o czasie wygaśnięcia

### `src/views/pages/trees/show.php` — modyfikacja

- [ ] Dodaj link "Zarządzaj dostępem" dla role = owner obok "Ustawienia":
  ```php
  <?php if ($userRole === 'owner'): ?>
  <a href="/trees/<?= $tree->id ?>/members">Zarządzaj dostępem</a>
  <?php endif; ?>
  ```

---

## Faza 8 — TreeRepository + Dashboard

### `src/Repositories/TreeRepository.php`

- [ ] Dodaj metodę `findByMember(string $userId): array`
  - Drzewa gdzie user jest członkiem (ale NIE właścicielem)
  - Zwraca Tree[] z `user_role` dostępnym jako pole

### `src/Controllers/TreeController.php`

- [ ] W `index()`: scalić wyniki `findByOwner()` i `findByMember()`
  - Przekazać `$sharedTrees` osobno lub jako jeden zbiór z flagą

### `src/views/pages/trees/index.php`

- [ ] Sekcja "Moje drzewa" (właściciel)
- [ ] Sekcja "Udostępnione mi" (member) — jeśli nie puste

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
