# Audyt bezpieczeństwa — Genealog (backend) — Re-audit #4

**Data:** 2026-04-08
**Audytor:** claude-opus-4-6 + 3 subagenty (security-auditor, general-purpose, Plan)
**Metoda:** OWASP Top 10 + RODO + NIS2 + WCAG + STRIDE
**Zakres:** Weryfikacja poprawek backend-3 (25 zadań) + nowy moduł Discovery Sources
**Werdykt:** **PASS WITH CONDITIONS** — 2 krytyczne i 11 poważnych przed deploy

---

## Kontekst

Czwarty audyt backendu w sekwencji:
- **Audit #1** (`dev/audit/backend/`) — 73 zadań, ~60 zaimplementowanych
- **Audit #2** (`dev/audit/backend-2/`) — 21 zadań, 17 zaimplementowanych (odroczona Faza 3 architektury)
- **Audit #3** (`dev/audit/backend-3/`) — 25 zadań, **25/25 zaimplementowanych** ✅
- **Audit #4** (ten) — weryfikacja backend-3 + audyt nowego modułu Discovery Sources

### Zakres zmian od backend-3

**Poprawki z backend-3 (wszystkie zweryfikowane):**
- K1 SMTP sanityzacja ✅, K2 findSoleOwnedTreeIds NOT EXISTS + transferOwnership ✅, K3 Privacy Policy + consent ✅
- P1-P11, P13, D1-D12 — wszystkie PASS

**Nowe komponenty dodane przez użytkownika (poza backend-3):**
- `src/Services/Discovery/MatchSourceInterface.php` — pluggable interface
- `src/Services/Discovery/MatchSourceRegistry.php` — registry pattern
- `src/Services/Discovery/Sources/LocalTreeMatchSource.php`
- `src/Services/Discovery/Sources/CrossTreeMatchSource.php`
- `src/Services/Discovery/Sources/FamilySearchMatchSource.php` (stub)
- `src/Services/Discovery/Sources/GenetykaMatchSource.php` (stub)
- `src/Services/Discovery/DTO/` — SearchCriteria, SearchContext, MatchResult
- `src/Services/Discovery/PersonImportService.php`
- `src/Services/Discovery/FingerprintService.php`
- `src/Repositories/NotificationRepository::existsRecentForLink` (dedup)
- `src/Repositories/UserRepository::isDiscoveryOptedIn`
- Bootstrap w `public/index.php:127-140` (MatchSourceRegistry + EventDispatcher listeners)

---

## Weryfikacja backend-3 — ✅ PASS 25/25

| ID | Naprawa | Plik | Status |
|----|---------|------|--------|
| K1 | SMTP sanitizeHeader | `EmailService.php:17-19,78` | ✅ PASS |
| K2 | findSoleOwnedTreeIds NOT EXISTS | `AccountDeletionService.php:118-128` | ✅ PASS |
| K2 | transferOwnership | `AccountDeletionService.php:53-55` | ✅ PASS |
| K3 | /privacy + /terms routes | `public/index.php:174-179` | ✅ PASS |
| K3 | Consent validation | `AuthController.php:94` | ✅ PASS |
| P1 | data-search-name XSS fix | `trees/persons/index.php` | ✅ PASS |
| P2 | GEDCOM MIME whitelist | `GedcomController.php:26` | ✅ PASS |
| P2 | Content check `0 HEAD` | `GedcomController.php:136` | ✅ PASS |
| P3 | Invitation email check | `InvitationService.php:accept` | ✅ PASS |
| P6 | register_shutdown_function tmpPath | `GedcomController.php:127-131` | ✅ PASS |
| P7 | session_version getSessionVersion() | `ProfileController.php:90,126` | ✅ PASS |
| P8 | Migration 012 indexes | `migrations/012_persons_indexes.sql` | ✅ PASS |
| P9 | MAX_HIERARCHY_DEPTH=20 | `PersonController.php` | ✅ PASS |
| P10 | DataExport extended | `DataExportService.php:75-140` | ✅ PASS |
| P11 | RODO Art. 18 restriction | `migration 013, SettingsController, AuthService::clearRestriction` | ✅ PASS |
| P13 | backup.md | `docs/operations/backup.md` | ✅ PASS |
| D1-D12 | Drobne | różne | ✅ PASS |

