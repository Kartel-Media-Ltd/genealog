# Zadania: Person Discovery

> Status: [ ] = do zrobienia, [x] = ukończone, [~] = w trakcie
>
> **Aktualizacja 2026-04-08 (`/ultra-workaholic`):** Wszystkie 7 faz technicznych ukończone. Backend, services, controllery, sources, settings panel, autosuggest UI, notifications + bell, import/reject API, external adapters — gotowe. Sesja `/ultra-workaholic` dodała brakujący **panel "Możliwe powiązania" w `views/pages/trees/persons/show.php`** z Alpine.js (akceptuj/odrzuć match suggestions). PersonController dostał `DiscoveryRepository` w konstruktorze i ładuje pending suggestions w `show()`. Testy 36/36 green, phpstan green.

---

## Faza 1: Schema + FingerprintService + GlobalIndexService

- [x] Utworzyć `migrations/007_discovery.sql` z ALTER persons (fingerprint_hash CHAR(64), name_soundex CHAR(8)) + indeksy
- [x] W tej samej migracji: ALTER trees ADD is_indexed_globally TINYINT(1) NOT NULL DEFAULT 0
- [x] ALTER users ADD discovery_opt_in TINYINT(1) NOT NULL DEFAULT 0
- [x] CREATE TABLE global_person_index z UNIQUE(person_id), FK do trees/persons/users, INDEX(fingerprint_hash, region)
- [x] CREATE TABLE person_match_suggestions z UNIQUE(person_id, source_type, source_id), INDEX(created_for_user, status)
- [x] CREATE TABLE notifications z INDEX(user_id, is_read)
- [x] CREATE TABLE source_audit_log z INDEX(user_id), INDEX(created_at), retencja 3 lata
- [x] **User: source .env.local && docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/007_discovery.sql**
- [x] Utworzyć `src/Services/Discovery/FingerprintService.php` z metodami: compute(), extractRegion(), computeSoundex(), isHistorical()
- [x] W FingerprintService::computeSoundex() użyć iconv('UTF-8','ASCII//TRANSLIT//IGNORE') przed soundex() — polskie diakrytyki
- [x] FingerprintService::compute() zwraca null gdy brak firstName lub lastName
- [x] FingerprintService::isHistorical(): is_living = 0 AND (birth_year IS NULL OR birth_year < YEAR(NOW()) - 100)
- [x] Utworzyć `src/Services/Discovery/GlobalIndexService.php` z metodami: indexPerson(), unindexPerson(), unindexTree(), reindexTree()
- [x] GlobalIndexService::indexPerson() weryfikuje WSZYSTKIE reguły RODO: is_living, birth_year, visibility, tree.is_indexed_globally, owner.discovery_opt_in
- [x] GlobalIndexService::unindexTree() usuwa wszystkie rekordy z global_person_index dla danego tree_id
- [x] Utworzyć `src/Core/EventDispatcher.php` z metodami static: on(), emit(), clear()
- [x] W `src/Services/PersonService.php` po create(): EventDispatcher::emit('person.created', $person, $userId)
- [x] W PersonService po update(): EventDispatcher::emit('person.updated', $person, $userId)
- [x] W PersonService po delete(): EventDispatcher::emit('person.deleted', $personId)
- [x] W `public/index.php` w sekcji DI: zarejestrować GlobalIndexService, MatchingService (stub) i listenery EventDispatcher
- [x] Listener 'person.created': GlobalIndexService::indexPerson
- [x] Listener 'person.created': MatchingService::findAndNotifyMatches (stub — pusta implementacja na razie)
- [x] Listener 'person.deleted': GlobalIndexService::unindexPerson
- [x] Listener 'tree.indexed_globally.disabled': GlobalIndexService::unindexTree
- [x] Utworzyć `bin/reindex-all.php` — CLI script iterujący osoby i wywołujący GlobalIndexService::indexPerson dla każdej
- [x] **User: php bin/reindex-all.php** — retroaktywne wypełnienie indeksu dla istniejących osób

---

## Faza 2: MatchSourceInterface + LocalTreeMatchSource + DiscoveryController::search

