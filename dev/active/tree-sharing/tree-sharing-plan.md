# Tree Sharing — Pełna architektura

**Feature:** Zaproszenia do drzewa genealogicznego + zarządzanie rolami członków  
**Faza MVP:** 5 (Tree Sharing) wg harmonogramu CLAUDE.md  
**Data planu:** 2026-04-07

---

## 1. Przegląd przepływu

```
Właściciel drzewa
  → GET  /trees/{id}/members          — widzi listę członków + formularz zaproszenia
  → POST /trees/{id}/invite           — wysyła zaproszenie (zapisuje token, wysyła e-mail)

System → wysyła e-mail z linkiem:
  https://domena/invite/{token}

Zaproszony użytkownik
  → GET  /invite/{token}              — strona akceptacji (sprawdza token, TTL, nie wygasł)
    → jeśli NIE ma konta → pokazuje formularz rejestracji + ukryty token
    → jeśli MA konto i jest zalogowany → bezpośrednie accept
  → POST /invite/{token}/accept       — akceptuje zaproszenie (INSERT tree_members, UPDATE used_at)

Właściciel
  → POST /trees/{id}/members/{uid}/remove  — usuwa członka
  → POST /trees/{id}/members/{uid}/role    — zmienia rolę (editor ↔ viewer)
```

---

## 2. Endpointy

### 2.1 Chronione (wymagają `AuthMiddleware`)

| Metoda | Ścieżka | Kontroler::metoda | Opis | Uprawnienia |
|--------|---------|-------------------|------|-------------|
| GET | `/trees/{id}/members` | `InvitationController::members()` | Lista członków + formularz zaproszenia | owner |
| POST | `/trees/{id}/invite` | `InvitationController::invite()` | Wyślij zaproszenie | owner |
| POST | `/trees/{id}/members/{uid}/remove` | `InvitationController::removeMember()` | Usuń członka | owner |
| POST | `/trees/{id}/members/{uid}/role` | `InvitationController::changeRole()` | Zmień rolę | owner |

### 2.2 Publiczne (bez `AuthMiddleware`, token działa jako tymczasowy klucz)

| Metoda | Ścieżka | Kontroler::metoda | Opis |
|--------|---------|-------------------|------|
| GET | `/invite/{token}` | `InvitationController::showAccept()` | Strona przyjęcia zaproszenia |
| POST | `/invite/{token}/accept` | `InvitationController::processAccept()` | Akceptuj zaproszenie |

> **Uwaga:** `/invite/{token}` jest publiczne, ale po akceptacji wymaga zalogowanego użytkownika. Jeśli user nie jest zalogowany, `processAccept` przekierowuje do `/register?invitation={token}` lub `/login?invitation={token}` z zachowaniem tokenu w sesji.

---

## 3. Tabele — stan i zmiany

### 3.1 Tabela `invitations` (już istnieje — `migrations/002_trees.sql`)

Istniejące kolumny:
- `id` CHAR(36), `tree_id` FK, `invited_by` FK users
- `invited_email` VARCHAR(254), `token` CHAR(64) UNIQUE
- `role` ENUM('editor','viewer'), `expires_at` TIMESTAMP
- `used_at` TIMESTAMP NULL, `created_at` TIMESTAMP
- Indeksy: `uq_invitations_token`, `idx_inv_email`, `idx_inv_expires`

**Brakuje:** indeks na `(tree_id, used_at)` — przyda się do listowania aktywnych zaproszeń per drzewo.

### 3.2 Tabela `tree_members` (już istnieje — `migrations/002_trees.sql`)

Istniejące kolumny:
- `tree_id` FK, `user_id` FK, `role` ENUM('owner','editor','viewer'), `invited_at`

**Brakuje:** kolumna `invited_by` CHAR(36) NULL — kto zaprosił (dla audytu). Opcjonalna na MVP.

### 3.3 Migracja `migrations/005_invitations_indexes.sql`

```sql
-- Migration 005: Dodatkowe indeksy dla tree sharing
-- Brak zmian strukturalnych — tabele invitations i tree_members już istnieją w 002_trees.sql

ALTER TABLE invitations
    ADD INDEX idx_inv_tree_active (tree_id, used_at, expires_at);

ALTER TABLE tree_members
    ADD COLUMN invited_by CHAR(36) NULL AFTER role,
    ADD CONSTRAINT fk_tree_members_invited_by
        FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL;
```

---

## 4. Nowe pliki

### 4.1 Controller

**`src/Controllers/InvitationController.php`**

