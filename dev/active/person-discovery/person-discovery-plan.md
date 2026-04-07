# Plan architektoniczny: Person Discovery

**Feature:** Person Discovery — kompleksowy system znajdowania i importowania osób z 3 źródeł  
**Typ:** COMPLEX  
**Status:** Planowanie  
**Data:** 2026-04-07  
**Audyt:** K1, K2, P1-P4, D1-D3 zaaplikowane

---

## Cel

Kiedy użytkownik dodaje nową osobę do drzewa, system automatycznie proponuje dopasowania z:
1. Własnych drzew użytkownika (lokalne deduplikacje)
2. Zanonimizowanego globalnego indeksu innych drzew (cross-tree matching z opt-in)
3. Zewnętrznych rejestrów (FamilySearch, Geneteka)

Wyniki pojawiają się jako autosuggest w formularzu, persystentne propozycje w profilu osoby oraz powiadomienia push. Architektura plug-and-play przez `MatchSourceInterface`.

---

## Architektura wysokopoziomowa

```
3 punkty wejścia:
  A) Autosuggest w formularzu nowej osoby (/persons/create)
  B) Panel sugestii w profilu osoby (/persons/show)
  C) Dedykowane wyszukiwanie (/search)
           |
           v
    DiscoveryController
           |
           v
    MatchingService (orkiestrator: hybrid fingerprint + fuzzy)
           |
     MatchSourceRegistry
           |
     ┌─────┼─────────────┬──────────────────┐
     v     v             v                  v
 Local  CrossTree  FamilySearch       Geneteka
 Tree   MatchSource MatchSource      MatchSource
```

**EventDispatcher** (in-process) hookuje `person.created / updated / deleted`:
- `GlobalIndexService::indexPerson` — aktualizuje globalny indeks
- `MatchingService::findAndNotifyMatches` — tworzy sugestie + powiadomienia

---

## Schema — migracja 007_discovery.sql

### ALTER persons

```sql
ALTER TABLE persons
  ADD COLUMN fingerprint_hash CHAR(64) AFTER updated_at,
  ADD COLUMN name_soundex CHAR(8) AFTER fingerprint_hash,
  ADD INDEX idx_persons_fingerprint (fingerprint_hash),
  ADD INDEX idx_persons_soundex (name_soundex);
```

### ALTER trees (WAŻNE: DEFAULT 0 — opt-in, nie opt-out, wymagane RODO Art. 25)

```sql
ALTER TABLE trees
  ADD COLUMN is_indexed_globally TINYINT(1) NOT NULL DEFAULT 0 AFTER is_public;
```

### ALTER users

```sql
ALTER TABLE users
  ADD COLUMN discovery_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER locale;
```

### Nowe tabele

```sql
CREATE TABLE global_person_index (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fingerprint_hash CHAR(64)    NOT NULL,
  tree_id       INT UNSIGNED NOT NULL,
  person_id     INT UNSIGNED NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  region        VARCHAR(100),
  earliest_birth_year SMALLINT,
  latest_birth_year   SMALLINT,
  is_living     TINYINT(1) NOT NULL DEFAULT 1,
  indexed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gpi_person (person_id),
  INDEX idx_gpi_fingerprint_region (fingerprint_hash, region),
  INDEX idx_gpi_owner (owner_user_id),
  CONSTRAINT fk_gpi_tree   FOREIGN KEY (tree_id)   REFERENCES trees(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_gpi_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gpi_owner  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE person_match_suggestions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id       INT UNSIGNED NOT NULL,
  source_type     ENUM('local','cross_tree','external') NOT NULL,
  source_id       VARCHAR(64) NOT NULL,
  source_data     JSON,
  confidence      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  status          ENUM('pending','imported','rejected','expired') NOT NULL DEFAULT 'pending',
  created_for_user INT UNSIGNED NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pms_person_source (person_id, source_type, source_id),
  INDEX idx_pms_user_status (created_for_user, status),
  CONSTRAINT fk_pms_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pms_user   FOREIGN KEY (created_for_user) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(50) NOT NULL,
  title      VARCHAR(255) NOT NULL,
  body       TEXT,
  link       VARCHAR(500),
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user_read (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE source_audit_log (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  action           VARCHAR(50) NOT NULL,
  source_type      VARCHAR(50),
  source_id        VARCHAR(64),
  target_person_id INT UNSIGNED,
  target_tree_id   INT UNSIGNED,
  ip               VARCHAR(45),
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sal_user (user_id),
  INDEX idx_sal_created (created_at),
  CONSTRAINT fk_sal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Retencja: 3 lata. Cron: bin/cleanup-audit-log.php
-- GDPR erasure NIE usuwa wpisów audit (wymaga osobnego endpointu admin)
```

