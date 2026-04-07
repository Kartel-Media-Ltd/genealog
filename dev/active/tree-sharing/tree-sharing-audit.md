# Tree Sharing — Audyt bezpieczeństwa

**Feature:** Zaproszenia + zarządzanie rolami  
**Data audytu:** 2026-04-07  
**Metodologia:** OWASP Top 10 + RODO Art. 25 (Privacy by Design)

Legenda: `KRYTYCZNE` | `POWAZNE` | `DROBNE` | `PASS`

---

## 1. Autoryzacja i kontrola dostępu

### 1.1 Tylko właściciel może zapraszać
**Status: POWAZNE — WYMAGA IMPLEMENTACJI**

Weryfikacja `isOwner()` musi być wykonana **w serwisie** (nie tylko w kontrolerze), ponieważ serwis może być wywoływany z różnych miejsc.

```php
// InvitationService::invite() — obowiązkowe:
if (!$this->treeRepo->isOwner($treeId, $inviterId)) {
    throw new \RuntimeException('Tylko właściciel może zapraszać.');
}
```

Weryfikacja tylko w kontrolerze jest niewystarczająca — zasada defense in depth.

### 1.2 IDOR: `/trees/{id}/members` — czy sprawdza właściciela?
**Status: KRYTYCZNE — WYMAGA IMPLEMENTACJI**

Każda metoda InvitationController odnosząca się do `{id}` musi weryfikować `isOwner($treeId, $userId)`:
- `members()` — GET lista — viewer nie powinien widzieć emailów oczekujących
- `invite()` — POST
- `removeMember()` — POST
- `changeRole()` — POST

Błąd: jeśli tylko `invite()` sprawdza ownera, ale `removeMember()` nie — atakujący może usunąć dowolnego członka z drzewa (mając tylko swoje `uid`).

Wzorzec weryfikacji:
```php
private function requireOwner(string $treeId): void
{
    $userId = Session::get('user_id');
    if (!$this->treeRepo->isOwner($treeId, $userId)) {
        $this->response->withFlash('error', 'Brak uprawnień.')->redirect('/trees');
    }
}
```

### 1.3 IDOR: `/trees/{id}/members/{uid}/remove` — czy `{uid}` należy do `{id}`?
**Status: POWAZNE — WYMAGA IMPLEMENTACJI**

Weryfikacja: czy `targetUserId` jest faktycznie memberem drzewa `treeId` przed usunięciem. Bez tego atakujący (właściciel drzewa A) mógłby wysłać POST do `/trees/A/members/{uid_from_tree_B}/remove` i usunąć kogoś z cudzego drzewa, jeśli `deleteMember()` nie filtruje po `tree_id`.

```sql
-- Bezpieczna wersja DELETE:
DELETE FROM tree_members
WHERE tree_id = :tid AND user_id = :uid AND role != 'owner'
-- role != 'owner' jako dodatkowy guard w SQL
```

### 1.4 Właściciel nie może usunąć sam siebie
**Status: POWAZNE — WYMAGA IMPLEMENTACJI**

Sprawdzenie: `$targetUserId !== $trees->owner_id` (pobieramy `owner_id` z tabeli `trees`, nie z sesji).

```php
$tree = $this->treeRepo->findById($treeId);
if ($tree->ownerId === $targetUserId) {
    throw new \RuntimeException('Nie można usunąć właściciela drzewa.');
}
```

Samo `role != 'owner'` w SQL nie wystarczy, jeśli owner_id mógłby mieć inną rolę w tree_members.

---

## 2. Token bezpieczeństwo

### 2.1 Token generowany kryptograficznie
**Status: PASS — prawidłowy wzorzec zaplanowany**

```php
$token = bin2hex(random_bytes(32)); // 64 znaki, 256 bitów entropii
```

`random_bytes()` używa CSPRNG — bezpieczne. Nie używać `md5(uniqid())`, `rand()`, `time()`.

### 2.2 Token jednorazowy (used_at)
**Status: PASS — kolumna used_at istnieje w tabeli**

