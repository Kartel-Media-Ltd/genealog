# Zadania: Person Discovery

> Status: [ ] = do zrobienia, [x] = ukończone, [~] = w trakcie

---

## Faza 1: Schema + FingerprintService + GlobalIndexService

- [ ] Utworzyć `migrations/007_discovery.sql` z ALTER persons (fingerprint_hash CHAR(64), name_soundex CHAR(8)) + indeksy
- [ ] W tej samej migracji: ALTER trees ADD is_indexed_globally TINYINT(1) NOT NULL DEFAULT 0
- [ ] ALTER users ADD discovery_opt_in TINYINT(1) NOT NULL DEFAULT 0
- [ ] CREATE TABLE global_person_index z UNIQUE(person_id), FK do trees/persons/users, INDEX(fingerprint_hash, region)
- [ ] CREATE TABLE person_match_suggestions z UNIQUE(person_id, source_type, source_id), INDEX(created_for_user, status)
- [ ] CREATE TABLE notifications z INDEX(user_id, is_read)
- [ ] CREATE TABLE source_audit_log z INDEX(user_id), INDEX(created_at), retencja 3 lata
- [ ] **User: source .env.local && docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/007_discovery.sql**
- [ ] Utworzyć `src/Services/Discovery/FingerprintService.php` z metodami: compute(), extractRegion(), computeSoundex(), isHistorical()
- [ ] W FingerprintService::computeSoundex() użyć iconv('UTF-8','ASCII//TRANSLIT//IGNORE') przed soundex() — polskie diakrytyki
- [ ] FingerprintService::compute() zwraca null gdy brak firstName lub lastName
- [ ] FingerprintService::isHistorical(): is_living = 0 AND (birth_year IS NULL OR birth_year < YEAR(NOW()) - 100)
- [ ] Utworzyć `src/Services/Discovery/GlobalIndexService.php` z metodami: indexPerson(), unindexPerson(), unindexTree(), reindexTree()
- [ ] GlobalIndexService::indexPerson() weryfikuje WSZYSTKIE reguły RODO: is_living, birth_year, visibility, tree.is_indexed_globally, owner.discovery_opt_in
- [ ] GlobalIndexService::unindexTree() usuwa wszystkie rekordy z global_person_index dla danego tree_id
- [ ] Utworzyć `src/Core/EventDispatcher.php` z metodami static: on(), emit(), clear()
- [ ] W `src/Services/PersonService.php` po create(): EventDispatcher::emit('person.created', $person, $userId)
- [ ] W PersonService po update(): EventDispatcher::emit('person.updated', $person, $userId)
- [ ] W PersonService po delete(): EventDispatcher::emit('person.deleted', $personId)
- [ ] W `public/index.php` w sekcji DI: zarejestrować GlobalIndexService, MatchingService (stub) i listenery EventDispatcher
- [ ] Listener 'person.created': GlobalIndexService::indexPerson
- [ ] Listener 'person.created': MatchingService::findAndNotifyMatches (stub — pusta implementacja na razie)
- [ ] Listener 'person.deleted': GlobalIndexService::unindexPerson
- [ ] Listener 'tree.indexed_globally.disabled': GlobalIndexService::unindexTree
- [ ] Utworzyć `bin/reindex-all.php` — CLI script iterujący osoby i wywołujący GlobalIndexService::indexPerson dla każdej
- [ ] **User: php bin/reindex-all.php** — retroaktywne wypełnienie indeksu dla istniejących osób

---

## Faza 2: MatchSourceInterface + LocalTreeMatchSource + DiscoveryController::search

- [ ] Utworzyć `src/Services/Discovery/DTO/SearchCriteria.php` z constructor + normalize()
- [ ] Utworzyć `src/Services/Discovery/DTO/SearchContext.php` (currentUserId, currentTreeId, accessibleTreeIds)
- [ ] Utworzyć `src/Services/Discovery/DTO/PersonData.php`
- [ ] Utworzyć `src/Services/Discovery/DTO/MatchResult.php` z polami: sourceType, sourceId, firstName, lastName, birthYear, region, confidence, treeRef
- [ ] Utworzyć `src/Services/Discovery/MatchSourceInterface.php` z sygnaturami: getName(), search(), fetchDetails(), isAvailable()
- [ ] Utworzyć `src/Services/Discovery/MatchSourceRegistry.php` z metodami: register(), get(), getEnabled()
- [ ] Utworzyć `src/Services/Discovery/Sources/LocalTreeMatchSource.php` — szuka w persons WHERE tree_id IN ($ctx->accessibleTreeIds)
- [ ] LocalTreeMatchSource: wyłącznie prepared statements, żadnych konkatenacji SQL
- [ ] LocalTreeMatchSource::search() najpierw exact (fingerprint_hash = ?), potem fuzzy (name_soundex = ? + Levenshtein PHP)
- [ ] Utworzyć `src/Services/Discovery/MatchingService.php` z findCandidates() i findAndNotifyMatches()
- [ ] MatchingService::findCandidates(): exact najpierw, fuzzy gdy <3 wyniki, confidence threshold 0.5
- [ ] Wynik findCandidates(): array podzielony na klucze 'local', 'crossTree', 'external'
- [ ] Utworzyć `src/Repositories/DiscoveryRepository.php` z metodami: saveSuggestion(), findSuggestions(), updateSuggestionStatus(), suggestionExists()
- [ ] Utworzyć `src/Controllers/DiscoveryController.php` z metodą search()
- [ ] DiscoveryController::search(): weryfikacja że currentUser ma role editor/owner w tree_id (tree_members check)
- [ ] DiscoveryController::search(): zwraca JSON {local: [], crossTree: [], external: []}
- [ ] W `public/index.php`: routing GET /api/discovery/search → DiscoveryController::search
- [ ] W `public/index.php` sekcja DI: zarejestrować MatchSourceRegistry, LocalTreeMatchSource, MatchingService, DiscoveryController, DiscoveryRepository