---

## Backend — klasy i sygnatury

### Abstrakcja kluczowa

```php
// src/Services/Discovery/MatchSourceInterface.php
interface MatchSourceInterface
{
    public function getName(): string;                                      // 'local', 'cross_tree', 'familysearch'
    public function search(SearchCriteria $c, SearchContext $ctx): array;  // MatchResult[]
    public function fetchDetails(string $sourceId): ?PersonData;
    public function isAvailable(): bool;
}
```

### DTOs

```php
// src/Services/Discovery/DTO/SearchCriteria.php
final class SearchCriteria
{
    public function __construct(
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?int    $birthYear    = null,
        public readonly ?string $birthPlace   = null,
        public readonly ?string $region       = null,
    ) {}

    public function normalize(): self { /* iconv + lowercase + trim */ }
}

// src/Services/Discovery/DTO/SearchContext.php
final class SearchContext
{
    public function __construct(
        public readonly int   $currentUserId,
        public readonly int   $currentTreeId,
        public readonly array $accessibleTreeIds,  // tree_id[] z tree_members
    ) {}
}

// src/Services/Discovery/DTO/MatchResult.php
final class MatchResult
{
    public function __construct(
        public readonly string  $sourceType,     // 'local' | 'cross_tree' | 'external'
        public readonly string  $sourceId,
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?int    $birthYear,
        public readonly ?string $region,         // tylko województwo (cross_tree)
        public readonly float   $confidence,     // 0.0–1.0
        public readonly ?string $treeRef,        // "Drzewo #A4F8" dla cross_tree (P4)
        // NIE ma: photo, notes, person_id, tree_id, owner_email, full date (P4)
    ) {}
}

// src/Services/Discovery/DTO/PersonData.php
final class PersonData
{
    public function __construct(
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?string $birthDate,
        public readonly ?string $birthPlace,
        public readonly ?string $deathDate,
        public readonly ?string $deathPlace,
        public readonly ?string $gender,
        public readonly ?string $notes,
    ) {}
}
```

### Registry

```php
// src/Services/Discovery/MatchSourceRegistry.php
class MatchSourceRegistry
{
    private array $sources = [];

    public function register(MatchSourceInterface $source): void;
    public function get(string $name): ?MatchSourceInterface;
    public function getEnabled(): array;  // filtruje po isAvailable()
}
```

Rejestracja w `public/index.php`:
```php
$matchRegistry = new MatchSourceRegistry();
$matchRegistry->register(new LocalTreeMatchSource($db));
$matchRegistry->register(new CrossTreeMatchSource($db));
if (getenv('FAMILYSEARCH_CLIENT_ID')) {
    $matchRegistry->register(new FamilySearchMatchSource($familySearchSvc));
}
if (getenv('GENETEKA_LOCAL_DB')) {
    $matchRegistry->register(new GenetykaMatchSource($db));
}
```

### FingerprintService

```php
// src/Services/Discovery/FingerprintService.php
class FingerprintService
{
    public function compute(Person $person): ?string;
    // SHA2(normalized_first || normalized_last || birth_year || region, 256)
    // Zwraca null jeśli brak imienia/nazwiska

    public function computeSoundex(string $name): string;
    // WAŻNE (D3): najpierw iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $name)
    // potem lowercase + trim + soundex()
    // Bez normalizacji polski soundex nie zadziała (Ą/Ę/Ó → A/E/O)

    public function extractRegion(string $place): ?string;
    // zwraca województwo z miejsca urodzenia (prosta mapa miast PL)

    public function isHistorical(Person $person): bool;
    // is_living = 0 AND (birth_year IS NULL OR birth_year < YEAR(NOW()) - 100)
}
```

### GlobalIndexService

```php
// src/Services/Discovery/GlobalIndexService.php
class GlobalIndexService
{
    public function indexPerson(Person $person): void;
    // Reguły RODO (wszystkie muszą być spełnione):
    //   is_living = 0
    //   AND (birth_year IS NULL OR birth_year < YEAR(NOW()) - 100)
    //   AND visibility != 'private'
    //   AND tree.is_indexed_globally = 1  (opt-in!)
    //   AND tree.owner.discovery_opt_in = 1

    public function unindexPerson(string $personId): void;
    public function unindexTree(string $treeId): void;

    public function reindexTree(string $treeId): void;
    // Iteruje osoby drzewa, wywołuje indexPerson dla każdej kwalifikującej się
}
```

### MatchingService