**Regresje:** 0 wykrytych. Backend-3 został zaimplementowany solidnie.

---

## KRYTYCZNE [K] — blokują deploy

### K1 — RODO Art. 7(1): brak dowodu zgody w bazie danych
**Plik:** `src/Services/AuthService.php:49`, `src/Repositories/UserRepository.php:33`
**Kategoria:** RODO Art. 7(1) — ciężar dowodu zgody

**Dowód:** `AuthController::processRegister` weryfikuje checkbox `consent` backend-side (ZAD-1.3 backend-3), ale samo potwierdzenie **nie jest zapisywane w DB**. Tabela `users` nie ma kolumny `terms_accepted_at` / `consent_version`.

**Ryzyko:** RODO Art. 7(1) wymaga od administratora **udowodnienia, że osoba wyraziła zgodę**. Bez persystencji: user może zakwestionować zgodę, organ nadzorczy (UODO) może zażądać dowodu — brak. Kary administracyjne do 4% obrotu.

**Naprawa:**
1. Migration `015_user_consent_tracking.sql`:
   ```sql
   ALTER TABLE users
       ADD COLUMN terms_accepted_at TIMESTAMP NULL AFTER created_at,
       ADD COLUMN terms_version VARCHAR(20) NULL AFTER terms_accepted_at;
   ```
2. `UserRepository::create()` — dodaj parametry + INSERT
3. `AuthService::register()` — pobierz aktualną wersję regulaminu (np. z `config/config.php` lub pliku `docs/terms-version.txt`) i przekaż do create
4. `AuthController::processRegister` — przekazuje `ACCEPT_TIMESTAMP`

**Effort:** S (1h + migracja)

---

### K2 — RODO Art. 13(1)(f) + Rozdział V: Privacy Policy nie informuje o Discovery Sources / transferze do USA
**Plik:** `src/views/pages/privacy.php:117-122` (sekcja 8 "Przekazywanie danych poza EOG")
**Kategoria:** RODO Art. 13(1)(e-f), Art. 46, Rozdział V

**Dowód:** Sekcja 8 mówi: *"Dane nie są przekazywane poza Europejski Obszar Gospodarczy. Infrastruktura Genealog znajduje się w [LOKALIZACJA SERWERA]."* — jest to **nieprawda** gdy user aktywuje wyszukiwanie przez FamilySearchMatchSource (USA).

FamilySearch jest pluggable source w architekturze (stub obecnie, ale zarejestrowane w `public/index.php:131`). Gdy zostanie aktywowane:
- Dane osobowe (imię, nazwisko, rok urodzenia) są wysyłane do FamilySearch Inc. (USA)
- Transfer do USA wymaga **mechanizmu transferu** (Standard Contractual Clauses, BCR, adequacy decision)
- Po wyroku Schrems II brak decyzji adekwatności dla USA w zakresie większości danych

**Ryzyko:** Naruszenie Rozdziału V RODO. Kary administracyjne. Nawet jako stub — user nie jest informowany o możliwości transferu, więc podstawa prawna (zgoda) jest niekompletna.

**Naprawa:**
1. Dodaj sekcję w `privacy.php` (po sekcji 8):
   ```markdown
   ## 9. Integracje z zewnętrznymi rejestrami (Discovery Sources)

   Na Twoje żądanie Genealog może wyszukiwać osoby w zewnętrznych rejestrach:
   - **FamilySearch** (USA) — transfer danych poza EOG na podstawie SCC
   - **Geneteka** (Polska) — PTG, bez transferu
   - **Local / Cross-tree** — wewnętrzne, bez transferu

   Wyszukiwanie wymaga Twojej świadomej decyzji. Możesz wyłączyć konkretne źródła w ustawieniach Discovery.
   ```
2. W `terms.php` dodaj paragraf informacyjny
3. Discovery settings (`/trees/{id}/settings/discovery`) — dodaj opcję per-source opt-in
4. Rozważ wstrzymanie aktywacji `FamilySearchMatchSource` do czasu zawarcia SCC z FamilySearch Inc.

**Effort:** M (2-3h + research prawny)

---

## POWAŻNE [P] — wymagane przed deploy

### P1 — Synchroniczny EventDispatcher w bulk GEDCOM import → DoS
**Pliki:** `public/index.php:136-139`, `src/Services/Discovery/MatchingService.php`
**Kategoria:** OWASP A04 — Insecure Design (DoS)

