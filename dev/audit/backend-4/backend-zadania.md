# Backend — zadania naprawcze (re-audit #4)

**Data:** 2026-04-08
**Format:** atomowe zadania 2-30 min, posortowane fazami
**Plan oparty na:** weryfikacji backend-3 + audycie nowego modułu Discovery Sources

---

## Status implementacji

- [ ] Faza 1 — BLOCKING (2 zadania, ~4-6h) — **RODO critical**
- [ ] Faza 2 — IMPORTANT (11 zadań, ~6-8h) — **Discovery hardening + RODO**
- [ ] Faza 3 — NICE TO HAVE (14 zadań, ~3-4h) — **Quality + WCAG**
- [ ] Faza 4 — ODROCZONE (post-MVP) — nie robić teraz

---

## Faza 1 — BLOCKING (przed dowolnym deployem)
**Cel:** eliminacja 2 krytycznych luk RODO
**Szacowany czas:** ~4-6h

---

### ZAD-1.1 — K1: Persystencja zgody na regulamin (RODO Art. 7(1))
**Pliki:** nowa migracja `015_user_consent.sql`, `src/Repositories/UserRepository.php`, `src/Services/AuthService.php`, `src/Controllers/AuthController.php`
**Czas:** 1h

#### Krok A: Migration
Utwórz `migrations/015_user_consent.sql`:
```sql
-- Migration 015: RODO Art. 7(1) — persystencja zgody na regulamin
-- Bez tego administrator nie może udowodnić że user zaakceptował regulamin — naruszenie Art. 7(1) RODO.

ALTER TABLE users
    ADD COLUMN terms_accepted_at TIMESTAMP NULL AFTER created_at,
    ADD COLUMN terms_version VARCHAR(20) NULL AFTER terms_accepted_at;

-- Istniejących userów oznacz jako "grandfathered" (zgoda implicit z faktu korzystania):
UPDATE users
SET terms_accepted_at = created_at,
    terms_version = 'legacy-pre-2026-04-08'
WHERE terms_accepted_at IS NULL;
```

#### Krok B: Aktualna wersja regulaminu w config
Dodaj do `config/config.php`:
```php
define('TERMS_VERSION', '2026-04-08');
```

#### Krok C: `UserRepository::create`
```php
public function create(
    string $id,
    string $email,
    string $passwordHash,
    string $name,
    string $locale = 'pl',
    ?string $termsVersion = null,
): bool {
    $affected = $this->db->execute(
        'INSERT INTO users (id, email, password_hash, name, locale, terms_accepted_at, terms_version)
         VALUES (:id, :email, :hash, :name, :locale, :accepted_at, :version)',
        [
            ':id' => $id, ':email' => $email, ':hash' => $passwordHash,
            ':name' => $name, ':locale' => $locale,
            ':accepted_at' => $termsVersion !== null ? date('Y-m-d H:i:s') : null,
            ':version' => $termsVersion,
        ]
    );
    return $affected === 1;
}
```

#### Krok D: `AuthService::register`
```php
public function register(string $name, string $email, string $password, ?string $ip = null): User
{
    // ... existing validation ...

    $id   = Uuid::generate();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $version = defined('TERMS_VERSION') ? TERMS_VERSION : '2026-04-08';

    $this->userRepo->create($id, $email, $hash, $name, 'pl', $version);
    // ...
}
```

**Weryfikacja:**
1. Utwórz konto przez formularz
2. `SELECT terms_accepted_at, terms_version FROM users WHERE email = 'test@example.com';` — powinno mieć aktualną datę i wersję
3. Istniejące konta (przed migracją) mają `legacy-pre-2026-04-08`

---

### ZAD-1.2 — K2: Privacy Policy + Terms — rozszerz o Discovery Sources
**Pliki:** `src/views/pages/privacy.php`, `src/views/pages/terms.php`
**Czas:** 3-4h (+ research prawny)

#### Krok A: Zmień sekcję 8 w `privacy.php`
Znajdź:
```markdown
## 8. Przekazywanie danych poza EOG
Dane nie są przekazywane poza Europejski Obszar Gospodarczy.
```

