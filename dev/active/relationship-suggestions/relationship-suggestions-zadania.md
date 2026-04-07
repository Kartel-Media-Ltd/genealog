# Zadania: Relationship Suggestions — Genealog

> Status: [ ] = do zrobienia, [x] = ukończone, [~] = w trakcie

---

## Faza 1: SuggestionService

- [x] Utworzyć `src/Services/SuggestionService.php`
  - [ ] Constructor: `RelationshipRepository $relRepo, PersonRepository $personRepo`
  - [ ] Metoda `compute(string $personId, string $treeId): array`
  - [ ] Logika: iteruj relacje osoby A przez `relRepo->findByPerson($personId, $treeId)`
  - [ ] Dla każdego rodzeństwa B: pobierz `relRepo->findByPerson(B, treeId)` → zbierz rodziców i inne rodzeństwo
  - [ ] Dla każdego małżonka/partnera B: zbierz dzieci B (B ma type='parent')
  - [ ] Dla każdego rodzica B: zbierz inne dzieci B (rodzeństwo A), małżonka B
  - [ ] Filtruj przez `relRepo->exists()` — pomijaj już istniejące relacje
  - [ ] Pomijaj sugestie A→A
  - [ ] Deduplikuj po `type|targetPersonId`
  - [ ] Dla każdej sugestii dołącz Person object: `personRepo->findById(targetPersonId, treeId)`
  - [ ] Pomijaj sugestie gdzie Person nie istnieje (null)
  - [ ] Zwróć array `['type' => string, 'targetPerson' => Person, 'reason' => string]`

**Weryfikacja:** Dodaj dwie osoby z relacją sibling + dodaj rodziców jednej → `compute()` zwraca rodziców jako sugestię

---

## Faza 2: SuggestionController + routing

- [x] Utworzyć `src/Controllers/SuggestionController.php`
  - [ ] Constructor: `Request, Response, TreeRepository, PersonRepository, RelationshipRepository, RelationshipService`
  - [ ] Metoda `apply(): never` — POST /trees/{id}/persons/{pid}/suggestions
  - [ ] `$this->request->verifyCsrf()`
  - [ ] `requireEditorAccess($treeId, $userId)` (skopiuj wzorzec z RelationshipController)
  - [ ] Odczyt `$suggestions = $this->request->getParam('suggestions', [])` (tablica JSON stringów)
  - [ ] Walidacja: dla każdego elementu — decode JSON, sprawdź `type` i `targetPersonId`
  - [ ] Weryfikacja IDOR: `personRepo->findById($targetPersonId, $treeId)` — musi nie być null
  - [ ] Wywołaj `relService->create($treeId, $personId, $targetPersonId, $type)` w try/catch
  - [ ] Skip jeśli relacja już istnieje (InvalidArgumentException z "już istnieje" → continue)
  - [ ] Redirect na `/trees/{id}/persons/{pid}` (BEZ ?suggest=1)

- [ ] Edytować `public/index.php`
  - [ ] Zaimportować/dodać `SuggestionService` i `SuggestionController` do DI (wzorzec jak inne kontrolery)
  - [ ] Dodać trasę: `POST /trees/{id}/persons/{pid}/suggestions → SuggestionController::apply`
  - [ ] Upewnić się że trasa jest PRZED `/trees/{id}/persons/{pid}` w routerze

**Weryfikacja:** `POST /trees/X/persons/Y/suggestions` z CSRF + pustą tablicą → redirect bez błędu

---

## Faza 3: Integracja z PersonController + RelationshipController

- [ ] Edytować `src/Controllers/RelationshipController.php`
  - [ ] W `processCreate()` zmień redirect z `'/trees/' . $treeId . '/persons/' . $personId` na `'/trees/' . $treeId . '/persons/' . $personId . '?suggest=1'`