---

## Faza 3: CrossTreeMatchSource + privacy + settings panel

- [ ] Utworzyć `src/Services/Discovery/Sources/CrossTreeMatchSource.php`
- [ ] CrossTreeMatchSource::search() szuka w global_person_index WHERE fingerprint_hash = ? OR name_soundex = ?
- [ ] CrossTreeMatchSource: wykluczyć rekordy z własnych drzew użytkownika (owner_user_id != currentUserId)
- [ ] P4: response BEZ tree_name — zamiast tego treeRef = "Drzewo #" . substr(hash($treeId), 0, 4)
- [ ] P4: response BEZ photo, notes, person_id, tree_id, owner_email — tylko firstName, lastName, birthYear (rok), region (województwo)
- [ ] Zarejestrować CrossTreeMatchSource w MatchSourceRegistry w public/index.php
- [ ] Utworzyć `src/Controllers/DiscoveryController::discoverySettings()` (obsługuje GET i POST)
- [ ] GET discoverySettings(): sprawdza role owner, zwraca formularz z is_indexed_globally, discovery_opt_in
- [ ] POST discoverySettings(): waliduje owner role, UPDATE trees SET is_indexed_globally = ? + UPDATE users SET discovery_opt_in = ?
- [ ] Po włączeniu (1→1): wywołać GlobalIndexService::reindexTree($treeId)
- [ ] Po wyłączeniu (1→0): EventDispatcher::emit('tree.indexed_globally.disabled', $treeId)
- [ ] Utworzyć `src/views/pages/trees/settings/discovery.php` z formularzem (checkbox + info o RODO + button Reindeksuj)
- [ ] Routing GET /trees/{id}/settings/discovery → discoverySettings
- [ ] Routing POST /trees/{id}/settings/discovery → discoverySettings
- [ ] Dodać link "Ustawienia odkrywania" w views/pages/trees/show.php (tylko dla owner)
- [ ] P1 rate limit: dodać endpoint 'discovery_search' do sprawdzenia w rate_limits, max 30 req/min per (ip, user_id)

---

## Faza 4: Autosuggest UI w formularzu osoby

- [ ] W `src/views/pages/trees/persons/create.php` dodać Alpine component `x-data="personDiscovery(<?= $treeId ?>)"`
- [ ] Zaimplementować $watch na firstName + lastName (2 osobne watchers)
- [ ] Debounce 400ms przed każdym fetch (clearTimeout + setTimeout)
- [ ] Warunek minimujący: min 2 znaki w obu polach wymagane przed fetch
- [ ] Fetch GET /api/discovery/search?treeId=X&firstName=Y&lastName=Z
- [ ] Panel pod formularzem widoczny gdy results nie jest pusty
- [ ] Sekcja "Z Twoich drzew" (local) — accordion z licznikiem wyników
- [ ] Sekcja "Z innych drzew" (crossTree) — accordion z licznikiem, info o anonimizacji
- [ ] Sekcja "Z zewnętrznych baz" (external) — accordion z licznikiem (puste w Fazie 4)
- [ ] Karta wyniku: imię + nazwisko, rok urodzenia, źródło, confidence badge
- [ ] Button "Użyj tych danych" w każdej karcie
- [ ] "Użyj tych danych" wypełnia pola: first_name, last_name, birth_date (rok), birth_place
- [ ] Loading spinner podczas fetch (x-show="loading")
- [ ] Empty state: "Brak dopasowań" (x-show="!loading && noResults")
- [ ] Error handling: network failure → toast Alpine (x-show="error")

---

## Faza 5: Notifications + bell icon