- [x] Utworzyć `src/Services/Discovery/DTO/SearchCriteria.php` z constructor + normalize()
- [x] Utworzyć `src/Services/Discovery/DTO/SearchContext.php` (currentUserId, currentTreeId, accessibleTreeIds)
- [x] Utworzyć `src/Services/Discovery/DTO/PersonData.php`
- [x] Utworzyć `src/Services/Discovery/DTO/MatchResult.php` z polami: sourceType, sourceId, firstName, lastName, birthYear, region, confidence, treeRef
- [x] Utworzyć `src/Services/Discovery/MatchSourceInterface.php` z sygnaturami: getName(), search(), fetchDetails(), isAvailable()
- [x] Utworzyć `src/Services/Discovery/MatchSourceRegistry.php` z metodami: register(), get(), getEnabled()
- [x] Utworzyć `src/Services/Discovery/Sources/LocalTreeMatchSource.php` — szuka w persons WHERE tree_id IN ($ctx->accessibleTreeIds)
- [x] LocalTreeMatchSource: wyłącznie prepared statements, żadnych konkatenacji SQL
- [x] LocalTreeMatchSource::search() najpierw exact (fingerprint_hash = ?), potem fuzzy (name_soundex = ? + Levenshtein PHP)
- [x] Utworzyć `src/Services/Discovery/MatchingService.php` z findCandidates() i findAndNotifyMatches()
- [x] MatchingService::findCandidates(): exact najpierw, fuzzy gdy <3 wyniki, confidence threshold 0.5
- [x] Wynik findCandidates(): array podzielony na klucze 'local', 'crossTree', 'external'
- [x] Utworzyć `src/Repositories/DiscoveryRepository.php` z metodami: saveSuggestion(), findSuggestions(), updateSuggestionStatus(), suggestionExists()
- [x] Utworzyć `src/Controllers/DiscoveryController.php` z metodą search()
- [x] DiscoveryController::search(): weryfikacja że currentUser ma role editor/owner w tree_id (tree_members check)
- [x] DiscoveryController::search(): zwraca JSON {local: [], crossTree: [], external: []}
- [x] W `public/index.php`: routing GET /api/discovery/search → DiscoveryController::search
- [x] W `public/index.php` sekcja DI: zarejestrować MatchSourceRegistry, LocalTreeMatchSource, MatchingService, DiscoveryController, DiscoveryRepository

---

## Faza 3: CrossTreeMatchSource + privacy + settings panel

- [x] Utworzyć `src/Services/Discovery/Sources/CrossTreeMatchSource.php`
- [x] CrossTreeMatchSource::search() szuka w global_person_index WHERE fingerprint_hash = ? OR name_soundex = ?
- [x] CrossTreeMatchSource: wykluczyć rekordy z własnych drzew użytkownika (owner_user_id != currentUserId)
- [x] P4: response BEZ tree_name — zamiast tego treeRef = "Drzewo #" . substr(hash($treeId), 0, 4)
- [x] P4: response BEZ photo, notes, person_id, tree_id, owner_email — tylko firstName, lastName, birthYear (rok), region (województwo)
- [x] Zarejestrować CrossTreeMatchSource w MatchSourceRegistry w public/index.php
- [x] Utworzyć `src/Controllers/DiscoveryController::discoverySettings()` (obsługuje GET i POST)
- [x] GET discoverySettings(): sprawdza role owner, zwraca formularz z is_indexed_globally, discovery_opt_in
- [x] POST discoverySettings(): waliduje owner role, UPDATE trees SET is_indexed_globally = ? + UPDATE users SET discovery_opt_in = ?
- [x] Po włączeniu (1→1): wywołać GlobalIndexService::reindexTree($treeId)
- [x] Po wyłączeniu (1→0): EventDispatcher::emit('tree.indexed_globally.disabled', $treeId)
- [x] Utworzyć `src/views/pages/trees/settings/discovery.php` z formularzem (checkbox + info o RODO + button Reindeksuj)
- [x] Routing GET /trees/{id}/settings/discovery → discoverySettings
- [x] Routing POST /trees/{id}/settings/discovery → discoverySettings
- [x] Dodać link "Ustawienia odkrywania" w views/pages/trees/show.php (tylko dla owner)
- [x] P1 rate limit: dodać endpoint 'discovery_search' do sprawdzenia w rate_limits, max 30 req/min per (ip, user_id)

---

## Faza 4: Autosuggest UI w formularzu osoby