```php
// src/Services/Discovery/MatchingService.php
class MatchingService
{
    public function findCandidates(SearchCriteria $c, SearchContext $ctx): array;
    // 1. Exact match (fingerprint_hash)
    // 2. Fuzzy (soundex + Levenshtein) jeśli <3 wyniki exactowych
    // 3. Confidence threshold: minimum 0.5
    // Zwraca MatchResult[] podzielone wg source_type

    public function findAndNotifyMatches(Person $person, int $userId): void;
    // Wywoływane z EventDispatcher po person.created/updated
    // Tworzy person_match_suggestions + wywołuje NotificationService::dispatch
}
```

### DiscoveryController

```php
// src/Controllers/DiscoveryController.php
class DiscoveryController
{
    public function search(): void;                  // GET /api/discovery/search
    public function importMatch(): void;             // POST /api/discovery/match/{id}/import
    public function rejectMatch(): void;             // POST /api/discovery/match/{id}/reject
    public function discoverySettings(): void;       // GET+POST /trees/{id}/settings/discovery
    public function notificationsCount(): void;      // GET /api/notifications/count
    public function notificationsList(): void;       // GET /api/notifications
    public function notificationRead(): void;        // POST /api/notifications/{id}/read
}
```

### PersonImportService

```php
// src/Services/Discovery/PersonImportService.php
class PersonImportService
{
    public function importFromMatch(int $matchId, int $treeId, int $userId): Person;
    // - NIE nadpisuje wypełnionych pól (merge, nie replace)
    // - P3: UPDATE person_match_suggestions SET status='imported'
    //        WHERE id=? AND status='pending' (optimistic lock)
    // - Jeśli affected_rows=0 → throw RaceConditionException (bail)
    // - INSERT do source_audit_log
}
```

### EventDispatcher

```php
// src/Core/EventDispatcher.php
class EventDispatcher
{
    public static function on(string $event, callable $handler): void;
    public static function emit(string $event, mixed ...$args): void;
    public static function clear(string $event): void;  // testy
}
```

Rejestracja listenerów w `public/index.php`:
```php
EventDispatcher::on('person.created', fn(Person $p, int $uid) =>
    $globalIndexSvc->indexPerson($p));

EventDispatcher::on('person.created', fn(Person $p, int $uid) =>
    $matchingSvc->findAndNotifyMatches($p, $uid));

EventDispatcher::on('person.deleted', fn(string $id) =>
    $globalIndexSvc->unindexPerson($id));

EventDispatcher::on('tree.indexed_globally.disabled', fn(string $tid) =>
    $globalIndexSvc->unindexTree($tid));
```

`PersonService::create/update/delete` po sukcesie wywołują `EventDispatcher::emit(...)`.

---

## Endpoints

| Metoda | Ścieżka | Auth | IDOR check |
|--------|---------|------|------------|
| GET | `/api/discovery/search` | logged-in + tree editor | `tree_members.role IN ('owner','editor') AND tree_id = current` |
| GET | `/api/discovery/match/{id}` | match owner | `WHERE created_for_user = currentUserId` (K2) |
| POST | `/api/discovery/match/{id}/import` | editor | `WHERE created_for_user = currentUserId` (K2) + optimistic lock (P3) |
| POST | `/api/discovery/match/{id}/reject` | match owner | `WHERE created_for_user = currentUserId` (K2) |
| GET | `/api/notifications` | logged-in | `WHERE user_id = currentUserId` |
| GET | `/api/notifications/count` | logged-in | jw. + `Cache-Control: max-age=25` (P1) |
| POST | `/api/notifications/{id}/read` | owner | `WHERE user_id = currentUserId` |
| GET + POST | `/trees/{id}/settings/discovery` | tree owner | `tree_members.role = 'owner'` |

**Rate limit (P1):** `discovery_search` — max 30 req/min per (ip, user_id) w tabeli `rate_limits`.

---

## Frontend (Alpine.js + Tailwind)

### A) Autosuggest w persons/create.php

```js
function personDiscovery(treeId) {
    return {
        firstName: '',
        lastName: '',
        results: { local: [], crossTree: [], external: [] },
        loading: false,
        debounceTimer: null,

        init() {
            this.$watch('firstName', () => this.debouncedSearch());
            this.$watch('lastName', () => this.debouncedSearch());
        },

        debouncedSearch() {
            clearTimeout(this.debounceTimer);
            if (this.firstName.length < 2 || this.lastName.length < 2) return;
            this.debounceTimer = setTimeout(() => this.fetchResults(), 400);
        },

        async fetchResults() {
            this.loading = true;
            try {
                const r = await fetch(`/api/discovery/search?treeId=${treeId}&firstName=${this.firstName}&lastName=${this.lastName}`);
                this.results = await r.json();
            } catch { /* toast */ }
            finally { this.loading = false; }
        },

        useData(result) {
            // wypełnia pola formularza
            document.querySelector('[name=first_name]').value = result.firstName;
            document.querySelector('[name=last_name]').value = result.lastName;
            if (result.birthYear) document.querySelector('[name=birth_date]').value = result.birthYear;
        }
    }
}
```

