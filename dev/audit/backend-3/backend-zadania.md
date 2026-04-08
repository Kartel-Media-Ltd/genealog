# Backend — zadania naprawcze (re-audit #3)

**Data:** 2026-04-08
**Format:** atomowe zadania 2-30 min, posortowane fazami
**Plan oparty na:** wynikach 3 agentów audytowych (OWASP, Compliance, Architecture)

---

## Status implementacji

- [ ] Faza 1 — BLOCKING (3 zadania, ~4-6h)
- [ ] Faza 2 — IMPORTANT (8 zadań, ~4-6h)
- [ ] Faza 3 — COMPLIANCE (3 zadania, ~8-10h)
- [ ] Faza 4 — NICE TO HAVE (10 zadań, ~2-3h)
- [ ] Faza 5 — odroczone (post-MVP) — nie robić teraz

---

## Faza 1 — BLOCKING (przed dowolnym deployem)
**Cel:** Eliminacja 3 krytycznych luk — SMTP injection, utrata danych userów, brak zgodności z RODO Art. 13.
**Szacowany czas:** ~4-6h

---

### ZAD-1.1 — K1: Sanityzacja nagłówków SMTP w EmailService
**Plik:** `src/Services/EmailService.php`
**Linie:** 42, 90 (miejsca składania `From:` header)
**Czas:** 10 min

Przed każdym użyciem `$fromName`, `$inviterName`, `$toEmail` w składaniu headerów dodaj sanityzację `\r\n\0`:

```php
// Na górze metody sendInvitation / sendPasswordReset:
$safeName  = str_replace(["\r", "\n", "\0"], '', $fromName);
$safeTo    = str_replace(["\r", "\n", "\0"], '', $toEmail);

// Użyj $safeName, $safeTo zamiast $fromName, $toEmail w header() i mail()
$headers = [
    'From: ' . $safeName . ' <' . $from . '>',
    'Reply-To: ' . $from,
    'MIME-Version: 1.0',
    // ...
];
```

**Weryfikacja:** test manualny — rejestracja z nazwą `Jan\r\nBcc: evil@example.com` → spróbuj wysłać invitation → header `From:` nie zawiera `Bcc:`.

---

### ZAD-1.2 — K2: Poprawa `findSoleOwnedTreeIds()` — transfer ownership zamiast usunięcia
**Plik:** `src/Services/AccountDeletionService.php`
**Linie:** 90-97
**Czas:** 1h (implementacja + test manualny)

#### Krok A: Zmień query na naprawdę sole-owned
```php
private function findSoleOwnedTreeIds(string $userId): array
{
    $rows = $this->db->fetchAll(
        "SELECT t.id FROM trees t
         WHERE t.owner_id = ?
           AND NOT EXISTS (
               SELECT 1 FROM tree_members tm
               WHERE tm.tree_id = t.id
                 AND tm.user_id != ?
                 AND tm.role IN ('owner','editor')
           )",
        [$userId, $userId]
    );
    return array_map(static fn(array $r): string => (string)$r['id'], $rows);
}
```

#### Krok B: Dodaj metodę `transferOwnership` dla drzew współdzielonych
```php
private function findSharedOwnedTreeIds(string $userId): array
{
    $rows = $this->db->fetchAll(
        "SELECT t.id FROM trees t
         WHERE t.owner_id = ?
           AND EXISTS (
               SELECT 1 FROM tree_members tm
               WHERE tm.tree_id = t.id
                 AND tm.user_id != ?
                 AND tm.role IN ('owner','editor')
           )",
        [$userId, $userId]
    );
    return array_map(static fn(array $r): string => (string)$r['id'], $rows);
}

private function transferOwnership(string $treeId, string $fromUserId): void
{
    // Pierwszy inny owner → następny editor
    $newOwner = $this->db->fetchOne(
        "SELECT user_id FROM tree_members
         WHERE tree_id = ? AND user_id != ?
         ORDER BY FIELD(role, 'owner', 'editor', 'viewer'), invited_at
         LIMIT 1",
        [$treeId, $fromUserId]
    );
    if ($newOwner === null) return; // nie powinno się zdarzyć po sharedOwnedTreeIds
    $this->db->execute(
        'UPDATE trees SET owner_id = ? WHERE id = ?',
        [$newOwner['user_id'], $treeId]
    );
    $this->db->execute(
        'UPDATE tree_members SET role = ? WHERE tree_id = ? AND user_id = ?',
        ['owner', $treeId, $newOwner['user_id']]
    );
}
```