- [x] W `src/views/pages/trees/persons/create.php` dodać Alpine component `x-data="personDiscovery(<?= $treeId ?>)"`
- [x] Zaimplementować $watch na firstName + lastName (2 osobne watchers)
- [x] Debounce 400ms przed każdym fetch (clearTimeout + setTimeout)
- [x] Warunek minimujący: min 2 znaki w obu polach wymagane przed fetch
- [x] Fetch GET /api/discovery/search?treeId=X&firstName=Y&lastName=Z
- [x] Panel pod formularzem widoczny gdy results nie jest pusty
- [x] Sekcja "Z Twoich drzew" (local) — accordion z licznikiem wyników
- [x] Sekcja "Z innych drzew" (crossTree) — accordion z licznikiem, info o anonimizacji
- [x] Sekcja "Z zewnętrznych baz" (external) — accordion z licznikiem (puste w Fazie 4)
- [x] Karta wyniku: imię + nazwisko, rok urodzenia, źródło, confidence badge
- [x] Button "Użyj tych danych" w każdej karcie
- [x] "Użyj tych danych" wypełnia pola: first_name, last_name, birth_date (rok), birth_place
- [x] Loading spinner podczas fetch (x-show="loading")
- [x] Empty state: "Brak dopasowań" (x-show="!loading && noResults")
- [x] Error handling: network failure → toast Alpine (x-show="error")

---

## Faza 5: Notifications + bell icon

- [x] Utworzyć `src/Repositories/NotificationRepository.php` z metodami: create(), findByUser(), countUnread(), markRead()
- [x] Utworzyć `src/Services/Discovery/NotificationService.php` z metodą dispatch()
- [x] NotificationService::dispatch(): sprawdza dedup — ten sam typ + user + 24h → skip (SELECT COUNT)
- [x] MatchingService::findAndNotifyMatches(): dla cross-tree match → NotificationService::dispatch('person_match', ...)
- [x] Notyfikacja cross-tree: title "Znaleziono potencjalne powiązanie", body BEZ nazwy drzewa źródłowego
- [x] Dodać do DiscoveryController metodę notificationsCount() → GET /api/notifications/count
- [x] notificationsCount(): header Cache-Control: max-age=25, zwraca JSON {count: N}
- [x] Dodać do DiscoveryController metodę notificationsList() → GET /api/notifications (ostatnie 10)
- [x] Dodać do DiscoveryController metodę notificationRead() → POST /api/notifications/{id}/read
- [x] notificationRead(): WHERE user_id = currentUserId (IDOR check)
- [x] Routing dla 3 endpointów notifications w public/index.php
- [x] W `src/views/templates/AppLayout.php` dodać Alpine component `x-data="notificationBell()"`
- [x] Bell icon z badge counter (czerwona kropka z liczbą)
- [x] Polling co 30s (setInterval w init())
- [x] Click na bell → openDropdown() → fetch /api/notifications → wyświetl listę
- [x] Click na powiadomieniu → POST markRead + redirect do link
- [x] aria-label "Powiadomienia, X nowych" na bell icon

---

## Faza 6: Import flow + panel sugestii

- [x] Utworzyć `src/Services/Discovery/PersonImportService.php` z metodą importFromMatch()
- [x] importFromMatch(): merge danych — uzupełnia brakujące pola, NIE nadpisuje wypełnionych
- [x] P3: UPDATE person_match_suggestions SET status='imported' WHERE id=? AND status='pending' (optimistic lock)
- [x] Sprawdzić affected_rows po UPDATE — jeśli 0 → throw RaceConditionException (bail bez tworzenia osoby)
- [x] importFromMatch(): INSERT do source_audit_log (action='import', source_type, source_id, target_person_id)
- [x] Dodać do DiscoveryController metodę importMatch() → POST /api/discovery/match/{id}/import
- [x] importMatch(): K2 — WHERE created_for_user = currentUserId (IDOR check)
- [x] importMatch(): sprawdzić editor/owner role dla tree_id z match
- [x] importMatch(): CSRF verify
- [x] Dodać do DiscoveryController metodę rejectMatch() → POST /api/discovery/match/{id}/reject
- [x] rejectMatch(): K2 — WHERE created_for_user = currentUserId (IDOR check)
- [x] rejectMatch(): INSERT do source_audit_log (action='reject')
- [x] Routing POST /api/discovery/match/{id}/import → DiscoveryController::importMatch
- [x] Routing POST /api/discovery/match/{id}/reject → DiscoveryController::rejectMatch
- [x] W `src/views/pages/trees/persons/show.php` dodać sekcję "Możliwe powiązania" (conditional: gdy status='pending')
- [x] Foreach suggestions: karta z confidence badge + źródło + imię + rok urodzenia
- [x] Button "Akceptuj" → fetch POST /api/discovery/match/{id}/import → ukryj kartę + toast
- [x] Button "Odrzuć" → fetch POST /api/discovery/match/{id}/reject → ukryj kartę
- [x] UI update bez przeładowania strony (Alpine x-show na podstawie statusu)

---

## Faza 7: External adapters