Zastąp:
```markdown
## 8. Przekazywanie danych poza EOG

Domyślnie dane osobowe nie są przekazywane poza Europejski Obszar Gospodarczy.
Infrastruktura Genealog znajduje się w [LOKALIZACJA SERWERA] (EU).

**Wyjątek: Zewnętrzne rejestry genealogiczne (Discovery Sources)**
Na Twoje świadome żądanie, Genealog może inicjować wyszukiwanie osób
w zewnętrznych rejestrach:
- **FamilySearch** (USA) — transfer danych poza EOG. Podstawa transferu:
  Standardowe Klauzule Umowne (Standard Contractual Clauses) zgodnie z decyzją
  wykonawczą Komisji Europejskiej 2021/914.
- **Geneteka** (Polska) — bez transferu poza EOG.
- **Local / Cross-tree** — wewnętrzne wyszukiwanie w bazie Genealog.

Wysyłane dane: imię, nazwisko, rok urodzenia osoby której szukasz.
Możesz w każdym momencie wyłączyć konkretne źródła w ustawieniach
"Discovery" dla Twoich drzew.
```

#### Krok B: Dodaj sekcję 9 o Discovery Sources
```markdown
## 9. Zewnętrzne rejestry (Discovery Sources)

Genealog umożliwia wyszukiwanie osób w zewnętrznych bazach genealogicznych.
Obsługiwane rejestry:

| Rejestr | Lokalizacja | Transfer | Zgoda wymagana |
|---------|-------------|----------|----------------|
| Local / Cross-tree | Genealog (EU) | Nie | Opt-in Discovery |
| Geneteka (PTG) | PL | Nie | Opt-in Discovery |
| FamilySearch | USA | **TAK (SCC)** | Per-source opt-in |

Wyszukiwanie w zewnętrznym rejestrze następuje wyłącznie po:
1. Włączeniu globalnej zgody Discovery w ustawieniach Twojego drzewa
2. Świadomym wyborze "Szukaj w [rejestr]" dla konkretnej osoby

Wyniki są prezentowane Tobie — nie są automatycznie importowane.
Import danych z zewnętrznego rejestru wymaga Twojej decyzji (klik "Importuj").
```

#### Krok C: Analogiczne sekcje w `terms.php`
Dodaj paragraf w §2 Usługi lub nowy §11 Zewnętrzne integracje.

#### Krok D: Update tabela "Twoje prawa" w privacy.php
Dodaj wiersz:
- **Prawo do sprzeciwu wobec transferu do państw trzecich** — wyłączenie
  FamilySearch w ustawieniach Discovery blokuje wszelkie transfery do USA.

**UWAGA:** Treść merytoryczna wymaga weryfikacji przez radcę prawnego.
Szablon dostarcza strukturę — pełna treść zależy od ustaleń biznesowych.

**Weryfikacja:**
1. GET `/privacy` → sekcje 8 i 9 widoczne
2. Sekcja 9 zawiera tabelę rejestrów
3. Brak "placeholdera" `[LOKALIZACJA SERWERA]` — uzupełnij do lokalizacji faktycznej
4. Link ze formularza rejestracji działa

---

## Faza 2 — IMPORTANT (ten sam sprint)
**Cel:** DoS mitigation + RODO enforcement + architektura
**Szacowany czas:** ~6-8h

---

### ZAD-2.1 — P1: Batch event dla bulk GEDCOM import (DoS mitigation)
**Pliki:** `src/Services/GedcomService.php`, `src/Services/Discovery/MatchingService.php`, `public/index.php`
**Czas:** 2h

**Problem:** `EventDispatcher::on('person.created', ...)` w bootstrap wywołuje `MatchingService::findAndNotifyMatches` synchronicznie. Import 5000 osób = 5000 wywołań matching = DoS.

**Fix krótkoterminowy:** Disable per-person matching podczas importu GEDCOM, emit batch event po zakończeniu.

#### Krok A: `GedcomService::import` — flag + batch event
```php
public function import(string $treeId, string $filePath, string $userId): ImportResult
{
    // ... existing logic ...

    // Flaga wyłącza per-person matching
    $GLOBALS['_gedcom_import_in_progress'] = true;
    try {
        foreach ($parsedPersons as $p) {
            // normalny flow z dispatch('person.created')
            $this->personRepo->insert($p);
            EventDispatcher::dispatch('person.created', $p, $userId);
        }
    } finally {
        unset($GLOBALS['_gedcom_import_in_progress']);
    }

    // Po zakończeniu importu — 1× batch event dla całego drzewa
    EventDispatcher::dispatch('tree.imported', $treeId, $userId, $result->personsCount);

    return $result;
}
```