#### Krok C: W `deleteAccount()` wywołaj transfer przed cascade delete:
```php
// W deleteAccount() — między krokami 1 i 2:
$sharedOwnedTreeIds = $this->findSharedOwnedTreeIds($userId);
foreach ($sharedOwnedTreeIds as $treeId) {
    $this->transferOwnership($treeId, $userId);
}
```

**Weryfikacja:** Test manualny:
1. User A tworzy drzewo, zaprasza User B jako editor.
2. User A usuwa swoje konto.
3. Drzewo pozostaje, User B jest teraz owner.
4. Drzewo gdzie User A jest jedynym ownerem — usunięte.

---

### ZAD-1.3 — K3: Privacy Policy, Terms of Service, Consent flow przy rejestracji
**Pliki:** nowe — `src/views/pages/privacy.php`, `src/views/pages/terms.php`, modyfikacja `src/views/pages/auth/register.php`, `public/index.php`, `src/Controllers/AuthController.php`
**Czas:** 3-4h (+ research merytoryczny treści)

#### Krok A: Utwórz `src/views/pages/privacy.php`
Szablon:
```php
<?php declare(strict_types=1); ?>
<main id="main-content" class="max-w-3xl mx-auto py-8 px-4">
    <h1 class="text-3xl font-bold mb-6">Polityka Prywatności</h1>
    <p class="text-muted-foreground mb-4">Ostatnia aktualizacja: <?= date('Y-m-d') ?></p>

    <h2 class="text-xl font-semibold mt-6 mb-3">1. Administrator danych</h2>
    <p>Administratorem danych jest [NAZWA], [ADRES], kontakt: [EMAIL].</p>

    <h2 class="text-xl font-semibold mt-6 mb-3">2. Cel przetwarzania (Art. 13 RODO)</h2>
    <ul class="list-disc list-inside">
        <li>Prowadzenie konta użytkownika (podstawa: art. 6(1)(b) — umowa)</li>
        <li>Zapewnienie bezpieczeństwa systemu (art. 6(1)(f) — uzasadniony interes)</li>
        <li>Marketing własny (art. 6(1)(a) — zgoda, opt-out w ustawieniach)</li>
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-3">3. Zakres danych</h2>
    <ul class="list-disc list-inside">
        <li>Dane konta: imię, email, hasło (zaszyfrowane)</li>
        <li>Dane genealogiczne dodane przez użytkownika (osoby, relacje, zdjęcia)</li>
        <li>Dane techniczne: adres IP, logi dostępu (retencja 3 lata — art. 5(1)(e))</li>
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-3">4. Twoje prawa (Art. 15-21 RODO)</h2>
    <ul class="list-disc list-inside">
        <li>Dostęp do danych — eksport w ustawieniach</li>
        <li>Sprostowanie — edycja profilu</li>
        <li>Usunięcie — strefa niebezpieczna w ustawieniach</li>
        <li>Ograniczenie przetwarzania</li>
        <li>Przenoszenie — eksport JSON + GEDCOM</li>
        <li>Sprzeciw wobec marketingu — toggle w ustawieniach</li>
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-3">5. Odbiorcy danych</h2>
    <p>Dane nie są przekazywane stronom trzecim (poza przypadkami zgody lub obowiązku prawnego).</p>

    <h2 class="text-xl font-semibold mt-6 mb-3">6. Okres przechowywania</h2>
    <p>Dane konta — do czasu jego usunięcia. Logi audytowe — 3 lata. Tokeny reset hasła — 24h po wygaśnięciu.</p>

    <h2 class="text-xl font-semibold mt-6 mb-3">7. Organ nadzorczy</h2>
    <p>Prawo wniesienia skargi do Prezesa UODO (uodo.gov.pl).</p>
</main>
```

#### Krok B: Utwórz `src/views/pages/terms.php`
Analogiczny szablon z regulaminem (zakres: rejestracja, dozwolone użycie, odpowiedzialność, zasady ws. danych osób trzecich w drzewach).

**UWAGA:** treść merytoryczna powinna być skonsultowana z radcą prawnym — ZAD-1.3 dostarcza szablon, pełna treść wymaga osobnego review.

#### Krok C: Dodaj trasy w `public/index.php`
Po trasach `/login`, `/register` (sekcja public):
```php
$router->get('/privacy', function() use ($response) {
    $response->view('pages/privacy', ['title' => 'Polityka prywatności']);
});
$router->get('/terms', function() use ($response) {
    $response->view('pages/terms', ['title' => 'Regulamin']);
});
```