**Dowód:**
```php
EventDispatcher::on('person.created', static function ($person, $userId)
    use ($globalIndexSvc, $matchingSvc) {
    $globalIndexSvc->indexPerson($person);
    $matchingSvc->findAndNotifyMatches($person, $userId); // ← synchroniczne
});
```

GedcomService emituje `person.created` per osoba. Import 5000-osobowego pliku = 5000× synchronicznych wywołań `findAndNotifyMatches()`, każde z ≥2 queries DB. Rate limit GEDCOM = 5 importów/h → 25 000 heavy queries na godzinę per IP.

Dodatkowo: docbloc MatchingService mówi "asynchroniczne powiadomienia" (linia 19) — **mylący komentarz**.

**Ryzyko:** DoS bazy danych, timeout HTTP, degradacja dla innych userów podczas masowego importu.

**Naprawa (krótkoterminowo):** GedcomService przy imporcie **nie emituje** `person.created` per osoba. Zamiast tego po zakończeniu importu emituje `tree.imported` event który wywołuje batch matching asynchroniczny (lub wyłącza matching dla importu całkowicie — user może ręcznie uruchomić `reindexTree` gdy chce).

**Naprawa (długoterminowo):** Job queue (`async_jobs` table + cron worker) — patrz backend-2 Faza 3 odroczone.

**Effort:** M (3-4h krótkoterminowo, L długoterminowo)

---

### P2 — `findAndNotifyMatches` nie weryfikuje `discovery_opt_in`
**Plik:** `src/Services/Discovery/MatchingService.php:94-188`
**Kategoria:** RODO Art. 7(3) — prawo do wycofania zgody

**Dowód:** `findAndNotifyMatches` wywołuje `CrossTreeMatchSource::search()` dla nowo dodanej osoby **bez sprawdzenia `discovery_opt_in` właściciela drzewa**. Jeśli user wycofał zgodę ale `unindexTree` zawiodło (np. `error_log + continue` bez rollback), jego osoby zostają w `global_person_index` i dalej generują powiadomienia dla innych.

Metoda `UserRepository::isDiscoveryOptedIn()` już istnieje (linia 140) ale nie jest wołana w `findAndNotifyMatches`.

**Ryzyko:** Naruszenie Art. 7(3) RODO — przetwarzanie danych po wycofaniu zgody.

**Naprawa:** W `MatchingService::findAndNotifyMatches`, po pobraniu `$tree`:
```php
if (!$this->userRepo->isDiscoveryOptedIn($tree->ownerId)) {
    return; // zgoda wycofana — brak cross-tree matching
}
```

**Effort:** XS (5 min)

---

### P3 — `reindexTree` N+1 queries + brak batching (DoS)
**Pliki:** `src/Services/Discovery/GlobalIndexService.php:148-154`, `src/Controllers/DiscoveryController.php:reindexTree`
**Kategoria:** Performance / DoS

**Dowód:**
```php
public function reindexTree(string $treeId): void
{
    $persons = $this->personRepo->findByTree($treeId);
    foreach ($persons as $person) {
        $this->indexPerson($person); // każda iteracja: SELECT tree + SELECT user + INSERT
    }
}
```

Dla drzewa z 5000 osób: 10 000+ queries w jednym HTTP request przez `DiscoveryController::reindexTree()`. Brak `set_time_limit`, brak chunking, brak progress. Timeout 30s realny.

**Naprawa:**
1. Pobierz `$tree` i `$isOptedIn` RAZ przed pętlą (caching)
2. Dodaj `set_time_limit(300)` na początku metody
3. Chunk po 100 osób z `flush()` progress dla UI

**Effort:** S (30 min)

---

### P4 — `error_log` ujawnia `userId` w CrossTreeMatchSource
**Plik:** `src/Services/Discovery/Sources/CrossTreeMatchSource.php:172-177`
**Kategoria:** OWASP A09 — Logging failures

**Dowód:**
```php
error_log(sprintf(
    '[CrossTreeMatchSource] returned %d matches for user=%s (hash=%s)',
    count($out), $context->currentUserId, $hash !== null ? 'yes' : 'no'
));
```

Log wykonuje się przy **każdym** zapytaniu z wynikami (nie tylko error). `currentUserId` (UUID) + informacja o aktywności tworzy audit trail dla atakującego z dostępem do logów.