#### Krok B: Handler w `public/index.php`
```php
EventDispatcher::on('person.created', static function ($person, $userId) use ($globalIndexSvc, $matchingSvc) {
    // Zawsze indeksuj (tanie)
    $globalIndexSvc->indexPerson($person);

    // Matching TYLKO poza kontekstem GEDCOM bulk import
    if (empty($GLOBALS['_gedcom_import_in_progress'] ?? false)) {
        $matchingSvc->findAndNotifyMatches($person, $userId);
    }
});

EventDispatcher::on('tree.imported', static function ($treeId, $userId, $count) use ($matchingSvc) {
    // Batch matching dla całego drzewa po imporcie
    // (zamiast N × findAndNotifyMatches, 1× reindexAndMatch)
    try {
        $matchingSvc->matchTreeAfterImport($treeId, $userId);
    } catch (\Throwable $e) {
        error_log('tree.imported matching failed: ' . $e->getMessage());
    }
});
```

#### Krok C: Nowa metoda `MatchingService::matchTreeAfterImport`
```php
public function matchTreeAfterImport(string $treeId, string $userId): void
{
    // Sprawdź opt-in RAZ (P2 fix)
    $tree = $this->treeRepo->findById($treeId);
    if ($tree === null || !$this->userRepo->isDiscoveryOptedIn($tree->ownerId)) {
        return;
    }

    // Wybierz tylko niedawno dodane osoby z drzewa (top N żeby nie spamować)
    $persons = $this->personRepo->findByTree($treeId);
    $recentLimit = 100; // max N matching wywołań po imporcie
    $persons = array_slice($persons, 0, $recentLimit);

    foreach ($persons as $person) {
        if ($person->isLiving) continue;
        try {
            $this->findAndNotifyMatches($person, $userId);
        } catch (\Throwable $e) {
            error_log('matchTreeAfterImport failed for person ' . $person->id . ': ' . $e->getMessage());
        }
    }
}
```

**Weryfikacja:** Upload pliku GEDCOM z 1000 osobami. W logach: brak setek `[CrossTreeMatchSource]` entries. Jedno wywołanie `matchTreeAfterImport` po zakończeniu.

---

### ZAD-2.2 — P2: `isDiscoveryOptedIn` check w findAndNotifyMatches (Art. 7(3))
**Plik:** `src/Services/Discovery/MatchingService.php`
**Czas:** 5 min (quick win)

W metodzie `findAndNotifyMatches`, po pobraniu `$tree` a przed wywołaniem sources:
```php
public function findAndNotifyMatches(Person $person, string $userId): void
{
    if ($person->isLiving) return;

    $tree = $this->treeRepo->findById($person->treeId);
    if ($tree === null) return;

    // ZAD-2.2 (P2): RODO Art. 7(3) — jeśli user wycofał zgodę,
    // nie wywołuj cross-tree matching (mimo że persona może być w global_person_index)
    if (!$this->userRepo->isDiscoveryOptedIn($tree->ownerId)) {
        return;
    }

    // ... dalsza logika: sources, suggestions, notifications
}
```

Jeśli `UserRepository` nie jest w konstruktorze `MatchingService` — dodaj.

**Weryfikacja:** User A włącza discovery, tworzy osobę → match w cross-tree (OK). User A wyłącza discovery → tworzy kolejną osobę → **brak** cross-tree match.

---

### ZAD-2.3 — P3: reindexTree N+1 + chunking + timeout
**Plik:** `src/Services/Discovery/GlobalIndexService.php`
**Czas:** 30 min

#### Current (problematic)
```php
public function reindexTree(string $treeId): void
{
    $persons = $this->personRepo->findByTree($treeId);
    foreach ($persons as $person) {
        $this->indexPerson($person);  // każda iteracja: SELECT tree + SELECT user
    }
}
```

#### Fix
```php
public function reindexTree(string $treeId): void
{
    @set_time_limit(300);

    // Pobierz tree + opt-in RAZ
    $tree = $this->treeRepo->findById($treeId);
    if ($tree === null) return;
    $optedIn = $this->userRepo->isDiscoveryOptedIn($tree->ownerId);

    if (!$optedIn) {
        // Jeśli user wyłączył discovery — unindex wszystko z tego drzewa
        $this->unindexTree($treeId);
        return;
    }

    $persons = $this->personRepo->findByTree($treeId);
    $chunkSize = 100;
    $chunks = array_chunk($persons, $chunkSize);

    foreach ($chunks as $chunk) {
        foreach ($chunk as $person) {
            try {
                $this->indexPersonInternal($person, $tree, $optedIn);
            } catch (\Throwable $e) {
                error_log('reindexTree: failed person ' . $person->id . ': ' . $e->getMessage());
            }
        }
        // Opcjonalnie: flush output dla UI progress (jeśli running w request)
    }
}

/**
 * Indeksuje osobę z pre-pobranym tree + optedIn — eliminuje N+1.
 */
private function indexPersonInternal(Person $person, Tree $tree, bool $optedIn): void
{
    // Przenieś logikę z indexPerson ale bez SELECT trees/users
    if (!$optedIn || $person->isLiving || $person->visibility === 'private') {
        $this->unindexPerson($person->id);
        return;
    }
    // ... reszta logiki
}
```