```sql
-- W InvitationRepository::findByToken():
WHERE token = :token AND used_at IS NULL AND expires_at > NOW()
```

Obowiązkowe: po akceptacji ZAWSZE `UPDATE invitations SET used_at = NOW()`.  
Edge case: race condition — dwa równoległe kliknięcia. Rozwiązanie: transakcja lub `UPDATE ... WHERE used_at IS NULL` + sprawdzenie affected rows.

```php
// Bezpieczne accept() — atomowe:
$db->beginTransaction();
$affected = $db->execute(
    'UPDATE invitations SET used_at = NOW() WHERE token = :t AND used_at IS NULL',
    [':t' => $token]
);
if ($affected === 0) {
    $db->rollBack();
    throw new \RuntimeException('Link jest już wykorzystany.');
}
// INSERT tree_members...
$db->commit();
```

### 2.3 TTL 7 dni
**Status: PASS — zaplanowane poprawnie**

```php
$expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
```

Weryfikacja: zawsze sprawdzać `expires_at > NOW()` w SQL, nie tylko po stronie PHP.

### 2.4 Token w URL — logi serwera
**Status: DROBNE — do zaakceptowania dla MVP**

Token w URL `/invite/{token}` trafi do:
- Logów serwera Apache/nginx
- Historia przeglądarki zapraszanego

Zalecenie MVP: akceptowalne (standard branżowy — GitHub, Slack robią tak samo).  
Przyszłość: POST-based token exchange (token w formularzu, nie URL) — poziom paranoi.

---

## 3. CSRF

### 3.1 CSRF na wszystkich POST
**Status: KRYTYCZNE — WYMAGA WDROŻENIA na każdym formularzu**

Każda akcja zmieniająca stan musi mieć `verifyCsrf()`:
- POST `/trees/{id}/invite` — tak
- POST `/invite/{token}/accept` — tak (nawet strona publiczna)
- POST `/trees/{id}/members/{uid}/remove` — tak
- POST `/trees/{id}/members/{uid}/role` — tak

CSRF token w każdym formularzu:
```html
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
```

Wyjątek: GET `/invite/{token}` — nie zmienia stanu, nie potrzebuje CSRF.

---

## 4. Email Enumeration

### 4.1 Nie ujawniać czy email istnieje w systemie
**Status: POWAZNE — WYMAGA IMPLEMENTACJI**

Błędny wzorzec:
```php
// ZLE — ujawnia istnienie konta
if ($this->userRepo->emailExists($email)) {
    throw new \Exception('Użytkownik z tym emailem już jest zaproszony');
}
```

Poprawny wzorzec:
```php
// DOBRZE — zawsze ta sama odpowiedź
$this->invRepo->create(...); // tworzy zaproszenie bez względu na to czy email istnieje
$this->emailSvc->sendInvitation($email, ...); // wyśle jeśli istnieje, cicho pominie jeśli nie
// Lub: próbuj wysłać i nie loguj błędu dla nieistniejącego adresu

// Flash message zawsze:
withFlash('success', 'Jeśli ten adres e-mail jest zarejestrowany lub nowy, zaproszenie zostało wysłane.')
```

Wyjątek: można ujawnić "Użytkownik jest już członkiem tego drzewa" — to nie jest enumeration dotyczący rejestracji w systemie, tylko dotyczący już widocznego drzewa.

---

## 5. Rate Limiting

### 5.1 Rate limiting na POST `/trees/{id}/invite`
**Status: POWAZNE — WYMAGA IMPLEMENTACJI**

Bez limitu właściciel drzewa (lub atakujący z przejętym kontem) może masowo wysyłać e-maile spam do dowolnych adresów przez nasz serwer.

Implementacja w `InvitationService::invite()`:
```php
// Sprawdź rate limit per inviter (nie per IP — to legalna akcja zalogowanego usera)
$key = 'invite_' . $inviterId;
if ($this->authSvc->isRateLimited($ip, $key)) {
    throw new \RuntimeException('Wysłano zbyt wiele zaproszeń. Spróbuj za 15 minut.');
}
$this->authSvc->recordAttempt($ip, $key);
```