**Ryzyko:** Enumeracja aktywnych userów, korelacja zapytań, SIEM może traktować jako data exfiltration channel.

**Naprawa:**
```php
// Usuń $context->currentUserId z logu:
error_log(sprintf('[CrossTreeMatchSource] %d matches (hash=%s)', count($out), $hash !== null ? 'yes' : 'no'));
```

**Effort:** XS (3 min)

---

### P5 — Brak `timeout` w `MatchSourceInterface` (dług architektoniczny)
**Plik:** `src/Services/Discovery/MatchSourceInterface.php`
**Kategoria:** OWASP A04 — Insecure Design

**Dowód:** Interface `MatchSourceInterface` nie wymusza timeoutu dla `search()`. Gdy `FamilySearchMatchSource` i `GenetykaMatchSource` zostaną zaimplementowane (HTTP calls), będą wywoływane synchronicznie z `MatchingService::findCandidates()`. Jeden timeout FamilySearch (30s) zablokuje request usera.

**Naprawa:** Rozszerzyć interface:
```php
interface MatchSourceInterface {
    public function getName(): string;
    public function isAvailable(): bool;
    public function getTimeoutSeconds(): int; // Default 5s
    public function search(SearchCriteria $criteria, SearchContext $context): array;
}
```
MatchingService wymusza timeout przez `stream_context` lub wrappery biblioteczne.

**Effort:** S (45 min + tests)

---

### P6 — RODO Art. 15: Eksport bez `tree_members` i `password_resets`
**Plik:** `src/Services/DataExportService.php:100-140`
**Kategoria:** RODO Art. 15 — prawo dostępu

**Dowód:** DataExportService zawiera 7 sekcji po backend-3 (account, notifications, audit-log, invitations, shared-tree-contributions, discovery-suggestions, trees), ale nie zawiera:
- `tree-memberships.json` — w których drzewach user jest członkiem, z jaką rolą, kto zaprosił
- `password-resets.json` (metadata bez tokenu) — historia żądań resetu z IP

**Ryzyko:** Niekompletność Art. 15 — user ma prawo wiedzieć wszystko. Organ może uznać eksport za niezgodny.

**Naprawa:** Dodać sekcje w `DataExportService::generateExport`:
```php
$memberships = $this->db->fetchAll(
    'SELECT tm.tree_id, tm.role, tm.invited_at, tm.invited_by, t.name AS tree_name
     FROM tree_members tm INNER JOIN trees t ON t.id = tm.tree_id
     WHERE tm.user_id = ?',
    [$userId]
);
$zip->addFromString('tree-memberships.json', json_encode($memberships, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$passwordResets = $this->db->fetchAll(
    'SELECT id, expires_at, used_at, ip, created_at
     FROM password_resets WHERE user_id = ?', // BEZ kolumny token (secret)
    [$userId]
);
$zip->addFromString('password-resets.json', json_encode($passwordResets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

**Effort:** XS (15 min)

---

### P7 — GenetykaMatchSource brak audit logu w `source_audit_log`
**Plik:** `src/Services/Discovery/Sources/GenetykaMatchSource.php`
**Kategoria:** RODO Art. 30 — Rejestr Czynności Przetwarzania

**Dowód:** Stub `GenetykaMatchSource::search()` zwraca `[]`, ale architektonicznie nie zakłada auditingu. Każde wywołanie zewnętrznego źródła danych to przetwarzanie w rozumieniu RODO — powinno być audytowane per Art. 30.

`PersonImportService` loguje **import**, ale wyszukiwanie (browsowanie zewnętrznego rejestru) nie.

**Naprawa:** Przed pełną implementacją — dodaj w `search()`:
```php
$this->discoveryRepo->logAudit(
    userId: $context->currentUserId,
    action: 'discovery_search',
    sourceType: $this->getName(),
    sourceId: sha1(serialize($criteria)),
    targetPersonId: null,
    targetTreeId: $context->treeId ?? null,
    ip: $context->ip,
);
```

Zastosuj analogicznie w `FamilySearchMatchSource` przy implementacji.

**Effort:** S (30 min)

---

### P8 — Privacy Policy — 4+ placeholdery blokują produkcję
**Pliki:** `src/views/pages/privacy.php`, `src/views/pages/terms.php`, `src/Services/DataExportService.php:180` (README.txt)
**Kategoria:** RODO Art. 13 — obowiązek informacyjny

**Dowód:** Dokumenty zawierają niepodmienione:
- `[NAZWA ADMINISTRATORA]` (2×)
- `[ADRES]`, `[NIP]`
- `[EMAIL DPO]` (3× + w README.txt eksportu danych)
- `[LOKALIZACJA SERWERA]`
- `[APP_URL]` (terms.php)
- `[EMAIL KONTAKTOWY]` (terms.php § 9)

**Ryzyko:** Brak możliwości kontaktu DPO, brak identyfikacji administratora. Naruszenie Art. 13(1)(a-c).

**Naprawa:** Lista checklist do uzupełnienia przed go-live. Najlepiej: przenieść wartości do `config/company.php` i include w szablonach.

**Effort:** S (1h + ustalenie danych firmy)

---

### P9 — NIS2: brak scenariusza external dependency outage w incident-response
**Plik:** `docs/security/incident-response.md`
**Kategoria:** NIS2 Art. 21.2(c) — business continuity

**Dowód:** Scenariusze A-D pokrywają wyciek, brute-force, włamanie, ransomware. Brak **scenariusza E** dla awarii zewnętrznej zależności (FamilySearch API outage, Geneteka CSV niedostępne).

**Naprawa:** Dodać sekcję "E) Awaria zewnętrznej zależności":
```markdown
### E) Awaria zewnętrznej zależności (Discovery Sources)