**Weryfikacja:** Drzewo z 1000 osobami — reindex kończy się w <10s zamiast 60s+.

---

### ZAD-2.4 — P4: Usuń `userId` z error_log w CrossTreeMatchSource
**Plik:** `src/Services/Discovery/Sources/CrossTreeMatchSource.php:172-177`
**Czas:** 3 min (quick win)

```php
// PRZED:
error_log(sprintf(
    '[CrossTreeMatchSource] returned %d matches for user=%s (hash=%s)',
    count($out), $context->currentUserId, $hash !== null ? 'yes' : 'no'
));

// PO:
error_log(sprintf(
    '[CrossTreeMatchSource] %d matches (hash=%s)',
    count($out), $hash !== null ? 'yes' : 'no'
));
```

---

### ZAD-2.5 — P5: Dodaj `getTimeoutSeconds()` do MatchSourceInterface
**Pliki:** `src/Services/Discovery/MatchSourceInterface.php` + wszystkie implementacje
**Czas:** 45 min

#### Krok A: Interface
```php
interface MatchSourceInterface
{
    public const SOURCE_LOCAL      = 'local';
    public const SOURCE_CROSS_TREE = 'cross_tree';
    public const SOURCE_EXTERNAL   = 'external';

    public function getName(): string;
    public function isAvailable(): bool;

    /**
     * Timeout w sekundach dla wywołania search().
     * Local/internal sources: 5s (default).
     * External HTTP sources: 10-15s.
     * Używane przez MatchingService do wymuszenia stream_context timeout.
     */
    public function getTimeoutSeconds(): int;

    public function search(SearchCriteria $criteria, SearchContext $context): array;
}
```

#### Krok B: Implementacje — domyślny 5s
```php
// LocalTreeMatchSource, CrossTreeMatchSource:
public function getTimeoutSeconds(): int { return 5; }

// FamilySearchMatchSource:
public function getTimeoutSeconds(): int { return 10; }

// GenetykaMatchSource:
public function getTimeoutSeconds(): int { return 8; }
```

#### Krok C: MatchingService — wymuszenie timeoutu
Dla obecnego stanu (stuby) wystarczy przechowywanie wartości. Przy implementacji HTTP sources: stream_context_create z timeout.

---

### ZAD-2.6 — P6: DataExport — tree_members + password_resets (Art. 15)
**Plik:** `src/Services/DataExportService.php`
**Czas:** 15 min (quick win)

Po sekcji `discovery-suggestions.json` dodaj:

```php
// ZAD-2.6 (P6): RODO Art. 15 — eksport członkostwa w drzewach + historia resetów hasła

// Membership w drzewach (nie-sole-owner — jako editor/viewer)
$memberships = $this->db->fetchAll(
    'SELECT tm.tree_id, tm.role, tm.invited_at, tm.invited_by, t.name AS tree_name
     FROM tree_members tm
     INNER JOIN trees t ON t.id = tm.tree_id
     WHERE tm.user_id = ?',
    [$userId]
);
$zip->addFromString('tree-memberships.json', json_encode(
    $memberships,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
));

// Historia resetów hasła (bez kolumny token — secret)
$passwordResets = $this->db->fetchAll(
    'SELECT id, expires_at, used_at, ip, created_at
     FROM password_resets WHERE user_id = ? ORDER BY created_at DESC',
    [$userId]
);
$zip->addFromString('password-resets.json', json_encode(
    $passwordResets,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
));
```

Aktualizuj README.txt wewnątrz eksportu:
```text
- tree-memberships.json   — drzewa gdzie jesteś członkiem (editor/viewer) z informacją kto zaprosił
- password-resets.json    — historia żądań resetu hasła (bez samego tokenu, tylko metadata)
```

---

### ZAD-2.7 — P7: Audit log w Discovery search
**Pliki:** `src/Services/Discovery/Sources/GenetykaMatchSource.php`, `src/Services/Discovery/Sources/FamilySearchMatchSource.php`
**Czas:** 30 min

