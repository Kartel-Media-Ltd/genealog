# Tree Sharing — Kontekst i decyzje architektoniczne

**Feature:** Zaproszenia + zarządzanie rolami  
**Data:** 2026-04-07

---

## 1. Dlaczego `php mail()` najpierw, PHPMailer opcjonalnie

### Wybór dla MVP: `php mail()`

`php mail()` jest funkcją wbudowaną w PHP — zero zależności Composera, działa wszędzie gdzie skonfigurowany jest sendmail lub SMTP lokalny. Dla MVP to wystarczające.

**Zalety:**
- Zero konfiguracji na większości hostingów (sendmail/postfix skonfigurowany)
- Natychmiastowe wdrożenie — EmailService to ~30 linii kodu
- Wystarczające dla małej bazy użytkowników (kilkadziesiąt drzew)

**Wady:**
- Brak śledzenia dostarczenia
- Ląduje w spamie jeśli serwer nie ma SPF/DKIM
- Brak kolejkowania (synchroniczne w kontrolerze)

**Kiedy przejść na PHPMailer:**
- Jeśli `mail()` nie działa na hostingu (shared hosting często blokuje)
- Jeśli potrzeba SMTP relay (SendGrid, Mailgun, Amazon SES)
- Jeśli potrzeba szablonów HTML wymagających biblioteki
- Komenda: `composer require phpmailer/phpmailer`

**Abstrakcja gotowa:** `EmailService` ma jeden interfejs — podmiana na PHPMailer wymaga zmiany tylko implementacji `sendInvitation()`, nie kodu wywołującego.

---

## 2. Token storage — tylko w DB, nie w sesji

### Decyzja: Token wyłącznie w `invitations.token`

**Dlaczego nie w sesji:**
- Sesja jest per-przeglądarka. Zaproszony może kliknąć link na innym urządzeniu niż ten gdzie inicjalnie otworzył stronę.
- Sesja wygasa — token traci ważność razem z sesją, nie po 7 dniach.
- Link z tokenem musi działać po zamknięciu przeglądarki i ponownym otwarciu.

**Dlaczego nie w cookie:**
- Cookie jest powiązane z domeną i przeglądarką.
- Podobne problemy jak sesja.

**Token jako self-contained credential:**
Wzorzec stosowany przez GitHub, Slack, Notion, Google — link z tokenem działa niezależnie od stanu sesji. Token === tymczasowe uprawnienie do jednej akcji (dołączenie do drzewa).

**Jedyne miejsce gdzie sesja jest używana do tokens:**
`Session::set('pending_invitation', $token)` — tymczasowo, gdy niezalogowany user trafia na `/invite/{token}`. Po zalogowaniu/rejestracji token jest odczytywany, usuwany z sesji i przetwarzany normalnie.

---

## 3. Przepływ: zaproszony nie ma konta vs ma konto

### Przypadek A: Zaproszony MA konto i jest zalogowany

```
GET /invite/{token}
  → InvitationController::showAccept()
  → Session::has('user_id') = true
  → Renderuje stronę z przyciskiem "Dołącz do drzewa"
  
POST /invite/{token}/accept
  → InvitationService::accept($token, Session::get('user_id'))
  → INSERT tree_members
  → UPDATE invitations SET used_at = NOW()
  → redirect /trees/{treeId}
```

### Przypadek B: Zaproszony MA konto, ale NIE jest zalogowany

```
GET /invite/{token}
  → InvitationController::showAccept()
  → Session::has('user_id') = false
  → Session::set('pending_invitation', $token)
  → Renderuje stronę z opcjami: "Zaloguj się" / "Utwórz konto"
  
GET /login  (user klika "Zaloguj się")
  → Normalne logowanie

POST /login
  → AuthController::processLogin()
  → po sukcesie: sprawdź Session::get('pending_invitation')
  → Session::delete('pending_invitation')
  → redirect /invite/{token}    ← teraz user jest zalogowany

GET /invite/{token}   (ponownie, już zalogowany)
  → Przypadek A
```

### Przypadek C: Zaproszony NIE MA konta

```
GET /invite/{token}
  → showAccept() → Session::set('pending_invitation', $token)
  → Renderuje stronę z opcją "Utwórz konto"

GET /register?invitation={token}
  → AuthController::showRegister()
  → Opcjonalnie: odczyt tokenu z query — prefill emaila jeśli pasuje

POST /register
  → AuthController::processRegister()
  → po sukcesie: sprawdź Session::get('pending_invitation')
  → Session::delete('pending_invitation')  
  → redirect /invite/{token}

POST /invite/{token}/accept
  → Przypadek A (nowy user już jest zalogowany w sesji)
```

### Przypadek D: Token wygasł / użyty

```
GET /invite/{token}
  → InvitationService::findValidByToken($token) = null
  → withFlash('error', 'Link zaproszenia wygasł lub jest nieprawidłowy.')
  → redirect /login
```