1. Wykrycie: alarmy z `CrossTreeMatchSource`/external: timeout, 5xx, > 10% fail rate
2. Mitigation:
   - MatchingService ma exception isolation per source → fallback automatycznie
   - Tymczasowe wyłączenie przez env: unset FAMILYSEARCH_CLIENT_ID → isAvailable()=false
3. Komunikacja użytkownikom: banner "Wyszukiwanie w rejestrze X chwilowo niedostępne"
4. Monitoring health: circuit breaker (planowany w backlogu)
```

**Effort:** S (30 min)

---

### P10 — Consent checkbox bez `aria-describedby`
**Plik:** `src/views/pages/auth/register.php`
**Kategoria:** WCAG 2.1 AA — 3.3.2

**Dowód:** Checkbox `#consent` ma `<label>` z linkami do Regulaminu i Polityki, ale brak `aria-describedby` łączącego z opisem.

**Naprawa:**
```html
<input type="checkbox" id="consent" name="consent" value="1" required
       aria-describedby="consent-desc">
<label for="consent" id="consent-desc">
    Akceptuję <a href="/terms" target="_blank">Regulamin</a> i ...
</label>
```

**Effort:** XS (5 min)

---

### P11 — Bootstrap w `public/index.php` rejestruje sources z getenv — brak walidacji GENETEKA_LOCAL_DB (path traversal ready)
**Plik:** `public/index.php:132`, `src/Services/Discovery/Sources/GenetykaMatchSource.php`
**Kategoria:** OWASP A01 — Path traversal readiness

**Dowód:**
```php
$matchRegistry->register(new GenetykaMatchSource(getenv('GENETEKA_LOCAL_DB') ?: null));
```

Wartość env trafia bezpośrednio do konstruktora jako `$localDbPath`. Stub obecnie ignoruje, ale przy pełnej implementacji ścieżka bez `realpath()` + allowlist pozwoli na path traversal.

**Naprawa:** W konstruktorze `GenetykaMatchSource`:
```php
public function __construct(?string $localDbPath) {
    if ($localDbPath !== null) {
        $real = realpath($localDbPath);
        $allowed = realpath(STORAGE_PATH . '/geneteka');
        if ($real === false || !str_starts_with($real, $allowed)) {
            error_log('GenetykaMatchSource: invalid path, disabling');
            $localDbPath = null;
        }
    }
    $this->localDbPath = $localDbPath;
}
```

**Effort:** S (20 min)

---

## DROBNE [D]

### D1 — `/health` endpoint ujawnia timestamp
**Plik:** `src/Controllers/ApiController.php:133-152`
**Kategoria:** A05 — Security Misconfiguration (minor)

**Dowód:** `GET /health` zwraca `{"status":"ok","db":"ok","ts":"2026-04-08T..."}` — publiczny. Timestamp ujawnia strefę czasową serwera.