Na początku `search()` (przed zwrotem wyników):
```php
public function search(SearchCriteria $criteria, SearchContext $context): array
{
    if (!$this->isAvailable()) {
        return [];
    }

    // ZAD-2.7 (P7): RODO Art. 30 — audit log dla każdego wywołania external source
    $this->discoveryRepo->logAudit(
        userId: $context->currentUserId,
        action: 'discovery_search',
        sourceType: self::getName(),
        sourceId: sha1(serialize([
            'first_name' => $criteria->firstName,
            'last_name'  => $criteria->lastName,
            'birth_year' => $criteria->birthYear,
        ])),
        targetPersonId: null,
        targetTreeId: $context->treeId ?? null,
        ip: $context->ip,
    );

    // ... faktyczny search
}
```

Wymaga wstrzyknięcia `DiscoveryRepository` do konstruktora tych klas.

---

### ZAD-2.8 — P8: Uzupełnij placeholdery Privacy Policy + Terms
**Pliki:** `src/views/pages/privacy.php`, `src/views/pages/terms.php`, `src/Services/DataExportService.php:180`, nowy `config/company.php`
**Czas:** 30 min (+ research)

#### Krok A: Utwórz `config/company.php`
```php
<?php
// Dane administratora danych — używane w Privacy Policy, Terms, eksporcie danych
return [
    'name'     => 'TODO: Uzupełnij nazwę prawną administratora',
    'address'  => 'TODO: ul. Przykładowa 1, 00-000 Warszawa',
    'nip'      => 'TODO: NIP',
    'location' => 'TODO: Serwer w Hetzner DC1 (Frankfurt, DE)',
    'dpo_email' => 'TODO: dpo@example.com',
    'contact_email' => 'TODO: kontakt@example.com',
    'app_url'  => defined('APP_URL') ? APP_URL : 'https://example.com',
];
```

#### Krok B: Load w bootstrap
Na początku `public/index.php`:
```php
$companyConfig = require __DIR__ . '/../config/company.php';
```

#### Krok C: Privacy.php i terms.php — podmień placeholdery
Zamień `[NAZWA ADMINISTRATORA]` na `<?= htmlspecialchars($companyConfig['name']) ?>` itd. Wymaga przekazania `$companyConfig` przez layout lub globalne.

#### Krok D: DataExportService README
```php
. "Kontakt DPO: " . ($GLOBALS['_company_config']['dpo_email'] ?? 'unknown') . "\n"
```

(Dla prostoty można dodać do `config/config.php` jako stałe: `DPO_EMAIL`, `COMPANY_NAME`, etc.)

**Weryfikacja:** GET `/privacy` — brak dosłownie widocznych `[...]` placeholderów.

---

### ZAD-2.9 — P9: incident-response.md — scenariusz E (external dependency)
**Plik:** `docs/security/incident-response.md`
**Czas:** 20 min

Przed sekcją "6. Kontakty" dodaj:

```markdown
### E) Awaria zewnętrznej zależności (Discovery Sources)

**Scenariusz:** FamilySearch API zwraca 5xx / timeout / odrzuca requesty.
**Wykrycie:**
- Alert monitoring: >10% fail rate requestów do external source
- User reports: "wyszukiwanie się zawiesza"
- Log analysis: powtarzające się `[FamilySearchMatchSource] error` w error_log

**Mitigation (automatyczne):**
1. `MatchingService::findCandidates` ma exception isolation per source → fallback działa automatycznie, inne sources kontynuują
2. Po timeoutcie `search()` zwraca `[]`, user widzi tylko wyniki z dostępnych source

**Mitigation (manualne):**
1. Tymczasowo wyłącz source przez env:
   ```bash
   unset FAMILYSEARCH_CLIENT_ID && docker restart genealog-app
   ```
   `isAvailable()` zwróci false → source nie jest wołane.
2. Wyślij komunikat użytkownikom przez banner w UI (TODO: add notification system)

**Root cause analysis:**
- Czy to outage providera (sprawdź status.familysearch.org)?
- Czy rate limit z naszej strony (za dużo requestów)?
- Czy problem z network / firewall?

**Recovery:**
- Przywróć env variable
- Monitor 1h dla weryfikacji
- Zaloguj incident w `docs/security/incident-log.md`

**SLA cel:** RTO dla external source <2h (nie blokuje głównego flow usera).
```

---

### ZAD-2.10 — P10: Consent checkbox `aria-describedby`
**Plik:** `src/views/pages/auth/register.php`
**Czas:** 5 min (quick win)

```html
<!-- PRZED: -->
<input type="checkbox" id="consent" name="consent" value="1" required
       class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring">
<label for="consent" class="text-muted-foreground select-none">
    Akceptuję ...
</label>

<!-- PO: -->
<input type="checkbox" id="consent" name="consent" value="1" required
       aria-describedby="consent-desc"
       class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring">
<label for="consent" id="consent-desc" class="text-muted-foreground select-none">
    Akceptuję ...
</label>
```