- [ ] Utworzyć `src/Repositories/NotificationRepository.php` z metodami: create(), findByUser(), countUnread(), markRead()
- [ ] Utworzyć `src/Services/Discovery/NotificationService.php` z metodą dispatch()
- [ ] NotificationService::dispatch(): sprawdza dedup — ten sam typ + user + 24h → skip (SELECT COUNT)
- [ ] MatchingService::findAndNotifyMatches(): dla cross-tree match → NotificationService::dispatch('person_match', ...)
- [ ] Notyfikacja cross-tree: title "Znaleziono potencjalne powiązanie", body BEZ nazwy drzewa źródłowego
- [ ] Dodać do DiscoveryController metodę notificationsCount() → GET /api/notifications/count
- [ ] notificationsCount(): header Cache-Control: max-age=25, zwraca JSON {count: N}
- [ ] Dodać do DiscoveryController metodę notificationsList() → GET /api/notifications (ostatnie 10)
- [ ] Dodać do DiscoveryController metodę notificationRead() → POST /api/notifications/{id}/read
- [ ] notificationRead(): WHERE user_id = currentUserId (IDOR check)
- [ ] Routing dla 3 endpointów notifications w public/index.php
- [ ] W `src/views/templates/AppLayout.php` dodać Alpine component `x-data="notificationBell()"`
- [ ] Bell icon z badge counter (czerwona kropka z liczbą)
- [ ] Polling co 30s (setInterval w init())
- [ ] Click na bell → openDropdown() → fetch /api/notifications → wyświetl listę
- [ ] Click na powiadomieniu → POST markRead + redirect do link
- [ ] aria-label "Powiadomienia, X nowych" na bell icon

---

## Faza 6: Import flow + panel sugestii

- [ ] Utworzyć `src/Services/Discovery/PersonImportService.php` z metodą importFromMatch()
- [ ] importFromMatch(): merge danych — uzupełnia brakujące pola, NIE nadpisuje wypełnionych
- [ ] P3: UPDATE person_match_suggestions SET status='imported' WHERE id=? AND status='pending' (optimistic lock)
- [ ] Sprawdzić affected_rows po UPDATE — jeśli 0 → throw RaceConditionException (bail bez tworzenia osoby)
- [ ] importFromMatch(): INSERT do source_audit_log (action='import', source_type, source_id, target_person_id)
- [ ] Dodać do DiscoveryController metodę importMatch() → POST /api/discovery/match/{id}/import
- [ ] importMatch(): K2 — WHERE created_for_user = currentUserId (IDOR check)
- [ ] importMatch(): sprawdzić editor/owner role dla tree_id z match
- [ ] importMatch(): CSRF verify
- [ ] Dodać do DiscoveryController metodę rejectMatch() → POST /api/discovery/match/{id}/reject
- [ ] rejectMatch(): K2 — WHERE created_for_user = currentUserId (IDOR check)
- [ ] rejectMatch(): INSERT do source_audit_log (action='reject')
- [ ] Routing POST /api/discovery/match/{id}/import → DiscoveryController::importMatch
- [ ] Routing POST /api/discovery/match/{id}/reject → DiscoveryController::rejectMatch
- [ ] W `src/views/pages/trees/persons/show.php` dodać sekcję "Możliwe powiązania" (conditional: gdy status='pending')
- [ ] Foreach suggestions: karta z confidence badge + źródło + imię + rok urodzenia
- [ ] Button "Akceptuj" → fetch POST /api/discovery/match/{id}/import → ukryj kartę + toast
- [ ] Button "Odrzuć" → fetch POST /api/discovery/match/{id}/reject → ukryj kartę
- [ ] UI update bez przeładowania strony (Alpine x-show na podstawie statusu)

---

## Faza 7: External adapters

- [ ] Utworzyć `src/Services/Discovery/Sources/FamilySearchMatchSource.php`
- [ ] FamilySearchMatchSource: adapter dla FamilySearchService z planu `registries`
- [ ] FamilySearchMatchSource::search(): transform wyniki RegistryInterface → MatchResult[] z source_type='external'
- [ ] FamilySearchMatchSource::isAvailable(): sprawdza getenv('FAMILYSEARCH_CLIENT_ID')
- [ ] Utworzyć `src/Services/Discovery/Sources/GenetykaMatchSource.php`
- [ ] GenetykaMatchSource: adapter dla GenetykaService (lokalny DB z CSV dump PTG)
- [ ] GenetykaMatchSource::isAvailable(): sprawdza getenv('GENETEKA_LOCAL_DB')
- [ ] Conditional registration w public/index.php (tylko gdy env vars ustawione)
- [ ] W autosuggest UI: sekcja "Z zewnętrznych baz" wyświetla wyniki external gdy istnieją
- [ ] source_audit_log: INSERT dla każdego external query (action='external_search')
- [ ] MatchResult dla external: confidence bazowana na polu "match_score" z rejestru lub fallback 0.6

---

## Faza 8: Dokumentacja

- [ ] Utworzyć `dev/docs/discovery-architecture.md` z opisem MatchSourceInterface i przepływem danych
- [ ] Diagram ASCII przepływu: formularz → DiscoveryController → MatchingService → MatchSourceRegistry → sources
- [ ] Przykład rejestracji nowego source (krok po kroku)
- [ ] Sekcja "RODO compliance" — reguły kwalifikowania, anonimizacja cross-tree, audit log
- [ ] Sekcja "EventDispatcher hooks" — lista eventów, kolejność listenerów, dodawanie nowych
- [ ] Zaktualizować CLAUDE.md — dodać sekcję "Person Discovery" z opisem architektury
- [ ] Zaktualizować MEMORY.md — dodać wpis o zaimplementowanym Person Discovery