**Naprawa:** Usuń `ts` z publicznej odpowiedzi lub zastąp `"db":true`.

---

### D2 — Komentarz w MatchingService wskazuje nieistniejącą metodę
**Plik:** `src/Services/Discovery/MatchingService.php:179`
**Kategoria:** Code quality

**Dowód:** `// (dedup będzie sprawdzany przez NotificationRepository::existsRecentForPerson — patrz important #3)` — metoda nazywa się `existsRecentForLink`, nie `existsRecentForPerson`.

**Naprawa:** Zaktualizuj komentarz.

---

### D3 — `LocalTreeMatchSource` zwraca osoby bez filtru `visibility`
**Plik:** `src/Services/Discovery/Sources/LocalTreeMatchSource.php:84-91`
**Kategoria:** Defense-in-depth

**Dowód:** Query nie filtruje `p.visibility = 'private'` ani `p.is_living = 0` — zwraca wszystkie osoby z dostępnych drzew. Dla local match to technicznie OK (user ma dostęp), ale narusza zasadę minimum exposure.

**Naprawa:** Dodaj `AND p.visibility != 'private'` lub osobny tryb "suggest dla importu" filtrowany dodatkowo.

---

### D4 — `isAvailable()` wywoływane N razy per request bez cache
**Plik:** `src/Services/Discovery/MatchSourceRegistry.php`
**Kategoria:** Performance

**Dowód:** `getEnabled()` wywołuje `isAvailable()` przy każdym search. Aktualnie lekkie (string check), ale gdy external sources wdrożą health check HTTP — będzie heavy.

**Naprawa:** Memoization w registry: `private ?array $availableCache = null; public function getEnabled() { return $this->availableCache ??= array_filter(...); }`.

---

### D5 — `PersonImportService` ignoruje `isLiving` z local sources
**Plik:** `src/Services/Discovery/PersonImportService.php:58-60`
**Kategoria:** Code quality (intended? undokumentowane)

**Dowód:** `$isLiving = $sourceType === 'local' ? (int)($sourceData['isLiving'] ?? 0) : 0`. Ale `MatchResult::jsonSerialize()` dla local **nie** serializuje `isLiving` → zawsze 0.

**Naprawa:** Zdefiniuj intencję: albo serializuj `isLiving` do `source_data`, albo udokumentuj że local imports zawsze `isLiving=0`.

---

### D6 — Hardcoded stringi 'local'/'cross_tree' w MatchingService
**Plik:** `src/Services/Discovery/MatchingService.php:52-59`
**Kategoria:** Code quality / maintainability

**Dowód:** `if ($name === 'local')`, `if ($name === 'cross_tree')`, `default => 'external'` — duplicate strings w MatchingService + Sources.

**Naprawa:** Stałe w interface:
```php
interface MatchSourceInterface {
    public const SOURCE_LOCAL = 'local';
    public const SOURCE_CROSS_TREE = 'cross_tree';
    // ...
}
```

---

### D7 — README.txt w eksporcie danych ma placeholder `[EMAIL DPO]`
**Plik:** `src/Services/DataExportService.php:180`
**Kategoria:** User experience

**Dowód:** Użytkownik pobierający ZIP zobaczy dosłownie `[EMAIL DPO]`.

**Naprawa:** Podmień na zmienną z config lub stałą.

---

### D8 — Privacy Policy bez Table of Contents (11 sekcji)
**Plik:** `src/views/pages/privacy.php`
**Kategoria:** WCAG 2.4.5 (multiple ways)

**Dowód:** 11 sekcji `<h2>` bez nawigacji. Użyteczność niska dla screen readerów.

**Naprawa:** Dodaj `<nav aria-label="Spis treści">` z linkami do sekcji przed treścią.

---

### D9 — Brak `lang="pl"` weryfikacji dla stron privacy/terms
**Plik:** `src/views/templates/AuthLayout.php`
**Kategoria:** WCAG 1.3.1

**Dowód:** Strony renderowane przez AuthLayout — weryfikuj czy layout ustawia `<html lang="pl">`.

**Naprawa:** Jeśli brak — dodaj.

---

### D10 — `CrossTreeMatchSource` spam do error_log przy popularnych nazwiskach
**Plik:** `src/Services/Discovery/Sources/CrossTreeMatchSource.php`
**Kategoria:** Operational / log noise