### Przypadek E: Już jest członkiem (idempotentność)

```
POST /invite/{token}/accept  (user kliknął link drugi raz)
  → InvitationService::accept()
  → treeRepo->getUserRole($treeId, $userId) != null  ← już jest memberem
  → UPDATE invitations SET used_at = NOW()  ← oznacz jako zużyty jeśli nie był
  → withFlash('info', 'Jesteś już członkiem tego drzewa.')
  → redirect /trees/{treeId}
```

---

## 4. Aktualizacja TreeAccessMiddleware / guards

### Obecny stan

Nie istnieje dedykowany `TreeAccessMiddleware` — weryfikacja dostępu jest robiona bezpośrednio w kontrolerach i serwisach:
- `TreeController::show()` wywołuje `TreeService::getForUser()` → `treeRepo->findForUser()` (sprawdza owner_id OR tree_members)
- `PersonController` wywołuje `personSvc->create()` → wewnętrznie sprawdza dostęp przez `treeRepo`

### Co się zmienia po dodaniu tree sharing

`treeRepo->findForUser()` już poprawnie obsługuje memberów (linia 43 w `TreeRepository`):
```sql
OR EXISTS (SELECT 1 FROM tree_members tm WHERE tm.tree_id = t.id AND tm.user_id = :uid2)
```
Zaproszeni członkowie automatycznie uzyskają dostęp do `GET /trees/{id}` i wszystkich podstron przez ten istniejący check.

### Co MUSI zostać zaktualizowane

**PersonController** i **RelationshipController** — akcje zapisu muszą weryfikować rolę:

```php
// Obecny stan (PersonController) — brak weryfikacji roli:
$tree = $treeRepo->findForUser($treeId, $userId);  // działa dla viewer też

// Po tree sharing — viewer nie może edytować:
$role = $treeRepo->getUserRole($treeId, $userId);
if (!in_array($role, ['owner', 'editor'], true)) {
    $response->withFlash('error', 'Brak uprawnień do edycji.')->redirect('/trees/' . $treeId);
}
```

Dotyczy metod:
- `PersonController::processCreate()`, `processEdit()`, `delete()`, `uploadPhoto()`
- `RelationshipController::processCreate()`, `delete()`

Wzorzec helper (można dodać do PersonController/RelationshipController):
```php
private function requireEditorAccess(string $treeId): void
{
    $userId = Session::get('user_id');
    $role = $this->treeRepo->getUserRole($treeId, $userId);
    if (!in_array($role, ['owner', 'editor'], true)) {
        $this->response
            ->withFlash('error', 'Masz dostęp tylko do podglądu tego drzewa.')
            ->redirect('/trees/' . $treeId);
    }
}
```

### Widok show.php — nawigacja dla viewer

`$canEdit` na linii 8 `src/views/pages/trees/show.php`:
```php
$canEdit = in_array($userRole, ['owner', 'editor'], true);
```
Jest już poprawny — viewer nie zobaczy przycisku "Dodaj osobę". Brak zmian potrzebnych.

---

## 5. Zależności i kolejność implementacji

Kolejność ważna dla uniknięcia błędów "class not found":

```
migrations/005_invitations_indexes.sql       (1. DB)
    ↓
InvitationRepository.php                     (2. zależy od DB)
    ↓
EmailService.php                             (2. niezależny)
    ↓
InvitationService.php                        (3. zależy od InvitationRepository + EmailService + TreeRepository)
    ↓
InvitationController.php                     (4. zależy od InvitationService)
    ↓
public/index.php (routing)                   (5. rejestracja wszystkiego)
    ↓
src/views/pages/trees/members.php            (6. widoki)
src/views/pages/invite/accept.php
    ↓
AuthController.php (modyfikacja)             (7. integracja pending_invitation)
TreeController.php (modyfikacja)             (7. findByMember)
PersonController.php (modyfikacja)           (7. requireEditorAccess)
RelationshipController.php (modyfikacja)     (7. requireEditorAccess)
```

---

## 6. Konfiguracja — stałe do dodania w `config/config.php`

```php
// E-mail
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost');
define('MAIL_FROM_NAME',    getenv('MAIL_FROM_NAME') ?: 'Genealog');
define('APP_URL',           getenv('APP_URL') ?: 'http://localhost:8080');

// Rate limiting — zaproszenia
define('INVITE_RATE_LIMIT_ATTEMPTS', (int)(getenv('INVITE_RATE_LIMIT_ATTEMPTS') ?: 10));
define('INVITE_RATE_LIMIT_WINDOW',   (int)(getenv('INVITE_RATE_LIMIT_WINDOW') ?: 900)); // 15 min
```

I w `.env.local`:
```
MAIL_FROM_ADDRESS=noreply@genealog.pl
MAIL_FROM_NAME=Genealog
APP_URL=http://localhost:8080
```