Panel pod formularzem: 3 sekcje jako accordion (local / crossTree / external), każda z licznikiem.

### B) Panel sugestii w persons/show.php

Sekcja "Możliwe powiązania" gdy istnieją `person_match_suggestions WHERE status='pending'`.
Karta: confidence badge + źródło + imię + daty + [Akceptuj] / [Odrzuć].
Akcje przez `fetch` — aktualizacja UI bez przeładowania strony.

### C) Notification bell w AppLayout.php

```js
function notificationBell() {
    return {
        count: 0,
        open: false,
        notifications: [],

        init() {
            this.fetchCount();
            setInterval(() => this.fetchCount(), 30000);
        },

        async fetchCount() {
            const r = await fetch('/api/notifications/count');
            const data = await r.json();
            this.count = data.count;
        },

        async openDropdown() {
            this.open = true;
            const r = await fetch('/api/notifications');
            this.notifications = await r.json();
        },

        async markRead(id, link) {
            await fetch(`/api/notifications/${id}/read`, { method: 'POST' });
            if (link) window.location = link;
        }
    }
}
```

### D) Settings drzewa /trees/{id}/settings/discovery

Formularz: checkbox "Włącz globalny indeks" (DEFAULT 0), info-box o RODO Art. 25, button "Reindeksuj retroaktywnie".

---

## Privacy / RODO compliance

### Reguły kwalifikowania do indeksu globalnego

Wszystkie muszą być `true`:
```
is_living = 0
AND (birth_year IS NULL OR birth_year < YEAR(NOW()) - 100)
AND visibility != 'private'
AND tree.is_indexed_globally = 1    ← K1: opt-in (DEFAULT 0), nie opt-out
AND tree.owner.discovery_opt_in = 1
```

### Cross-tree response — anonimizacja (P4)

Odpowiedź zawiera WYŁĄCZNIE:
- `firstName`, `lastName`, `birthYear`, `region` (tylko województwo)
- `treeRef: "Drzewo #" + substr(hash(tree_id), 0, 4)` — BEZ tree_name (P4)

Odpowiedź NIE zawiera: `photo`, `notes`, `full date`, `person_id`, `tree_id`, `owner_email`, `tree_name`.

### Fingerprint jako PII (P2)

- Podstawa prawna: RODO Art. 6(1)(f) — uzasadniony interes naukowy/genealogiczny
- Dodać sekcję "Globalny indeks" w regulaminie
- Przyszłość: rozważyć indeksowanie wyłącznie po `(birthYear, region)` — mniej identyfikujące

### Audit log

- Retencja: 3 lata
- Cron: `bin/cleanup-audit-log.php` (systemd timer)
- GDPR erasure NIE usuwa wpisów audit — osobny endpoint admin

---

## Hooks / Extensibility

Dodanie nowego źródła = jedna linia rejestracji:
```php
$matchRegistry->register(new GrobonetMatchSource($grobonetSvc));
```

Każdy EventDispatcher listener jest niezależny — nowe zachowania bez modyfikacji istniejącego kodu.

---

## Fazy implementacji

| Faza | Zakres |
|------|--------|
| 1 | Schema (007_discovery.sql) + FingerprintService + GlobalIndexService + EventDispatcher + hooki w PersonService + bin/reindex-all.php |
| 2 | DTOs + MatchSourceInterface + MatchSourceRegistry + LocalTreeMatchSource + MatchingService (tylko local) + DiscoveryController::search + routing |
| 3 | CrossTreeMatchSource + anonimizacja P4 + settings panel /trees/{id}/settings/discovery (DEFAULT 0!) |
| 4 | Autosuggest UI w persons/create.php (Alpine + debounce 400ms + 3-section panel) |
| 5 | NotificationRepository/Service + bell icon w AppLayout + Cache-Control: max-age=25 |
| 6 | PersonImportService + panel sugestii w persons/show.php + import flow + audit log |
| 7 | External adapters (FamilySearchMatchSource, GenetykaMatchSource) |
| 8 | Dokumentacja dev/docs/discovery-architecture.md + aktualizacja CLAUDE.md |

---

## Integracja z planem `registries`

`FamilySearchMatchSource` i `GenetykaMatchSource` są adapterami dla `FamilySearchService` i `GenetykaService` z planu `registries`. Tabele `search_jobs` i `registry_cache` tworzy migracja 006 (registries). Discovery używa ich tylko jeśli istnieją (conditional registration w index.php).

---

## Ryzyka

Patrz `person-discovery-audit.md` — pełny raport z K1, K2, P1-P4, D1-D3.