```
Metody:
- members(Request $r): never
  → sprawdza isOwner(), pobiera listę członków z repo, listę aktywnych zaproszeń
  → renderuje pages/trees/members

- invite(Request $r): never
  → verifyCsrf(), isOwner()
  → walidacja: email (filter_var), role (editor|viewer)
  → InvitationService::invite($treeId, $email, $role, $inviterId)
  → withFlash('success') → redirect /trees/{id}/members

- showAccept(Request $r): never
  → InvitationService::findValidByToken($token)
  → jeśli null/wygasł → withFlash('error', 'Link wygasł lub jest nieprawidłowy.') → redirect /login
  → renderuje pages/invite/accept (z formularzem jeśli niezalogowany)

- processAccept(Request $r): never
  → verifyCsrf()
  → InvitationService::accept($token, $userId)
  → withFlash('success', 'Dołączyłeś do drzewa.') → redirect /trees/{treeId}

- removeMember(Request $r): never
  → verifyCsrf(), isOwner()
  → nie można usunąć samego siebie (owner_id)
  → InvitationService::removeMember($treeId, $targetUid, $requesterId)
  → redirect /trees/{id}/members

- changeRole(Request $r): never
  → verifyCsrf(), isOwner()
  → nie można zmienić roli właściciela
  → InvitationService::changeRole($treeId, $targetUid, $newRole, $requesterId)
  → redirect /trees/{id}/members
```

### 4.2 Service

**`src/Services/InvitationService.php`**

```
Metody:
- invite(string $treeId, string $email, string $role, string $inviterId): void
  → walidacja: czy inviter jest ownerem ($treeRepo->isOwner)
  → sprawdź czy jest już aktywne zaproszenie dla tego email+tree (findActiveByEmailAndTree)
    → jeśli tak: rzuć wyjątek 'Zaproszenie dla tego adresu e-mail już oczekuje.'
  → sprawdź czy user z tym emailem jest już członkiem drzewa
    → jeśli tak: rzuć wyjątek 'Użytkownik jest już członkiem tego drzewa.'
  → $token = bin2hex(random_bytes(32))  // 64 znaki hex
  → $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'))
  → $invRepo->create(uuid, treeId, inviterId, email, token, role, expiresAt)
  → EmailService::sendInvitation(email, token, treeName, inviterName)

- findValidByToken(string $token): ?array
  → $invRepo->findByToken($token)
  → zwraca null jeśli: nie znaleziono, used_at IS NOT NULL, expires_at < NOW()

- accept(string $token, string $userId): string  // zwraca treeId
  → findValidByToken($token) → null: rzuć wyjątek
  → sprawdź czy userId nie jest już memberem ($treeRepo->getUserRole)
    → jeśli jest: tylko UPDATE used_at i redirect (idempotentne)
  → INSERT INTO tree_members (treeId, userId, role, invited_by=inviterId)
  → UPDATE invitations SET used_at = NOW() WHERE token = :token
  → zwróć treeId

- removeMember(string $treeId, string $targetUserId, string $requesterId): void
  → sprawdź czy requester jest ownerem
  → sprawdź czy target != owner_id (nie można usunąć właściciela)
  → $invRepo->deleteMember($treeId, $targetUserId)

- changeRole(string $treeId, string $targetUserId, string $newRole, string $requesterId): void
  → sprawdź czy requester jest ownerem
  → sprawdź czy target != owner_id
  → sprawdź czy newRole in ['editor', 'viewer']
  → $invRepo->updateMemberRole($treeId, $targetUserId, $newRole)
```

### 4.3 Repository

**`src/Repositories/InvitationRepository.php`**

```
Metody:
- create(id, treeId, invitedBy, invitedEmail, token, role, expiresAt): void
- findByToken(string $token): ?array
- findActiveByEmailAndTree(string $email, string $treeId): ?array
  → WHERE invited_email = :email AND tree_id = :tid AND used_at IS NULL AND expires_at > NOW()
- findActiveByTree(string $treeId): array
  → lista aktywnych (unused) zaproszeń dla drzewa
- deleteMember(string $treeId, string $userId): void
  → DELETE FROM tree_members WHERE tree_id = :tid AND user_id = :uid AND role != 'owner'
- updateMemberRole(string $treeId, string $userId, string $role): void
  → UPDATE tree_members SET role = :role WHERE tree_id = :tid AND user_id = :uid AND role != 'owner'
- getMembers(string $treeId): array
  → JOIN users: SELECT tm.*, u.name, u.email FROM tree_members tm JOIN users u ON u.id = tm.user_id WHERE tm.tree_id = :tid ORDER BY tm.role, u.name
```

### 4.4 Service e-mail

**`src/Services/EmailService.php`**

```
Metody:
- sendInvitation(string $toEmail, string $token, string $treeName, string $inviterName): bool
  → buduje link: APP_URL . '/invite/' . $token
  → buduje treść wiadomości (plain text + HTML)
  → php mail($toEmail, $subject, $body, $headers)
  → zwraca bool (czy mail() nie zwrócił false)

Konfiguracja przez stałe z config.php:
  MAIL_FROM_ADDRESS (np. noreply@genealog.pl)
  MAIL_FROM_NAME   (np. Genealog)
  APP_URL          (np. https://genealog.pl)
```

### 4.5 Widoki