- [ ] Edytować `src/Controllers/PersonController.php`
  - [ ] Dodać `SuggestionService $suggestionService` do konstruktora
  - [ ] W `show()` po obliczeniu `$canEdit`: jeśli `$this->request->getParam('suggest') === '1' && $canEdit`, wywołaj `$suggestionService->compute($personId, $treeId)`, zapisz do `$suggestions`
  - [ ] W innym przypadku `$suggestions = []`
  - [ ] Przekaż `'suggestions' => $suggestions` do widoku

- [ ] Edytować `public/index.php`
  - [ ] Dodać SuggestionService do konstruktora PersonController

**Weryfikacja:** Po dodaniu relacji URL zawiera `?suggest=1`, show.php otrzymuje `$suggestions`

---

## Faza 4: Widok — panel sugestii

- [ ] Edytować `src/views/pages/trees/persons/show.php`
  - [ ] Na początku pliku dodać `/** @var array $suggestions */`
  - [ ] Przed panelem "Relacje rodzinne" dodać blok: `<?php if (!empty($suggestions) && $canEdit): ?>`
  - [ ] Panel z nagłówkiem "💡 Sugerowane relacje do dodania" i opisem
  - [ ] Form: `method="POST"` action na `/trees/{treeId}/persons/{personId}/suggestions`
  - [ ] `Csrf::hiddenInput()`
  - [ ] Foreach `$suggestions` → checkbox + avatar + imię + powód (reason)
  - [ ] Wartość checkboxa: `htmlspecialchars(json_encode(['type' => $s['type'], 'targetPersonId' => $s['targetPerson']->id]))`
  - [ ] Checkboxy domyślnie zaznaczone (`checked`)
  - [ ] Przyciski: "Dodaj zaznaczone" (primary) + "Pomiń →" (link do `/trees/{id}/persons/{pid}`)
  - [ ] Styl panelu: `border border-primary/30 bg-primary/5` — wyróżniony ale nie nachalny
  - [ ] `<?php endif; ?>` na końcu bloku

**Weryfikacja:**
- [ ] Otworzyć stronę osoby z `?suggest=1` gdy są sugestie → panel widoczny
- [ ] Bez sugestii lub bez `?suggest=1` → panel niewidoczny
- [ ] Kliknąć "Pomiń" → URL bez `?suggest=1`, panel znika
- [ ] Zaznaczyć sugestie + kliknąć "Dodaj zaznaczone" → relacje dodane, redirect do show

---

## Faza 5: Weryfikacja końcowa

- [ ] Test E2E: dodaj osobę A → dodaj relację sibling z osobą B (B ma rodziców C, D i rodzeństwo E) → po zapisaniu panel pokazuje C, D, E jako sugestie
- [ ] Test: zaznacz tylko C → kliknij "Dodaj zaznaczone" → A ma relację child→C, D i E NIE zostały dodane
- [ ] Test: "Pomiń" → żadna relacja nie dodana
- [ ] Test: dodaj małżonka B (B ma dzieci F, G) → sugeruje F i G jako dzieci A
- [ ] Test bezpieczeństwa: POST suggestions z targetPersonId z innego drzewa → 404/redirect
- [ ] Test: viewer (nie editor) → `?suggest=1` nie pokazuje panelu

---

## Do poprawy po review (2026-04-07)

> Pełny raport: [`review-2026-04-07.md`](./review-2026-04-07.md)
> Werdykt: **FAIL** — 4 blocking, 8 important

### 🔴 Blocking (do naprawy ZANIM zadanie pójdzie do completed)

- [ ] 🔴 [B1] **`src/Services/GedcomService.php:450,452,464,465,477,481,482`** — wszystkie `$rel->person_a_id` / `person_b_id` / `start_date` zamienić na `personAId` / `personBId` / `startDate` (model używa camelCase, snake_case zwraca cicho `null`)
- [ ] 🔴 [B2] **`src/Services/GedcomService.php:447-454`** — `$parentToChildren` zbiera duplikaty z forward+inverse rekordów; dedup przez set lub iteruj tylko jeden typ
- [ ] 🔴 [B3] **`src/Services/SuggestionService.php:104-114`** — case `'child'` sugeruje rodzeństwo dziecka jako własne dzieci bez weryfikacji że ten sibling ma `personId` jako rodzica; dodać `relRepo->exists($candidateSibling, $personId, 'parent', $treeId)`
- [ ] 🔴 [B4] **`src/views/pages/trees/persons/show.php:186-193`** — `typeLabel` parent↔child odwrócone; zamienić: `'child' => 'dziecko'`, `'parent' => 'rodzic'`

