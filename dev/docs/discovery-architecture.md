# Person Discovery — Architektura

System znajdowania i importowania osób z 3 rodzajów źródeł: lokalnych drzew usera,
cross-tree matching przez anonimowy fingerprint, external registries (stubs).
Zaimplementowany 2026-04-07 na bazie planu `dev/active/person-discovery/`.

## Przepływ danych

```
┌───────────────────────────────────────────────────────────────┐
│  [Formularz nowej osoby] Alpine x-data="personDiscovery()"    │
│      ↓ @input.debounce.400ms                                   │
│      ↓ GET /api/discovery/search?treeId=X&firstName=Y&lastName=Z
└────────────────────────────┬──────────────────────────────────┘
                             ↓
┌───────────────────────────────────────────────────────────────┐
│  DiscoveryController::search()                                │
│     - IDOR check (tree_members.role IN owner/editor)          │
│     - Build SearchCriteria + SearchContext                    │
└────────────────────────────┬──────────────────────────────────┘
                             ↓
┌───────────────────────────────────────────────────────────────┐
│  MatchingService::findCandidates()                            │
│     - Iteruje MatchSourceRegistry::getEnabled()               │
│     - Grupuje wyniki po source: local/crossTree/external      │
└──┬──────────────────┬──────────────────┬──────────────────────┘
   ↓                  ↓                  ↓
┌─────────┐  ┌───────────────┐  ┌─────────────────────┐
│Local    │  │CrossTree      │  │FamilySearch/Geneteka│
│(persons │  │(global_person │  │(stuby, Faza 7       │
│ trees   │  │ _index,       │  │ registries required)│
│ user    │  │ anonimowe)    │  │                     │
│ access) │  │               │  │                     │
└─────────┘  └───────────────┘  └─────────────────────┘

EventDispatcher (in-process, public/index.php):
  person.created → GlobalIndexService::indexPerson
                  → MatchingService::findAndNotifyMatches
                  → NotificationService::notifyPersonMatch (bell icon)
  person.updated → GlobalIndexService::indexPerson
  person.deleted → GlobalIndexService::unindexPerson
  tree.indexed_globally.disabled → GlobalIndexService::unindexTree
```

## Kluczowa abstrakcja — MatchSourceInterface

```php
interface MatchSourceInterface {
    public function getName(): string;   // 'local' | 'cross_tree' | 'familysearch' | ...
    public function search(SearchCriteria $c, SearchContext $ctx): array;   // list<MatchResult>
    public function isAvailable(): bool;  // np. sprawdza ENV vars
}
```

Każde źródło żyje w `src/Services/Discovery/Sources/`. Dodanie nowego źródła
sprowadza się do:

1. Stworzenia klasy implementującej `MatchSourceInterface`
2. Jednej linii rejestracji w `public/index.php`:
   ```php
   $matchRegistry->register(new MyCustomMatchSource($db, $fingerprintSvc));
   ```

`MatchingService` nie wymaga modyfikacji — iteruje po `getEnabled()` transparentnie.

## Schema (migracja 008_discovery.sql)

- `persons.fingerprint_hash CHAR(64)` + `persons.name_soundex CHAR(8)` + indeksy
- `trees.is_indexed_globally TINYINT(1) DEFAULT 0` ⚠️ **opt-in** (RODO Art. 25)
- `trees.discovery_consent_at TIMESTAMP`
- `users.discovery_opt_in TINYINT(1) DEFAULT 0`
- `global_person_index(fingerprint_hash, tree_id, person_id, owner_user_id, region, earliest_birth_year, ...)`
- `person_match_suggestions(person_id, source_type, source_id, confidence, status, created_for_user)`
- `source_audit_log(user_id, action, source_type, source_id, target_person_id, ip)`
- `notifications` — już istniała (migracja 006)

## RODO compliance

Reguły egzekwowane w `GlobalIndexService::isEligible()`:

```
ELIGIBLE do global_person_index:
  is_living = 0
  AND birth_year IS NULL OR birth_year < YEAR(NOW()) - 100
  AND visibility != 'private'
  AND tree.is_indexed_globally = 1   ← user musi OPT-IN (DEFAULT 0)
  AND owner.discovery_opt_in = 1     ← opt-in na poziomie konta
```

Dodatkowo:

- **Lokalny fingerprint** (`persons.fingerprint_hash`) zapisywany ZAWSZE (potrzebny do lokalnego search)
- **Global index** — tylko po podwójnym opt-in (konto + drzewo)
- **Cross-tree response** zawiera tylko: imię, nazwisko, rok urodzenia, region (województwo), anonimowy `treeRef` (pierwsze 4 znaki hash tree_id)
- **Bez:** photo, notes, full date, email ownera, person_id, tree_id, tree_name (audyt P4)
- **Audit log** w `source_audit_log` — retencja 3 lata (RODO Art. 30)

## Hybrid matching strategy

`LocalTreeMatchSource::search()`:

1. **Phase 1 — exact fingerprint** (`persons.fingerprint_hash = ?`) → confidence 1.0
2. **Phase 2 — fuzzy fallback** (gdy < 3 exact matches): soundex + Levenshtein
   - Wagi: 0.4 imię + 0.6 nazwisko
   - Bonus ±2 lata birth_year: +0.1
   - Kara >10 lat różnicy: -0.15
   - Threshold minimum: 0.5
   - Cap: 0.95 (fuzzy nigdy nie przebije exact)