**`src/views/pages/trees/members.php`** — lista członków + formularz zaproszenia

```
Sekcje:
1. Breadcrumb: Moje drzewa > [Nazwa] > Członkowie
2. Nagłówek z nazwą drzewa
3. Lista aktywnych członków (tabela/karty):
   - Avatar, Imię, Email, Rola (badge), Data dołączenia
   - Przyciski: "Zmień rolę" (Alpine.js inline dropdown), "Usuń" (confirm dialog)
   - Właściciel oznaczony Crown icon, nie ma przycisków akcji
4. Oczekujące zaproszenia (tabela):
   - Email, Rola, Wygasa, status "Oczekuje"
5. Formularz zaproszenia (na dole lub modal):
   - Input email, Select rola (editor/viewer), Button "Zaproś"
   - CSRF token, POST /trees/{id}/invite
```

**`src/views/pages/invite/accept.php`** — strona akceptacji

```
Sekcje:
1. Nagłówek: "[Imię zapraszającego] zaprasza Cię do drzewa [Nazwa]"
2. Jeśli zalogowany → Button "Dołącz do drzewa" (POST /invite/{token}/accept)
3. Jeśli nie zalogowany:
   → Dwie opcje:
     a) "Mam już konto" → link /login?invitation={token}
     b) "Utwórz konto" → link /register?invitation={token}
   → Wyjaśnienie: "Po zalogowaniu automatycznie dołączysz do drzewa"
4. Info o roli (viewer/editor) i drzewie
```

---

## 5. Integracja z istniejącym kodem

### 5.1 AuthController — obsługa `?invitation={token}` po logowaniu/rejestracji

Po udanym `processLogin()` i `processRegister()`:
```php
// Jeśli w sesji/query jest invitation token → redirect do /invite/{token}/accept
$invitation = $request->getParam('invitation');
if ($invitation) {
    $response->redirect('/invite/' . $invitation . '/accept');
}
```

Alternatywnie: token przechowywany w sesji przed przekierowaniem na login:
```php
// W showAccept(): jeśli niezalogowany
Session::set('pending_invitation', $token);
$response->redirect('/login');

// W processLogin() po zalogowaniu:
if ($pending = Session::get('pending_invitation')) {
    Session::delete('pending_invitation');
    $response->redirect('/invite/' . $pending);
}
```

### 5.2 TreeController::index() — drzewa jako member

Aktualna metoda `findByOwner()` zwraca tylko drzewa własne. Trzeba dodać `findByMember()` w `TreeRepository`:

```sql
SELECT t.*, tm.role AS user_role, COUNT(p.id) AS persons_count
FROM trees t
JOIN tree_members tm ON tm.tree_id = t.id AND tm.user_id = :uid AND tm.role != 'owner'
LEFT JOIN persons p ON p.tree_id = t.id
GROUP BY t.id
ORDER BY t.updated_at DESC
```

I w `TreeController::index()` scalać oba wyniki — `findByOwner()` + `findByMember()`.

### 5.3 Rejestracja routingu w `public/index.php`

```php
// Dodać do DI:
use App\Repositories\InvitationRepository;
use App\Services\InvitationService;
use App\Services\EmailService;
use App\Controllers\InvitationController;

$invRepo   = new InvitationRepository($db);
$emailSvc  = new EmailService();
$invSvc    = new InvitationService($treeRepo, $invRepo, $userRepo, $emailSvc);

// Publiczne trasy (przed chronionymi):
$router->get('/invite/{token}',          [new InvitationController(...), 'showAccept']);
$router->post('/invite/{token}/accept',  [new InvitationController(...), 'processAccept']);

// Wewnątrz group('/trees', $mw, ...):
$r->get('/{id}/members',                       [$invCtrl, 'members']);
$r->post('/{id}/invite',                       [$invCtrl, 'invite']);
$r->post('/{id}/members/{uid}/remove',         [$invCtrl, 'removeMember']);
$r->post('/{id}/members/{uid}/role',           [$invCtrl, 'changeRole']);
```

---

## 6. Kluczowe decyzje projektowe

| Decyzja | Wybór | Uzasadnienie |
|---------|-------|--------------|
| Token storage | Tylko `invitations.token` w DB | Brak stanu w sesji, token działa jak self-contained credential |
| Email wysyłka | `php mail()` (MVP) | Zero zależności, wystarczające dla MVP; PHPMailer dodać jeśli mail() nie działa na hostingu |
| Token format | `bin2hex(random_bytes(32))` = 64 znaki | Kryptograficznie bezpieczny, pasuje do CHAR(64) |
| Właściciel nie może usunąć siebie | Enforced w service | Ochrona przed przypadkowym lockiem |
| Idempotentność accept | Tak | Podwójne kliknięcie linku nie powoduje błędu |
| Nie ujawniaj czy email istnieje | Tak | Odpowiedź zawsze: "Zaproszenie zostało wysłane" |