Limit: max 10 zaproszeń / 15 minut per właściciel (konfigurowalne przez stałą `INVITE_RATE_LIMIT`).

### 5.2 Rate limiting na GET `/invite/{token}`
**Status: DROBNE**

Bez uwierzytelnienia endpoint jest publicznie dostępny. Atakujący może brute-force'ować tokeny.  
Z `bin2hex(random_bytes(32))` = 64 hex = 2^256 przestrzeń — brute force praktycznie niemożliwy.  
Opcjonalnie: globalne rate limiting per IP (10 req/min) na tym endpoincie. Niska priorytet.

---

## 6. Wygasłe zaproszenia — cleanup

### 6.1 Brak automatycznego czyszczenia starych rekordów
**Status: DROBNE**

Wygasłe zaproszenia pozostają w `invitations` bez `used_at`. Nie stanowi zagrożenia (token i tak nie działa), ale tabela może rosnąć.

Zalecenie: cron `DELETE FROM invitations WHERE expires_at < NOW() - INTERVAL 30 DAY AND used_at IS NULL` — po MVP.

---

## 7. Widoczność danych członków

### 7.1 Lista emailów oczekujących zaproszeń — tylko dla właściciela
**Status: POWAZNE — WYMAGA IMPLEMENTACJI**

Strona `/trees/{id}/members` pokazuje emaile osób zaproszonych. Musi być dostępna **tylko dla właściciela**.

Editor i viewer nie mogą widzieć tej strony — mogłoby ujawnić emaile innych użytkowników.

### 7.2 Emaile istniejących członków
**Status: DROBNE — do decyzji**

Czy editor powinien widzieć imiona/emaile innych edytorów? Zależy od wymagań biznesowych. Rekomendacja MVP: tak (to drzewo rodzinne, nie korporacyjny system). Przyszłość: opcja "ukryj emaile".

---

## 8. RODO / Privacy by Design

### 8.1 Email jako PII w invitations
**Status: DROBNE — do zaakceptowania**

`invited_email` przechowywany w DB. Zgodne z RODO jeśli:
- Przechowywany tylko na czas trwania zaproszenia + rozsądny margines (30 dni po expire)
- Użytkownik może zażądać usunięcia (prawo do bycia zapomnianym)
- Nie przekazywany do podmiotów trzecich

Akcja po MVP: dodać do mechanizmu "usuń konto" kasowanie emaili z `invitations.invited_email`.

### 8.2 `invited_by` w tree_members — audyt
**Status: PASS**

Przechowywanie `invited_by` (kto zaprosił) jest uzasadnione celem (audyt) i nie narusza RODO jeśli dane są dostępne tylko administratorom i właścicielowi drzewa.

---

## 9. Podsumowanie — priorytety

| Priorytet | Problem | Akcja |
|-----------|---------|-------|
| KRYTYCZNE | IDOR: brak weryfikacji `isOwner` we wszystkich metodach | Implementuj `requireOwner()` helper |
| KRYTYCZNE | Brak CSRF na formularzach | Dodaj `verifyCsrf()` wszędzie |
| POWAZNE | Race condition w `accept()` (podwójne kliknięcie) | Użyj transakcji DB |
| POWAZNE | Email enumeration w `invite()` | Zawsze sukces w odpowiedzi |
| POWAZNE | Rate limiting na `/invite` | Max 10 zaproszeń / 15 min |
| POWAZNE | IDOR: `{uid}` z innego drzewa w `remove`/`role` | Guard `tree_id` w SQL DELETE/UPDATE |
| POWAZNE | Lista członków dostępna tylko dla ownera | Blokuj editorów/viewerów |
| DROBNE | Token w logach serwera | Akceptowalne dla MVP |
| DROBNE | Brak cleanup wygasłych | Cron po MVP |
| DROBNE | Emaile członków widoczne editorom | Decyzja biznesowa |