---

### ZAD-2.11 — P11: GenetykaMatchSource path validation
**Plik:** `src/Services/Discovery/Sources/GenetykaMatchSource.php`
**Czas:** 20 min

```php
public function __construct(?string $localDbPath)
{
    if ($localDbPath !== null) {
        $real = realpath($localDbPath);
        $storageRoot = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 4) . '/storage';
        $allowed = realpath($storageRoot . '/geneteka');

        if ($real === false || $allowed === false || !str_starts_with($real, $allowed)) {
            error_log('GenetykaMatchSource: invalid or unauthorized path, source disabled: ' . $localDbPath);
            $localDbPath = null;
        }
    }
    $this->localDbPath = $localDbPath;
}

public function isAvailable(): bool
{
    return $this->localDbPath !== null && is_readable($this->localDbPath);
}
```

---

## Faza 3 — NICE TO HAVE
**Cel:** Quality, WCAG, observability, testy
**Szacowany czas:** ~3-4h

---

### ZAD-3.1 — D1: /health endpoint — usuń timestamp
**Plik:** `src/Controllers/ApiController.php`
**Czas:** 3 min (quick win)

```php
// PRZED:
$status = ['status' => 'ok', 'ts' => date('c')];

// PO:
$status = ['status' => 'ok'];
```

---

### ZAD-3.2 — D2: Popraw komentarz `existsRecentForPerson`
**Plik:** `src/Services/Discovery/MatchingService.php:179`
**Czas:** 2 min (quick win)

```php
// PRZED:
// (dedup będzie sprawdzany przez NotificationRepository::existsRecentForPerson — patrz important #3)

// PO:
// (dedup sprawdzany przez NotificationRepository::existsRecentForLink — zobacz metodę w repo)
```

---

### ZAD-3.3 — D3: LocalTreeMatchSource filtr `visibility`
**Plik:** `src/Services/Discovery/Sources/LocalTreeMatchSource.php`
**Czas:** 5 min (quick win)

W SQL query dodaj:
```sql
AND p.visibility != 'private'
```

Lub jeśli use case wymaga widocznych wszystkich — udokumentuj komentarzem intencję.

---

### ZAD-3.4 — D4: Memoization w MatchSourceRegistry
**Plik:** `src/Services/Discovery/MatchSourceRegistry.php`
**Czas:** 10 min (quick win)

```php
private ?array $availableCache = null;

public function getEnabled(): array
{
    return $this->availableCache ??= array_filter(
        $this->sources,
        static fn(MatchSourceInterface $s): bool => $s->isAvailable()
    );
}

public function register(MatchSourceInterface $source): void
{
    $this->sources[$source->getName()] = $source;
    $this->availableCache = null; // invalidate
}
```

---

### ZAD-3.5 — D5: PersonImportService `isLiving` documentation
**Plik:** `src/Services/Discovery/PersonImportService.php`
**Czas:** 10 min

Dodaj komentarz przy logice `isLiving`:
```php
// ZAD-3.5 (D5): Importowana osoba jest ZAWSZE oznaczona jako "nieżyjąca" dla source_type != 'local'.
// Przyczyna: privacy by design (RODO Art. 25) — nie wiemy czy osoba w external rejestrze żyje.
// Dla source_type='local' — ufamy flagi z użytkownika (sam wprowadził).
// Jeśli w przyszłości MatchResult::jsonSerialize() dla local doda isLiving, zrewiduj tę logikę.
$isLiving = $sourceType === 'local' ? (int)($sourceData['isLiving'] ?? 0) : 0;
```

Lub dodaj `isLiving` do `MatchResult::jsonSerialize()` dla local profile — decyzja architektoniczna.

---

### ZAD-3.6 — D6: Stałe dla source names w MatchSourceInterface
**Plik:** `src/Services/Discovery/MatchSourceInterface.php` + użycia w MatchingService, Sources
**Czas:** 30 min

#### Krok A: Stałe w interface
```php
interface MatchSourceInterface
{
    public const SOURCE_LOCAL      = 'local';
    public const SOURCE_CROSS_TREE = 'cross_tree';
    public const SOURCE_EXTERNAL   = 'external'; // dla FamilySearch, Geneteka, etc.
    // ...
}
```

#### Krok B: Użycia
`MatchingService.php` — zastąp magic strings:
```php
if ($name === MatchSourceInterface::SOURCE_LOCAL) { ... }
elseif ($name === MatchSourceInterface::SOURCE_CROSS_TREE) { ... }
```