#### Krok D: Dodaj checkbox consent w `src/views/pages/auth/register.php`
Przed przyciskiem "Utwórz konto":
```html
<div class="flex items-start gap-2 text-sm">
    <input type="checkbox" id="consent" name="consent" value="1" required
           class="mt-0.5">
    <label for="consent" class="text-muted-foreground">
        Akceptuję <a href="/terms" target="_blank" class="underline">Regulamin</a>
        i zapoznałem/am się z <a href="/privacy" target="_blank" class="underline">Polityką prywatności</a>.
    </label>
</div>
```

#### Krok E: Waliduj w `AuthController::processRegister()`
```php
if ((string)$this->request->getParam('consent', '') !== '1') {
    $this->response->withFlash('error', 'Akceptacja Regulaminu i Polityki prywatności jest wymagana.')
        ->redirect('/register');
}
```

**Weryfikacja:**
1. GET `/privacy` i `/terms` → 200
2. POST `/register` bez `consent=1` → redirect + flash error
3. POST `/register` z `consent=1` → rejestracja udana

---

## Faza 2 — IMPORTANT (ten sam sprint)
**Cel:** Zamknięcie wszystkich XS/S poważnych.
**Szacowany czas:** ~4-6h.

---

### ZAD-2.1 — P1: XSS w `trees/persons/index.php` — refactor na JSON + Alpine
**Plik:** `src/views/pages/trees/persons/index.php`
**Linia:** ~219
**Czas:** 30 min

Zastąp:
```html
x-show="'<?= addslashes(strtolower($person->fullName())) ?>'.includes(search.toLowerCase())"
```

Nowym podejściem — przekaż dane jako JSON:
```php
<?php
// na górze widoku, po $persons = ...:
$personsJson = json_encode(
    array_map(fn($p) => [
        'id'   => $p->id,
        'name' => $p->fullName(),
    ], $persons),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
?>

<div x-data='{ persons: <?= $personsJson ?>, search: "" }'>
    <input type="text" x-model="search" placeholder="Szukaj...">
    <template x-for="p in persons" :key="p.id">
        <div x-show="search === '' || p.name.toLowerCase().includes(search.toLowerCase())">
            <span x-text="p.name"></span>
        </div>
    </template>
</div>
```

**Weryfikacja:** Dodaj osobę z imieniem `<script>alert(1)</script>` → na liście widać literalnie tekst, nie wykonuje się skrypt.

---

### ZAD-2.2 — P2: Usuń `application/octet-stream` z GEDCOM MIME whitelist
**Plik:** `src/Controllers/GedcomController.php`
**Linia:** 22
**Czas:** 5 min

```php
// PRZED:
private const ALLOWED_MIME = ['text/plain', 'text/x-gedcom', 'application/octet-stream'];

// PO:
private const ALLOWED_MIME = ['text/plain', 'text/x-gedcom'];
```

Dodaj również walidację zawartości (pierwsza linia pliku) w metodzie `import()`:
```php
$content = file_get_contents($tmpPath);
if (!str_starts_with(ltrim($content), '0 HEAD')) {
    throw new \InvalidArgumentException('Plik nie jest prawidłowym plikiem GEDCOM (brak nagłówka HEAD).');
}
```

**Weryfikacja:** Upload pliku PDF lub EXE → rejected z komunikatem "Niedozwolony typ pliku".

---

### ZAD-2.3 — P3: Weryfikacja emailu przy akceptacji zaproszenia
**Plik:** `src/Services/InvitationService.php`
**Linie:** ~82-101 (metoda `accept`)
**Czas:** 20 min

Dodaj w metodzie `accept($token, $userId)` weryfikację:
```php
public function accept(string $token, string $userId): Tree
{
    $inv = $this->invRepo->findValidByToken($token);
    if ($inv === null) {
        throw new \RuntimeException('Zaproszenie wygasło lub nie istnieje.');
    }

    // ZAD-2.3: weryfikuj że zalogowany user odpowiada invited_email
    $user = $this->userRepo->findById($userId);
    if ($user === null || strtolower($user->email) !== strtolower((string)$inv['invited_email'])) {
        throw new \RuntimeException('To zaproszenie nie jest przeznaczone dla tego konta.');
    }

    // ... reszta logiki bez zmian
}
```

Jeśli `InvitationService` nie ma `UserRepository` w konstruktorze — dodaj go i zaktualizuj `public/index.php` gdzie tworzony jest service.

**Weryfikacja:**
1. User A zaprasza B (`b@example.com`)
2. User C (`c@example.com`) loguje się i próbuje wejść na `/invite/{token}/accept`
3. Powinien dostać flash error "To zaproszenie nie jest przeznaczone dla tego konta"

---