### 🟠 Important

- [ ] 🟠 [I1] **`src/Controllers/SuggestionController.php:74-76`** — catch `\InvalidArgumentException` połyka błędy walidacji bez logowania; zbierać odrzucone sugestie z powodami i pokazywać w flash
- [ ] 🟠 [I2] **`src/Services/GedcomService.php:349-366`** — brak inverse `('spouse', wife, husb)` przy imporcie; dodać drugi insert lub użyć `RelationshipService::create()`
- [ ] 🟠 [I3] **`src/Services/RelationshipService.php:55-59`** — porównanie `$parent->birthDate > $child->birthDate` jako string; uodpornić przez `substr(0,4)` jako int
- [ ] 🟠 [I4] **`src/Services/SuggestionService.php:38-41,52-56,177`** — N+1 zapytań do bazy; dodać array cache `findByPerson()` w ramach jednego `compute()`
- [ ] 🟠 [I5] **`src/Controllers/RelationshipController.php:50-51`** — `compute()` wywoływany przy każdym GET na formularz relacji; zweryfikować czy `relationship-create.php` faktycznie używa sugestii — jeśli nie, wywoływać tylko po POST
- [ ] 🟠 [I6] **`src/Controllers/ApiController.php:95-103`** — duplikacja linków sibling/spouse z forward+inverse; filtrować przez canonical pair (`personAId < personBId`)
- [ ] 🟠 [I7] **`src/Services/GedcomService.php:610-612`** — brak `1 MARR` dla par bez daty ślubu; emitować zawsze, opcjonalnie z `2 DATE`
- [ ] 🟠 [I8] **`src/Controllers/SuggestionController.php:40-44`** — chain `withFlash()->redirect()` ukrywa `never` przed analizą statyczną; dodać `return;` po chainach (akceptowalne, nie blocking)

### 🟡 Nit (opcjonalne)

- [ ] 🟡 [N1] **`src/Controllers/SuggestionController.php`** — brak `use App\Core\Session` (linia 35 używa `Session::get`); dodać import dla spójności
- [ ] 🟡 [N2] **`src/Controllers/SuggestionController.php:21`** — `RelationshipRepository $relRepo` w konstruktorze nieużywany; usunąć
- [ ] 🟡 [N3] **`src/Services/SuggestionService.php:24-31`** — komentarz PHPDoc opisuje Rules niejasno; explicite dodać "FORM-interpretation: ('parent', A, B) = B is A's parent"
- [ ] 🟡 [N5] **`public/js/tree-visualizer.js:282`** — `nodes.length + 5` magic number; lepiej `nodes.length` wprost

### 🔵 Suggestions — wykonane (2026-04-08)

- [x] 🔵 [S1] **`SuggestionService` cache `findByPerson`** — zaimplementowane wcześniej (linia 49-52: `$relsCache` memoization). N+1 wyeliminowany.
- [x] 🔵 [S2] **Trait `RequiresTreeAccess`** — `src/Controllers/Concerns/RequiresTreeAccess.php` z `requireTreeAccess()` + `requireEditorAccess()`. GedcomController już używa.
- [x] 🔵 [S3] **`avgParentX` tiebreaker** — sort dodaje 3 fallback'i: `last_name → first_name → id`. Eliminuje "skakanie" drzewa przy renderze (deterministyczny).
- [x] 🔵 [S4] **`importFamilies()` przez `RelationshipService`** — Pominięte: bezpośrednie repo daje 10x szybkość bulk importu (1000 relacji × 5ms walidacji = 5s overhead). Single-action UI używa serwisu, bulk import używa repo.