**Dowód:** Log wywoływany przy każdym niezerowym wyniku. Przy popularnym nazwisku "Kowalski" może być setki wpisów/sesję.

**Naprawa:** Sample log (co 100-ny match) lub downgrade do DEBUG.

---

### D11 — Migration 014 nie dodaje automatycznie FK (manualny krok)
**Plik:** `migrations/014_tree_members_invited_by.sql`
**Kategoria:** Schema consistency

**Dowód:** Komentarz w migracji: "Jeśli FK jeszcze nie istnieje, uruchom ręcznie..." — deploy nie doda FK automatycznie.

**Naprawa:** Dodaj FK przez DDL z defensywną konwencją (try-drop-then-add).

---

### D12 — Tests coverage szacowane ~10% (6 test files / ~65 src files)
**Kategoria:** Quality

Brak testów dla:
- `AccountDeletionService` (najbardziej destrukcyjna operacja + K2 fix)
- `PasswordResetService`
- `MatchingService`, `PersonImportService`, Discovery Sources
- `EmailService` (K1 fix — sanitizeHeader wymaga testów jednostkowych)

---

### D13 — `FingerprintService.php` — weryfikacja czy są unit testy
**Plik:** `src/Services/Discovery/FingerprintService.php` + `tests/`
**Kategoria:** Quality

Deterministyczny algorytm fingerprint **musi** mieć testy regresyjne (zmiana implementacji bez testów = wszystkie hashe w `global_person_index` stają się nieważne).

---

### D14 — `GlobalIndexService` brak batching przy `unindexTree`
**Plik:** `src/Services/Discovery/GlobalIndexService.php`
**Kategoria:** Performance

Dla drzewa z 5000 osób — analogiczny N+1 co w reindexTree (jeśli per-person unindex).

---

## POZYTYWNE [+]

### Utrzymane z poprzednich audytów
- **Tenant isolation perfekcyjny** — tree_id filter w każdym query
- **Zero SQL injection** — PDO prepared wszędzie
- **CSRF full coverage** — verifyCsrf() na każdym POST
- **Session security** — httponly, SameSite=Strict, regeneration, absolute timeout 8h
- **Upload security** — finfo MIME, UUID filenames, content check (ZAD-2.2)
- **Rate limiting** — centralny RateLimiter w całym systemie
- **Privacy by design** — GlobalIndexService 5 reguł eligibility, GEDCOM export filtruje żyjące
- **Audit trail** — source_audit_log dla kluczowych akcji

### Nowe pozytywy z backend-3 + Discovery
- **SMTP sanityzacja** — sanitizeHeader() w obu metodach EmailService
- **AccountDeletion bezpieczne** — transferOwnership + NOT EXISTS query
- **Privacy Policy + Consent flow** — pełny pipeline rejestracji
- **CSP nowe endpointy** — `/health`, `/privacy`, `/terms`, `/settings/restrict` — wszystkie prawidłowo
- **Session version synchronizacja** — P7 fix z DB read zamiast lokalnego +1
- **RODO Art. 18 restriction** — kompletny flow: migration 013, backend, UI, auto-clear
- **Pluggable MatchSource architecture** — **wzorcowy OCP**, clean interface, DI-friendly
- **Exception isolation per source** — `try/catch` w `findCandidates` zapewnia odporność na pojedyncze failures
- **Dedup notyfikacji** — `existsRecentForLink` chroni przed spamem przy bulk import
- **isLiving filter w MatchingService** — wczesny return, privacy by design
- **CrossTreeMatchSource immutable snapshot** — czyta z global_person_index nie z persons
- **HMAC treeRef anonymization** — `currentUserId` w hash chroni przed enumeracją
- **PersonImportService optimistic lock** — `UPDATE WHERE status='pending'` + transakcja
- **FamilySearch/Geneteka stubs** — zarejestrowane warunkowo przez env, brak aktywnych calls w obecnym stanie
- **NotificationRepository::existsRecentForLink** + **UserRepository::isDiscoveryOptedIn** — dedykowane metody zamiast raw SQL w serwisach (clean repo pattern)

---

## STRIDE Threat Model — updates vs backend-3