`Sources/*.php` — `getName()`:
```php
public function getName(): string { return self::SOURCE_LOCAL; } // lub SOURCE_CROSS_TREE
```

---

### ZAD-3.7 — D7: README eksportu — placeholder [EMAIL DPO]
**Plik:** `src/Services/DataExportService.php:180`
**Czas:** 5 min (quick win)

Zamień hardcoded placeholder na zmienną (requires config z ZAD-2.8):
```php
. "Jeśli masz pytania lub brakuje jakichś danych, skontaktuj się z DPO: "
. (defined('DPO_EMAIL') ? DPO_EMAIL : 'dpo@genealog.local') . "\n"
```

---

### ZAD-3.8 — D8: Privacy Policy — Table of Contents
**Plik:** `src/views/pages/privacy.php`
**Czas:** 20 min

Po `<h1>` dodaj nawigację:
```html
<nav aria-label="Spis treści" class="my-6 rounded-md border border-border bg-muted/30 p-4">
    <h2 class="text-sm font-semibold mb-2">Spis treści</h2>
    <ol class="text-sm space-y-1 list-decimal list-inside">
        <li><a href="#sec-1" class="hover:underline">Administrator danych</a></li>
        <li><a href="#sec-2" class="hover:underline">Cel i podstawa przetwarzania</a></li>
        <li><a href="#sec-3" class="hover:underline">Zakres danych</a></li>
        <li><a href="#sec-4" class="hover:underline">Okres przechowywania</a></li>
        <li><a href="#sec-5" class="hover:underline">Twoje prawa</a></li>
        <li><a href="#sec-6" class="hover:underline">Odbiorcy danych</a></li>
        <li><a href="#sec-7" class="hover:underline">Dane osób trzecich</a></li>
        <li><a href="#sec-8" class="hover:underline">Przekazywanie poza EOG</a></li>
        <li><a href="#sec-9" class="hover:underline">Zewnętrzne rejestry</a></li>
        <li><a href="#sec-10" class="hover:underline">Bezpieczeństwo</a></li>
        <li><a href="#sec-11" class="hover:underline">Zmiany polityki</a></li>
    </ol>
</nav>
```

Dodaj `id="sec-N"` do każdego `<h2>`.

---

### ZAD-3.9 — D9: Weryfikuj `<html lang="pl">` w AuthLayout
**Plik:** `src/views/templates/AuthLayout.php`
**Czas:** 3 min (quick win)

```bash
grep 'lang=' src/views/templates/AuthLayout.php
```

Jeśli brak — dodaj `<html lang="pl">`. Jeśli jest — ZAD-3.9 zamyka się bez zmian.

---

### ZAD-3.10 — D10: CrossTreeMatchSource log sampling
**Plik:** `src/Services/Discovery/Sources/CrossTreeMatchSource.php`
**Czas:** 5 min (quick win)

```php
// PRZED (log przy każdym niepustym wyniku):
if (count($out) > 0) {
    error_log(...);
}

// PO (log co 100-ny match):
if (count($out) > 0 && random_int(1, 100) === 1) {
    error_log(sprintf('[CrossTreeMatchSource] %d matches (hash=%s, sampled 1%%)', count($out), $hash !== null ? 'yes' : 'no'));
}
```

---

### ZAD-3.11 — D11: Migration 014 dodaj FK automatycznie
**Plik:** `migrations/014_tree_members_invited_by.sql`
**Czas:** 10 min (quick win)

Dodaj bezpieczne try-drop-then-add pattern:
```sql
-- Dodaj kolumnę (idempotent)
ALTER TABLE tree_members
    ADD COLUMN IF NOT EXISTS invited_by CHAR(36) NULL AFTER invited_at;

-- FK (trochę toporne, ale DLA MariaDB działa przez usunięcie jeśli istnieje)
-- Próba drop:
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tree_members'
      AND CONSTRAINT_NAME = 'fk_tree_members_invited_by'
);

SET @sql := IF(@fk_exists > 0,
    'ALTER TABLE tree_members DROP FOREIGN KEY fk_tree_members_invited_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Dodaj FK
ALTER TABLE tree_members
    ADD CONSTRAINT fk_tree_members_invited_by
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL;
```

---

### ZAD-3.12 — D12: Unit testy dla FingerprintService + EmailService
**Pliki:** nowe — `tests/Unit/Services/EmailServiceTest.php`, `tests/Unit/Services/Discovery/FingerprintServiceTest.php`
**Czas:** 1h (S-M)