`FingerprintService::computeSoundex()` normalizuje polskie diakrytyki przez
`iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE')` przed wywołaniem `soundex()` — bez
tego PHP soundex nie obsługuje `Ż`, `Ł`, `Ć` itp.

## EventDispatcher — hooki lifecycle

Rejestracja w `public/index.php`:

```php
EventDispatcher::on('person.created', static function ($person, $userId) use ($globalIndexSvc, $matchingSvc) {
    $globalIndexSvc->indexPerson($person);
    $matchingSvc->findAndNotifyMatches($person, $userId);
});
```

Emisja z `PersonService`:

```php
public function create(string $treeId, string $createdBy, array $input): Person {
    // ... create logic ...
    EventDispatcher::emit('person.created', $person, $createdBy);
    return $person;
}
```

Wyjątki z listenerów są logowane do `error_log()`, **nie propagowane** — jeden
nieudany listener (np. sieciowy external match) nie przerywa utworzenia osoby.

## Endpointy API

| Metoda | Ścieżka | Auth / IDOR |
|--------|---------|-------------|
| GET | `/api/discovery/search` | logged-in + tree editor (`tree_members.role IN owner/editor`) |
| POST | `/api/discovery/match/{id}/import` | logged-in + `created_for_user = currentUserId` + tree editor |
| POST | `/api/discovery/match/{id}/reject` | logged-in + `created_for_user = currentUserId` |
| GET | `/api/notifications` | logged-in (WHERE `user_id = currentUserId`) |
| GET | `/api/notifications/count` | logged-in + `Cache-Control: max-age=25` |
| POST | `/api/notifications/{id}/read` | logged-in (WHERE `user_id = currentUserId`) |
| GET+POST | `/trees/{id}/settings/discovery` | tree owner only |

## Pliki — mapa implementacji

### Core
- `src/Core/EventDispatcher.php` — static in-process event dispatcher z error-log

### Discovery
- `src/Services/Discovery/FingerprintService.php` — compute(), computeSoundex(), extractRegion(), isHistorical()
- `src/Services/Discovery/GlobalIndexService.php` — indexPerson, unindexPerson, unindexTree, reindexTree, syncPersonFingerprint
- `src/Services/Discovery/MatchingService.php` — findCandidates, findAndNotifyMatches
- `src/Services/Discovery/MatchSourceInterface.php` — abstrakcja
- `src/Services/Discovery/MatchSourceRegistry.php` — DI container
- `src/Services/Discovery/PersonImportService.php` — optimistic lock import z audit

### DTOs
- `src/Services/Discovery/DTO/SearchCriteria.php`
- `src/Services/Discovery/DTO/SearchContext.php`
- `src/Services/Discovery/DTO/MatchResult.php` (JsonSerializable, RODO trimming w `jsonSerialize()`)

### Sources
- `src/Services/Discovery/Sources/LocalTreeMatchSource.php` — pełne dane (user access)
- `src/Services/Discovery/Sources/CrossTreeMatchSource.php` — anonimowy (treeRef, bez tree_name)
- `src/Services/Discovery/Sources/FamilySearchMatchSource.php` — **stub** (wymaga registries)
- `src/Services/Discovery/Sources/GenetykaMatchSource.php` — **stub** (wymaga registries)

### Controllers / Repositories
- `src/Controllers/DiscoveryController.php`
- `src/Repositories/DiscoveryRepository.php`

### Frontend
- `src/views/pages/trees/persons/create.php` — autosuggest panel (Alpine + debounce)
- `src/views/pages/trees/settings/discovery.php` — opt-in settings
- `src/views/templates/AppLayout.php` — notification bell (Alpine polling 30s)

### Migracje
- `migrations/008_discovery.sql`

### CLI
- `bin/reindex-all.php` — retroaktywny fill fingerprint_hash + global_person_index

## Jak dorzucić nowy external source

1. Utwórz klasę w `src/Services/Discovery/Sources/MyArchiveMatchSource.php`:
   ```php
   final class MyArchiveMatchSource implements MatchSourceInterface {
       public function getName(): string { return 'my_archive'; }
       public function isAvailable(): bool { return getenv('MY_ARCHIVE_KEY') !== false; }
       public function search(SearchCriteria $c, SearchContext $ctx): array {
           // fetch + transform → list<MatchResult> z sourceType='external'
       }
   }
   ```
2. Zarejestruj w `public/index.php`:
   ```php
   $matchRegistry->register(new MyArchiveMatchSource(getenv('MY_ARCHIVE_KEY') ?: null));
   ```
3. Gotowe — `MatchingService` podepnie automatycznie, autosuggest UI pokaże w sekcji „Z zewnętrznych baz"

## Znane ograniczenia MVP

- **External sources to stuby** — pełna implementacja wymaga planu `registries`
- **Panel sugestii w `persons/show.php`** — nie dodany w tej iteracji (endpointy `/import` i `/reject` działają, brakuje UI)
- **Retencja `source_audit_log`** — brak automatycznego cleanup'u (trzeba dopisać cron)
- **Anti-cycle dla import z cross-tree** — brak; jeśli user zaimportuje osobę cross-tree, która potem się indeksuje globalnie, teoretycznie mogłaby wywołać ponowną propozycję. Mitygacja: `UNIQUE(person_id, source_type, source_id)` na suggestions