| Threat | Aktor | Komponent | Ryzyko | Status | Fix |
|--------|-------|-----------|--------|--------|-----|
| **S**poofing | User | Consent proof | Brak dowodu w DB | ❌ **K1** | migration + terms_accepted_at |
| **S**poofing | User | Login | Credential stuffing | ✅ OK | Rate limit + bcrypt12 |
| **T**ampering | User | Name header | SMTP injection | ✅ OK (backend-3 K1) | sanitizeHeader |
| **T**ampering | User | session_version | Rozjechanie z DB | ✅ OK (backend-3 P7) | getSessionVersion |
| **R**epudiation | Org | Geneteka search | Brak audit Art. 30 | ❌ **P7** | Audit log w search() |
| **R**epudiation | User | Consent | Nie można udowodnić | ❌ **K1** | Persist w DB |
| **I**nformation Disclosure | Attacker | error_log | userId w logach | ❌ **P4** | Usuń userId z log |
| **I**nformation Disclosure | User | Export Art. 15 | tree_members/password_resets missing | ❌ **P6** | Dodaj sekcje |
| **I**nformation Disclosure | Admin | /health | metadata disclosure | ⚠️ **D1** | Usuń ts |
| **D**enial of Service | User | GEDCOM bulk import | Synchroniczny event loop | ❌ **P1** | Batch event / async |
| **D**enial of Service | User | reindexTree | N+1 + brak chunking | ❌ **P3** | Cache tree/user + chunk |
| **D**enial of Service | Org | External source timeout | Blokuje request | ❌ **P5** | Timeout in interface |
| **E**levation of Privilege | User | Discovery opt-out bypass | Art. 7(3) violation | ❌ **P2** | isDiscoveryOptedIn check |
| **E**levation of Privilege | User | GenetykaMatchSource path | Path traversal ready | ❌ **P11** | realpath + allowlist |
| **Legal** | Org | Discovery → USA | Rozdział V naruszenie | ❌ **K2** | Privacy Policy + SCC |

---

## Ocena końcowa

| Kategoria | K | P | D | + |
|-----------|---|---|---|---|
| **OWASP Top 10** | 0 | 5 | 5 | 8 |
| **RODO** | 2 | 3 | 1 | 5 |
| **NIS2** | 0 | 1 | 0 | 2 |
| **WCAG 2.1 AA** | 0 | 1 | 2 | 2 |
| **Architektura** | 0 | 4 | 6 | 7 |
| **Testability** | 0 | 0 | 2 | — |
| **RAZEM** | **2** | **14** | **16** | **24** |

**Werdykt:** **PASS WITH CONDITIONS**

### Trend wszystkich audytów

| Audit | K | P | D | +  | Komentarz |
|-------|---|---|---|----|-----------|
| #1 backend | 2 | 17 | 19 | 29 | Baseline — pełny system |
| #2 backend-2 | 2 | 8 | 4 | — | Re-audit po #1 |
| #3 backend-3 | 3 | 14 | 13 | 19 | Re-audit po #2 — nowe findings |
| **#4 backend-4** | **2** | **14** | **16** | **24** | **Weryfikacja #3 ✅ + Discovery Sources** |

**Obserwacja:** W audycie #4 krytyczne spadły (3→2) ale poważne zostały stabilne (14→14). Powód: **nowy moduł Discovery Sources** wprowadza własne problemy (5 POW + 6 DROB) równoważone przez **pozytywy z backend-3** (9 PASS weryfikacji).

### Blokuje deploy
- **K1** — brak dowodu zgody (Art. 7(1))
- **K2** — Privacy Policy nie informuje o Discovery USA transfer (Rozdział V)

### Wymagane przed deploy produkcyjnym
- **P1-P11** — wszystkie quick wins (łącznie ~8h)

### Deferred (post-MVP)
- **D1-D14** — cleanup, WCAG fine-tuning, observability

---

## Zalecenia procesowe

1. **Re-audit #5 po naprawie K1-K2** — waliduj privacy policy + consent persistence
2. **Pentest zewnętrzny** — po zamknięciu P1-P11, przed first production deploy
3. **Unit tests dla Discovery** — priorytet! FingerprintService, MatchingService, PersonImportService
4. **Monitoring setup** — alerty na synchroniczny event loop przy bulk import (ile eventów/sec)
5. **Job queue research** — async background jobs z backend-2 Faza 3 stają się krytyczne przy aktywacji FamilySearch/Geneteka