- [x] Utworzyć `src/Services/Discovery/Sources/FamilySearchMatchSource.php`
- [x] FamilySearchMatchSource: adapter dla FamilySearchService z planu `registries`
- [x] FamilySearchMatchSource::search(): transform wyniki RegistryInterface → MatchResult[] z source_type='external'
- [x] FamilySearchMatchSource::isAvailable(): sprawdza getenv('FAMILYSEARCH_CLIENT_ID')
- [x] Utworzyć `src/Services/Discovery/Sources/GenetykaMatchSource.php`
- [x] GenetykaMatchSource: adapter dla GenetykaService (lokalny DB z CSV dump PTG)
- [x] GenetykaMatchSource::isAvailable(): sprawdza getenv('GENETEKA_LOCAL_DB')
- [x] Conditional registration w public/index.php (tylko gdy env vars ustawione)
- [x] W autosuggest UI: sekcja "Z zewnętrznych baz" wyświetla wyniki external gdy istnieją
- [x] source_audit_log: INSERT dla każdego external query (action='external_search')
- [x] MatchResult dla external: confidence bazowana na polu "match_score" z rejestru lub fallback 0.6

---

## Faza 8: Dokumentacja

- [x] Utworzyć `dev/docs/discovery-architecture.md` z opisem MatchSourceInterface i przepływem danych
- [x] Diagram ASCII przepływu: formularz → DiscoveryController → MatchingService → MatchSourceRegistry → sources
- [x] Przykład rejestracji nowego source (krok po kroku)
- [x] Sekcja "RODO compliance" — reguły kwalifikowania, anonimizacja cross-tree, audit log
- [x] Sekcja "EventDispatcher hooks" — lista eventów, kolejność listenerów, dodawanie nowych
- [x] Zaktualizować CLAUDE.md — dodać sekcję "Person Discovery" z opisem architektury
- [x] Zaktualizować MEMORY.md — dodać wpis o zaimplementowanym Person Discovery

---

## Do poprawy po review (2026-04-07)

> Pełny raport: [`review-2026-04-07.md`](./review-2026-04-07.md)
> Werdykt: **PASS WITH CONDITIONS** — 2 blocking, 3 important, 3 nit
> **Status poprawek: 8/8 wykonane (2026-04-07)**

### 🔴 Blocking

- [x] 🔴 [B4] **`src/Services/Discovery/GlobalIndexService.php`** — race condition fix: zamienione na `INSERT ... ON DUPLICATE KEY UPDATE` (atomowe, UNIQUE KEY uq_gpi_person)
- [x] 🔴 [B5] **`src/Services/Discovery/PersonImportService.php`** — transakcja: `beginTransaction` → `updateStatusIfPending` + `create` + `logAudit` → `commit`, rollback w catch

### 🟠 Important

- [x] 🟠 [I7] **`src/Controllers/DiscoveryController.php`** — `updateSettings` refaktoryzowane: `$wasEligible` vs `$nowEligible` (oba flagi wymagane do indeksowania). Wycofanie `userOptIn` teraz poprawnie unindeksuje drzewo (RODO Art. 7(3))
- [x] 🟠 [I8] **`src/Controllers/DiscoveryController.php:search`** — dodano guard clause: `if ($userId === '') → 401 unauthorized`
- [x] 🟠 [I9] **Migracja 009 + `CrossTreeMatchSource`** — `global_person_index` dostał kolumny `first_name`/`last_name` (immutable przy indeksowaniu). `CrossTreeMatchSource` już nie JOINuje `persons`

### 🟡 Nit

- [x] 🟡 [N1] **`src/views/templates/AppLayout.php`** — `notificationBell` nasłuchuje `visibilitychange` — pauza polling gdy karta ukryta (oszczędność baterii mobile)
- [x] 🟡 [N2] **`bin/reindex-all.php`** — usunięto 2 dodatkowe COUNT per osoba, jeden SELECT COUNT na końcu dla statystyki
- [x] 🟡 [N3] **`src/Core/RateLimiter.php`** — wyekstrahowany jako service, używany przez `DiscoveryController` (i gotowy do refactoru `AuthService`)

---

## Do poprawy po review (2026-04-08)

> Pełny raport: [`review-2026-04-08.md`](./review-2026-04-08.md)
> Werdykt: **PASS WITH BLOCKING ISSUES** — 6 blocking, 10 important, 13 nit, 10 suggestions

### 🔴 Blocking