### ZAD-2.4 — P4: Rate limit dla `/register`
**Plik:** `src/Services/AuthService.php`
**Linie:** 25-30 (metoda `register`)
**Czas:** 5 min

Na początku metody `register` (PRZED walidacjami):
```php
public function register(string $name, string $email, string $password, ?string $ip = null): User
{
    // ZAD-2.4: rate limit chroni przed spamem rejestracyjnym
    if ($ip !== null && $this->isRateLimited($ip, 'register')) {
        throw new \RuntimeException('Zbyt wiele prób rejestracji. Spróbuj za 15 minut.');
    }

    // ... istniejąca walidacja
}
```

**Weryfikacja:** Wykonaj 6× rejestrację z tego samego IP → 6-ta powinna dostać RuntimeException.

---

### ZAD-2.5 — P6: `try/finally` dla tmpPath w GedcomController
**Plik:** `src/Controllers/GedcomController.php`
**Linia:** ~114-165 (metoda `import()`)
**Czas:** 5 min

Owin całą logikę parsowania w `try/finally`:
```php
public function import(): never
{
    $this->request->verifyCsrf();
    // ... rate limit + walidacja ...

    $tmpPath = sys_get_temp_dir() . '/gedcom-' . Uuid::generate() . '.ged';
    if (!move_uploaded_file($_FILES['gedcom']['tmp_name'], $tmpPath)) {
        $this->response->withFlash('error', 'Nie udało się przesłać pliku.')
            ->redirect('/trees/' . $treeId);
    }

    try {
        $result = $this->gedcomService->import($tmpPath, $treeId, $userId);
        // ... obsługa sukcesu
    } catch (\InvalidArgumentException $e) {
        $this->response->withFlash('error', $e->getMessage())
            ->redirect('/trees/' . $treeId);
    } catch (\Throwable $e) {
        error_log('GEDCOM import failed: ' . $e->getMessage());
        $this->response->withFlash('error', 'Nie udało się zaimportować pliku.')
            ->redirect('/trees/' . $treeId);
    } finally {
        if (is_file($tmpPath)) {
            @unlink($tmpPath);
        }
    }
}
```

**Weryfikacja:** Upload nieprawidłowego pliku (np. puste pliki) → `/tmp` nie zawiera resztek `gedcom-*.ged`.

---

### ZAD-2.6 — P7: Synchronizacja `session_version` z DB w ProfileController
**Plik:** `src/Controllers/ProfileController.php`
**Linie:** 85-87 (`changePassword`) i 123-126 (`changeEmail`)
**Czas:** 5 min

Zastąp lokalną inkrementację odczytem z DB:

```php
// PRZED:
$this->userRepo->incrementSessionVersion($userId);
Session::set('session_version', (int)Session::get('session_version', 0) + 1);

// PO:
$this->userRepo->incrementSessionVersion($userId);
$newVersion = $this->userRepo->getSessionVersion($userId) ?? 0;
Session::set('session_version', $newVersion);
```

Zastosuj w obu miejscach: `changePassword()` i `changeEmail()`.

**Weryfikacja:** Zmień hasło → kolejny request tego samego usera nie wylogowuje.

---

### ZAD-2.7 — P8: Migration — index na `persons(tree_id, gedcom_xref)`
**Nowy plik:** `migrations/012_persons_indexes.sql`
**Czas:** 5 min

```sql
-- Migration 012: performance indexes
-- Re-audit #3 P8: findByXref w GEDCOM import wykonuje N full-scans bez indeksu

ALTER TABLE persons
    ADD INDEX idx_persons_xref (tree_id, gedcom_xref);

-- Bonus: często używany query w liście drzew usera
ALTER TABLE tree_members
    ADD INDEX idx_tree_members_user (user_id);
```

Uruchomienie:
```bash
source .env.local && docker exec -i mariadb_docker mariadb \
  -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
  < migrations/012_persons_indexes.sql
```

**Weryfikacja:** `EXPLAIN SELECT * FROM persons WHERE tree_id = '...' AND gedcom_xref = '@I1@'` → `type: ref` (używa indeksu), nie `ALL`.

---

### ZAD-2.8 — P9: Depth cap dla `buildPersonHierarchy`
**Plik:** `src/Controllers/PersonController.php`
**Linie:** 63-122
**Czas:** 20 min

Dodaj parametr `$depth` do sygnatury + limit:
```php
private function buildPersonHierarchy(
    array $persons,
    string $rootId,
    int $depth = 0,
    int $maxDepth = 20
): array {
    if ($depth >= $maxDepth) {
        error_log("buildPersonHierarchy: depth limit reached for root={$rootId}");
        return ['id' => $rootId, 'truncated' => true];
    }

    // ... istniejąca logika z rekurencyjnymi wywołaniami:
    // $children[] = $this->buildPersonHierarchy($persons, $childId, $depth + 1, $maxDepth);
}
```