#### EmailServiceTest — test sanitizeHeader
```php
<?php
declare(strict_types=1);
namespace Tests\Unit\Services;

use App\Services\EmailService;
use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase
{
    public function testSanitizeHeaderRemovesCRLF(): void
    {
        $service = new EmailService();
        // Użyj reflection lub zmień metodę na protected dla test
        $reflection = new \ReflectionMethod($service, 'sanitizeHeader');
        $reflection->setAccessible(true);

        $this->assertSame('Jan Kowalski', $reflection->invoke($service, "Jan\r\nKowalski"));
        $this->assertSame('Jan', $reflection->invoke($service, "Jan\r\n\r\nBcc: evil@example.com"));
        $this->assertSame('JanNull', $reflection->invoke($service, "Jan\0Null"));
        $this->assertSame('clean', $reflection->invoke($service, "clean"));
    }
}
```

#### FingerprintServiceTest — regression pins
```php
<?php
declare(strict_types=1);
namespace Tests\Unit\Services\Discovery;

use App\Services\Discovery\FingerprintService;
use PHPUnit\Framework\TestCase;

class FingerprintServiceTest extends TestCase
{
    public function testFingerprintIsStableForSameInput(): void
    {
        $svc = new FingerprintService();

        // Pin: te hashe NIE mogą się zmienić bez świadomej decyzji
        // (zmiana = wszystkie wpisy w global_person_index stają się niedopasowalne)
        $fp1 = $svc->compute('Jan', 'Kowalski', 1950, 'Warszawa');
        $this->assertSame(64, strlen($fp1)); // SHA-256 hex = 64 chars
        $this->assertSame($fp1, $svc->compute('Jan', 'Kowalski', 1950, 'Warszawa'));

        // Case insensitive
        $this->assertSame($fp1, $svc->compute('jan', 'KOWALSKI', 1950, 'warszawa'));

        // Trim
        $this->assertSame($fp1, $svc->compute(' Jan ', ' Kowalski ', 1950, ' Warszawa '));
    }

    public function testFingerprintDiffersForDifferentPersons(): void
    {
        $svc = new FingerprintService();
        $fp1 = $svc->compute('Jan', 'Kowalski', 1950, 'Warszawa');
        $fp2 = $svc->compute('Anna', 'Kowalska', 1950, 'Warszawa');
        $this->assertNotSame($fp1, $fp2);
    }
}
```

---

### ZAD-3.13 — D13: AccountDeletionService tests (K2 regression guard)
**Plik:** nowy `tests/Unit/Services/AccountDeletionServiceTest.php`
**Czas:** 1h

Testy dla K2 fix — findSoleOwnedTreeIds + transferOwnership:
- Sole owner case → drzewo usuwane
- Shared owner → transfer ownership
- Brak innych ownerów ale jest editor → transfer na editor
- Brak nikogo innego → drzewo sole owned (usuwane)

(Wymaga mock Database — można użyć PDO sqlite:memory w setUp).

---

### ZAD-3.14 — D14: (odroczone) unindexTree batching
Patrz post-MVP backlog.

---

## Faza 4 — ODROCZONE (post-MVP)

Nie do implementacji w `/ultra-workaholic dev/audit/backend-4`:

- **Async EventDispatcher + job queue** (backend-2 Faza 3)
- **Container.php activation** (backend-2 Faza 3)
- **Redis session handler** (backend-2 Faza 3)
- **GedcomService split** (backend Faza 5)
- **MFA TOTP** (backend-3 P14)
- **CSP nonce-based** (backend-3 P5)
- **DPIA document** (backend-3 P12)
- **Rejestr Czynności Przetwarzania** (backend-3 D13)
- **unindexTree batching** (D14)
- **Discovery split controller** (backend Faza 4)

---

## Podsumowanie

| Faza | Zadań | Czas | Typ |
|------|-------|------|-----|
| **Faza 1 BLOCKING** | 2 | ~4-6h | K1-K2 (RODO) |
| **Faza 2 IMPORTANT** | 11 | ~6-8h | P1-P11 |
| **Faza 3 NICE TO HAVE** | 14 | ~3-4h | D1-D13 + testy |
| **RAZEM** | **27** | **~13-18h** | |

**Quick wins (< 90 min łącznie):** ZAD-2.2, ZAD-2.4, ZAD-2.6, ZAD-2.10, ZAD-3.1, ZAD-3.2, ZAD-3.3, ZAD-3.4, ZAD-3.7, ZAD-3.9, ZAD-3.10, ZAD-3.11

**Po Fazach 1+2 → re-audit #5** (`dev/audit/backend-5/`) dla finalnej weryfikacji RODO i Discovery hardening.