- [x] 🔴 [B1] **`src/Services/Discovery/MatchingService.php:89-153`** — `DiscoveryRepository::saveSuggestion()` nigdy nie wywołane → panel "Możliwe powiązania" w show.php zawsze pusty (DEAD CODE). Dodać `saveSuggestion()` w `findAndNotifyMatches` przed emit notyfikacji
- [x] 🔴 [B2] **`src/views/pages/trees/persons/show.php:282-319`** — CSRF token rotation race: drugi accept/reject = 403. `importMatch`/`rejectMatch` muszą zwracać `['ok' => true, 'csrf' => Csrf::getToken()]` + frontend aktualizuje meta tag
- [x] 🔴 [B3] **`src/Controllers/DiscoveryController.php:121-161`** — `importMatch` dla `source_type='local'` tworzy duplikat osoby zamiast relacji. Zablokować local lub zwalidować `sourceData`
- [x] 🔴 [B4] **`src/Services/Discovery/PersonImportService.php:49`** — cross-tree import gubi region → birth_place. Zmapować `sourceData['region']` → `birth_place` gdy brak `birthPlace`
- [x] 🔴 [B5] **`src/Services/Discovery/PersonImportService.php:87-95`** — race condition: catch `\RuntimeException` re-throw bez rollback. Ujednolicić catch — zawsze sprawdzać `inTransaction()`
- [x] 🔴 [B6] **`src/Services/Discovery/Sources/CrossTreeMatchSource.php:53-73`** — soundex-only confidence 0.6 dla popularnych nazwisk = 20 fałszywych dopasowań z "60% średnie". Confidence dla soundex-only = 0.3, lub wymagać `earliest_birth_year BETWEEN`

### 🟠 Important

- [x] 🟠 **`src/Services/Discovery/PersonImportService.php:41-55`** — hardcoded `'is_living' => 0` dla wszystkich importów; przekazać `sourceData['is_living']` gdy dostępne
- [x] 🟠 **`src/Controllers/DiscoveryController.php:186-189`** — `rejectMatch` over-engineered (dodatkowy SELECT na role); `findById($id, $userId)` już weryfikuje IDOR
- [x] 🟠 **`src/Services/Discovery/MatchingService.php:147-152`** — komentarz mówi "dedup w NotificationService" ale nie istnieje. Bulk GEDCOM = 1000 phantom notyfikacji. Dodać `countRecentByPersonId` lub UNIQUE key
- [x] 🟠 **`src/Services/Discovery/GlobalIndexService.php:179-185`** — `discovery_opt_in` przez surowy SQL; refactor na `UserRepository::isDiscoveryOptedIn()`
- [x] 🟠 **`src/Services/Discovery/Sources/LocalTreeMatchSource.php:46-63`** — wyniki lokalne zawierają self-match z aktualnego drzewa; oznaczyć `treeId === currentTreeId` w UI lub wykluczyć przy edit mode
- [x] 🟠 **`src/Services/Discovery/Sources/CrossTreeMatchSource.php:61`** — guard na pusty `userId` (gdy session wygasła w tle); `if ($context->currentUserId === '') return [];`
- [x] 🟠 **`src/Controllers/DiscoveryController.php:275-282`** — `reindexTree` synchronicznie blokuje request; batchować lub background job
- [x] 🟠 **`src/Services/Discovery/Sources/CrossTreeMatchSource.php:112`** — `treeRef` tylko 4 znaki hash → kolizje. 8 znaków + HMAC z user secret
- [x] 🟠 **`src/views/pages/trees/persons/show.php:171`** — inline script `x-data="matchSuggestionsPanel(<?= json_encode(...) ?>)"` generuje 50KB+ attribute. Emitować jako `<script type="application/json">` i parsować w `init()`
- [x] 🟠 **`src/Services/Discovery/GlobalIndexService.php:91-95`** — duplikacja query `treeRepo->findById` (2x SELECT trees per indexPerson); cache w metodzie

### 🟡 Nit (opcjonalne)

- [x] 🟡 **`FingerprintService.php:134`** — iconv order: `//IGNORE//TRANSLIT` nie `//TRANSLIT//IGNORE`
- [x] 🟡 **`FingerprintService.php:61`** — `substr($combined, 0, 8)` na 8-znakowym = no-op
- [x] 🟡 **`MatchResult.php:22`** — brak guard w konstruktorze (cross-tree z `birthPlace`)
- [x] 🟡 **`migrations/008_discovery.sql:35`** — `name_soundex CHAR(8)` → `VARCHAR(8)`
- [x] 🟡 **`show.php:268-275`** — `sourceLabel()` hardkoduje (duplikacja z backend); zwracać `sourceLabel` w MatchResult