Dodaj też limit rozmiaru wejściowego w callerze:
```php
// Na początku index() / printList():
if (count($persons) > 5000) {
    $this->response->withFlash('warning', 'Drzewo zawiera ponad 5000 osób — widok uproszczony.')
        ->redirect('/trees/' . $treeId);
}
```

**Weryfikacja:** Import drzewa z 1000+ osobami + głębokie łańcuchy → strona ładuje się bez timeout / memory exhaustion.

---

## Faza 3 — COMPLIANCE (ten sam sprint lub następny)
**Cel:** Minimum viable RODO + NIS2.
**Szacowany czas:** ~8-10h.

---

### ZAD-3.1 — P10: Rozszerzenie DataExportService (RODO Art. 15)
**Plik:** `src/Services/DataExportService.php`
**Czas:** 3-4h

Dodaj do eksportu brakujące sekcje:

#### A) `invitations.json`
```php
$invitations = $this->db->fetchAll(
    'SELECT id, tree_id, invited_email, role, expires_at, used_at, created_at
     FROM invitations WHERE invited_by = ? ORDER BY created_at DESC',
    [$userId]
);
$zip->addFromString('invitations.json', json_encode($invitations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

#### B) `media-metadata.json`
```php
$media = $this->db->fetchAll(
    'SELECT m.id, m.tree_id, m.person_id, m.type, m.file_path, m.caption, m.year, m.created_at
     FROM media m
     INNER JOIN persons p ON m.person_id = p.id
     INNER JOIN trees t ON p.tree_id = t.id
     WHERE t.owner_id = ? OR EXISTS (SELECT 1 FROM tree_members tm WHERE tm.tree_id = t.id AND tm.user_id = ?)',
    [$userId, $userId]
);
$zip->addFromString('media-metadata.json', json_encode($media, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

#### C) Shared trees — osoby created_by user
```php
$sharedPersons = $this->db->fetchAll(
    'SELECT p.*, t.name AS tree_name
     FROM persons p
     INNER JOIN trees t ON p.tree_id = t.id
     WHERE p.created_by = ? AND t.owner_id != ?',
    [$userId, $userId]
);
$zip->addFromString('shared-tree-contributions.json', json_encode($sharedPersons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

#### D) Discovery settings & suggestions
```php
$discoverySuggestions = $this->db->fetchAll(
    "SELECT s.*
     FROM person_match_suggestions s
     INNER JOIN persons p ON s.person_id = p.id
     INNER JOIN trees t ON p.tree_id = t.id
     WHERE t.owner_id = ? OR EXISTS (SELECT 1 FROM tree_members tm WHERE tm.tree_id = t.id AND tm.user_id = ?)",
    [$userId, $userId]
);
$zip->addFromString('discovery-suggestions.json', json_encode($discoverySuggestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

Zaktualizuj `README.txt` w ZIP o nowe sekcje.

**Weryfikacja:** Pobierz ZIP → `unzip -l` → zawiera `invitations.json`, `media-metadata.json`, `shared-tree-contributions.json`, `discovery-suggestions.json`.

---

### ZAD-3.2 — P11: Implementacja RODO Art. 18 (right to restriction)
**Nowa migracja:** `migrations/013_user_restriction.sql`
**Pliki:** `src/Repositories/UserRepository.php`, `src/Controllers/SettingsController.php`, `src/Middleware/AuthMiddleware.php`, `src/views/pages/settings.php`
**Czas:** 4-5h

#### A) Migration
```sql
-- migrations/013_user_restriction.sql
ALTER TABLE users ADD COLUMN is_restricted TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN restricted_at TIMESTAMP NULL;
```

#### B) `UserRepository`
```php
public function setRestricted(string $id, bool $value): void
{
    $this->db->execute(
        'UPDATE users SET is_restricted = ?, restricted_at = ?, session_version = session_version + 1 WHERE id = ?',
        [(int)$value, $value ? date('Y-m-d H:i:s') : null, $id]
    );
}
```

#### C) `SettingsController::restrictAccount()` i `unrestrictAccount()`
```php
public function restrictAccount(): never
{
    $this->request->verifyCsrf();
    $userId = Session::get('user_id');
    $password = (string)$this->request->getParam('password', '');

    $row = $this->userRepo->findByIdWithHash($userId);
    if ($row === null || !password_verify($password, $row['password_hash'])) {
        $this->response->withFlash('error', 'Nieprawidłowe hasło.')->redirect('/settings?tab=danger');
    }

    $this->userRepo->setRestricted($userId, true);
    Session::destroy();
    $this->response->withFlash('success', 'Twoje konto zostało zawieszone. Możesz je przywrócić logując się ponownie.')
        ->redirect('/login');
}
```

#### D) `AuthController::processLogin()` — obsługa `is_restricted`
Dodaj po udanej weryfikacji hasła, przed utworzeniem sesji:
```php
if ((int)$row['is_restricted'] === 1) {
    // Pytaj czy chce unrestrict
    Session::set('pending_unrestrict_user_id', $row['id']);
    $this->response->redirect('/settings/unrestrict');
}
```

#### E) `/settings/unrestrict` endpoint — confirm + unrestrict
Formularz z `pending_unrestrict_user_id` → POST → `setRestricted(false)` → login.

#### F) UI w `settings.php` → Strefa niebezpieczna
Dodaj sekcję "Zawieś konto" przed "Usuń konto".

**Weryfikacja:**
1. Zawieszenie konta → logout + flash
2. Próba logowania → redirect na `/settings/unrestrict`
3. Confirm → konto aktywne, zalogowany

---

### ZAD-3.3 — P13: Dokumentacja backup/recovery (NIS2 Art. 21.2)
**Nowy plik:** `docs/operations/backup.md`
**Czas:** 2h

Szablon (dostosuj do konkretnej infrastruktury):

```markdown
# Backup & Recovery Plan — Genealog

**Właściciel:** [DPO / Head of Ops]
**Ostatnia aktualizacja:** 2026-04-08
**Review cadence:** kwartalnie

## Cele
- **RTO (Recovery Time Objective):** 4 godziny
- **RPO (Recovery Point Objective):** 24 godziny

## Strategia 3-2-1
- **3 kopie danych:** primary DB + local backup + offsite backup
- **2 media:** SSD (local) + S3-compatible storage (offsite)
- **1 offsite:** kopia zapasowa w innej lokalizacji geograficznej (EU-West)

## Harmonogram backupów

### MariaDB
- **Full backup:** codziennie o 2:00 UTC (`mariadb-dump --single-transaction`)
- **Binary logs:** continuous, rotacja 7 dni
- **Retention:** 30 dni (daily), 12 miesięcy (monthly full)

Skrypt: `bin/backup-db.sh` (TODO: utworzyć)

### Filesystem — storage/media/
- **rsync:** codziennie o 3:00 UTC → offsite S3
- **Retention:** 30 dni

### Configuration
- `config/config.php`, `.env.local`, migrations — w git repo (już backup)

## Procedura recovery

### DB failure
1. Zatrzymaj aplikację (`docker stop genealog`)
2. Przywróć najnowszy full backup: `mariadb -u root -p < backup-YYYY-MM-DD.sql`
3. Zastosuj binary logs do punktu przed awarią: `mariadb-binlog --start-datetime="..." | mariadb -u root -p`
4. Zweryfikuj integralność: `SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM trees;`
5. Uruchom aplikację
6. Test smoke: login + lista drzew + odczyt osoby

### Filesystem corruption
1. Zatrzymaj aplikację
2. `rsync` z S3 backup → `storage/media/`
3. Sprawdź permissions: `chown -R www-data:www-data storage/`
4. Uruchom aplikację

### Total loss (rebuild from scratch)
1. Provision nowy serwer (IaC: `infrastructure/terraform/` — TODO)
2. Zainstaluj stack (PHP 8.2, MariaDB, nginx)
3. Clone repo, composer install
4. Przywróć DB (full backup)
5. Przywróć filesystem (S3)
6. Uruchom migracje (weryfikacja schema consistency)
7. Test smoke

## Testy DR

- **Kwartalnie:** restore test na staging env (nie-produkcyjny)
- **Rocznie:** pełny DR drill (symulacja failover)
- **Protokół:** log w `docs/operations/dr-tests.log`

## Monitoring

- Alert gdy ostatni backup >36h temu
- Alert gdy binary log gap >1h
- Alert gdy restore test fail
- Dashboard: `[Grafana URL TBD]`

## Kontakty
- **Ops lead:** [EMAIL]
- **DPO:** [EMAIL]
- **MariaDB support:** [kontakt serwisowy]
- **Cloud provider:** [AWS/Hetzner/etc.]
```

Zaktualizuj `docs/security/incident-response.md:77`:
```markdown
2. Przywróć z najnowszego backup'u wg procedury w [docs/operations/backup.md](../operations/backup.md).
```

---

## Faza 4 — NICE TO HAVE (ten sam lub następny sprint)
**Cel:** Jakość, WCAG, drobne poprawki compliance.
**Szacowany czas:** ~2-3h.

---

### ZAD-4.1 — D1: Komentarze `// safe: int cast` przy interpolowanym LIMIT
**Pliki:** `src/Repositories/TreeRepository.php:183`, `src/Repositories/AdminRepository.php:42,72,98`
**Czas:** 5 min

Przed każdym `"... LIMIT {$lim}"` dodaj:
```php
// safe: $lim to int cast z (int) powyżej, PDO nie wspiera bound LIMIT params
```

---

### ZAD-4.2 — D2: Walidacja formatu pending_invitation tokena
**Plik:** `src/Controllers/InvitationController.php:106,128`
**Czas:** 5 min

Przed `Session::set('pending_invitation', $token)`:
```php
if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    $this->response->withFlash('error', 'Nieprawidłowy token zaproszenia.')->redirect('/login');
}
Session::set('pending_invitation', $token);
```

---

### ZAD-4.3 — D3: MIME boundary w EmailService → `bin2hex(random_bytes(16))`
**Plik:** `src/Services/EmailService.php:40,89`
**Czas:** 3 min

```php
// PRZED:
$boundary = md5(uniqid((string)time(), true));

// PO:
$boundary = bin2hex(random_bytes(16));
```

---

### ZAD-4.4 — D4: Walidacja `sortBy` w PersonController
**Plik:** `src/Controllers/PersonController.php` (metoda listująca osoby)
**Czas:** 5 min

Przed przekazaniem do repozytorium:
```php
$allowedSort = ['last_name', 'first_name', 'birth_date', 'created_at'];
$sortBy = (string)$this->request->getParam('sort', 'last_name');
if (!in_array($sortBy, $allowedSort, true)) {
    $sortBy = 'last_name';
}
```

---

### ZAD-4.5 — D5: Dodaj kolumnę `invited_by` do migration `tree_members` lub nowa migracja
**Plik:** nowa migracja `migrations/014_tree_members_invited_by.sql`
**Czas:** 3 min

```sql
-- Kolumna jest używana w InvitationRepository::insertMember(), ale nie było jej w oryginalnej migracji 002.
ALTER TABLE tree_members
    ADD COLUMN IF NOT EXISTS invited_by CHAR(36) NULL,
    ADD CONSTRAINT fk_tree_members_invited_by FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL;
```

---

### ZAD-4.6 — D6: Flash messages przez `Session::get()` zamiast `$_SESSION`
**Plik:** `src/views/molecules/flash-messages.php:28-54`
**Czas:** 10 min

Zastąp:
```php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashes = $_SESSION['flashes'] ?? [];
unset($_SESSION['flashes']);
```

Nowym:
```php
use App\Core\Session;
$flash = Session::get('flash');
Session::delete('flash');
$flashes = Session::get('flashes', []);
Session::delete('flashes');
```

Jeśli `Session::delete` nie istnieje — dodaj do `src/Core/Session.php`:
```php
public static function delete(string $key): void
{
    unset($_SESSION[$key]);
}
```

---

### ZAD-4.7 — D8: Podstawowa infrastruktura deployment
**Pliki:** nowe — `.env.example`, `Dockerfile`, `docker-compose.yml`, `src/Controllers/ApiController.php` (health)
**Czas:** 30 min (bez pełnego Docker setup)

#### A) `.env.example`
```bash
# Database
DATABASE_HOST=localhost
DATABASE_PORT=3306
DATABASE_NAME=genealog
DATABASE_USER=genealog
DATABASE_PASSWORD=changeme

# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://genealog.example.com
APP_KEY=__generate_with_base64_random_32__

# Mail
MAIL_FROM_ADDRESS=noreply@genealog.example.com
MAIL_FROM_NAME="Genealog"

# Rate limits
RATE_LIMIT_ATTEMPTS=5
RATE_LIMIT_WINDOW=900
```

#### B) Health endpoint w `ApiController`
```php
public function health(): never
{
    try {
        $db = \App\Core\Database::getInstance();
        $db->fetchOne('SELECT 1');
        $status = ['status' => 'ok', 'db' => 'ok', 'ts' => date('c')];
        $code = 200;
    } catch (\Throwable $e) {
        $status = ['status' => 'fail', 'db' => 'fail', 'error' => 'db unreachable'];
        $code = 503;
    }

    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($status);
    exit;
}
```

Trasa w `public/index.php` (public, bez middleware):
```php
$router->get('/health', [new ApiController($request, $response), 'health']);
```

**Weryfikacja:** `curl http://localhost:8080/health` → JSON z `status: ok`.

---

### ZAD-4.8 — D9: `session_version` check — zwiększ częstotliwość
**Plik:** `src/Core/Session.php`
**Czas:** 15 min

Obecne rozwiązanie sprawdza `session_version` losowo w 1% requestów. Zmień na:
- Sprawdź ZAWSZE dla chronionych tras (middleware robi to)
- Dodaj cache w sesji: `last_version_check_at`, nie sprawdzaj DB częściej niż co 30s

Albo prościej — usuń losowy 1% check, bo już `AuthMiddleware` sprawdza per request. Wysokość czynnego sprawdzenia to decyzja architektoniczna.

**Rekomendacja:** zostaw per-request sprawdzenie w `AuthMiddleware` (już tak jest), **usuń** losowy 1% check z `Session::start()` jeśli istnieje (może być redundancja, nie widzę w kodzie).

Zweryfikuj `grep -n "random_int" src/Core/Session.php` — jeśli brak, zadanie NIE dotyczy aktualnego kodu i można pominąć.

---

### ZAD-4.9 — D10: WCAG — skip link w AppLayout
**Plik:** `src/views/templates/AppLayout.php`
**Czas:** 5 min

Przed `<header>` dodaj:
```html
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50
          focus:bg-background focus:text-foreground focus:px-4 focus:py-2 focus:rounded
          focus:shadow-lg focus:ring-2 focus:ring-primary">
    Przejdź do treści
</a>
```

Upewnij się że `<main>` ma `id="main-content"` (lub dodaj).

**Weryfikacja:** Załaduj stronę → Tab → pierwsza widoczna opcja = "Przejdź do treści".

---

### ZAD-4.10 — D11: WCAG — `aria-live` na dropdown notyfikacji
**Plik:** `src/views/templates/AppLayout.php:148-182`
**Czas:** 5 min

Dodaj do containera listy notyfikacji:
```html
<div x-show="open"
     role="dialog"
     aria-label="Powiadomienia"
     aria-live="polite"
     aria-atomic="false">
    <!-- ...lista notyfikacji... -->
</div>
```

---

### ZAD-4.11 — D12: Toggle marketingu w `settings.php` (już istnieje endpoint)
**Plik:** `src/views/pages/settings.php`
**Czas:** 15 min

Backend już ma `SettingsController::updateNotifications()` i `UserRepository::updateEmailNotifications()`. Widok settings.php ma już tab Powiadomienia z togglem. Zweryfikuj czy działa i czy informacja o RODO Art. 21 jest widoczna:

Dodaj pod togglem:
```html
<p class="mt-3 text-xs text-muted-foreground">
    Wyłączenie powiadomień email to realizacja Twojego prawa do sprzeciwu wobec marketingu
    (art. 21 RODO). W każdym momencie możesz ponownie włączyć powiadomienia.
</p>
```

Jeśli toggle już jest i działa — tylko dodać komentarz RODO. Jeśli nie — dodać całą sekcję.

---

## Faza 5 — ODROCZONE (post-MVP, nie w tym cyklu)

Nie do implementacji w `/ultra-workaholic dev/audit/backend-3`. Wymagają osobnych decyzji lub iteracji.

- **P5** — CSP nonce-based (L, refactor layoutów)
- **P12** — DPIA (wymaga audytu prawnego)
- **P14** — MFA TOTP (L, nowy feature)
- **D7** — Deduplikacja controllers w routingu (razem z Container.php backend-2 Faza 3)
- **D13** — Rejestr Czynności Przetwarzania (wymaga DPO)

---

## Podsumowanie

| Faza | Zadań | Czas | Typ |
|------|-------|------|-----|
| **Faza 1 BLOCKING** | 3 | ~4-6h | krytyczne — przed deployem |
| **Faza 2 IMPORTANT** | 8 | ~4-6h | poważne — ten sprint |
| **Faza 3 COMPLIANCE** | 3 | ~8-10h | RODO/NIS2 minimum |
| **Faza 4 NICE TO HAVE** | 11 | ~2-3h | jakość, WCAG, drobne |
| **RAZEM** | **25** | **~18-25h** | |

**Quick wins (< 2h łącznie):** ZAD-1.1, ZAD-2.2, ZAD-2.4, ZAD-2.5, ZAD-2.6, ZAD-2.7, ZAD-4.1-4.6, ZAD-4.9, ZAD-4.10

**Po implementacji Fazy 1+2 → re-audit #4** (`dev/audit/backend-4/`) dla weryfikacji.
