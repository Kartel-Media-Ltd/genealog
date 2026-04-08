# Zadania: Relationship Suggestions — Genealog

> Status: [ ] = do zrobienia, [x] = ukończone, [~] = w trakcie

---

## Faza 1: SuggestionService

- [x] Utworzyć `src/Services/SuggestionService.php`
  - [x] Constructor: `RelationshipRepository $relRepo, PersonRepository $personRepo`
  - [x] Metoda `compute(string $personId, string $treeId): array`
  - [x] Logika: iteruj relacje osoby A przez `relRepo->findByPerson($personId, $treeId)`
  - [x] Dla każdego rodzeństwa B: pobierz `relRepo->findByPerson(B, treeId)` → zbierz rodziców i inne rodzeństwo
  - [x] Dla każdego małżonka/partnera B: zbierz dzieci B (B ma type='parent')
  - [x] Dla każdego rodzica B: zbierz inne dzieci B (rodzeństwo A), małżonka B
  - [x] Filtruj przez `relRepo->exists()` — pomijaj już istniejące relacje
  - [x] Pomijaj sugestie A→A
  - [x] Deduplikuj po `type|targetPersonId`
  - [x] Dla każdej sugestii dołącz Person object: `personRepo->findById(targetPersonId, treeId)`
  - [x] Pomijaj sugestie gdzie Person nie istnieje (null)
  - [x] Zwróć array `['type' => string, 'targetPerson' => Person, 'reason' => string]`

**Weryfikacja:** Dodaj dwie osoby z relacją sibling + dodaj rodziców jednej → `compute()` zwraca rodziców jako sugestię

---

## Faza 2: SuggestionController + routing

- [x] Utworzyć `src/Controllers/SuggestionController.php`
  - [x] Constructor: `Request, Response, TreeRepository, PersonRepository, RelationshipService`
  - [x] Metoda `apply(): never` — POST /trees/{id}/persons/{pid}/suggestions
  - [x] `$this->request->verifyCsrf()`
  - [x] `requireEditorAccess($treeId, $userId)` (skopiuj wzorzec z RelationshipController)
  - [x] Odczyt `$suggestions = $this->request->getParam('suggestions', [])` (tablica JSON stringów)
  - [x] Walidacja: dla każdego elementu — decode JSON, sprawdź `type` i `targetPersonId`
  - [x] Weryfikacja IDOR: `personRepo->findById($targetPersonId, $treeId)` — musi nie być null
  - [x] Wywołaj `relService->create($treeId, $personId, $targetPersonId, $type)` w try/catch
  - [x] Skip jeśli relacja już istnieje (InvalidArgumentException z "już istnieje" → continue)
  - [x] Redirect na `/trees/{id}/persons/{pid}` (BEZ ?suggest=1)

- [x] Edytować `public/index.php`
  - [x] Zaimportować/dodać `SuggestionService` i `SuggestionController` do DI (wzorzec jak inne kontrolery)
  - [x] Dodać trasę: `POST /trees/{id}/persons/{pid}/suggestions → SuggestionController::apply`
  - [x] Upewnić się że trasa jest PRZED `/trees/{id}/persons/{pid}` w routerze

**Weryfikacja:** `POST /trees/X/persons/Y/suggestions` z CSRF + pustą tablicą → redirect bez błędu

---

## Faza 3: Integracja z PersonController + RelationshipController

- [x] Edytować `src/Controllers/RelationshipController.php`
  - [x] W `processCreate()` zmień redirect z `'/trees/' . $treeId . '/persons/' . $personId` na `'/trees/' . $treeId . '/persons/' . $personId . '?suggest=1'`

- [x] Edytować `src/Controllers/PersonController.php`
  - [x] Dodać `SuggestionService $suggestionService` do konstruktora
  - [x] W `show()` po obliczeniu `$canEdit`: jeśli `$this->request->getParam('suggest') === '1' && $canEdit`, wywołaj `$suggestionService->compute($personId, $treeId)`, zapisz do `$suggestions`
  - [x] W innym przypadku `$suggestions = []`
  - [x] Przekaż `'suggestions' => $suggestions` do widoku

- [x] Edytować `public/index.php`
  - [x] Dodać SuggestionService do konstruktora PersonController

**Weryfikacja:** Po dodaniu relacji URL zawiera `?suggest=1`, show.php otrzymuje `$suggestions`

---

## Faza 4: Widok — panel sugestii

- [x] Edytować `src/views/pages/trees/persons/show.php`
  - [x] Na początku pliku dodać `/** @var array $suggestions */`
  - [x] Przed panelem "Relacje rodzinne" dodać blok: `<?php if (!empty($suggestions) && $canEdit): ?>`
  - [x] Panel z nagłówkiem "💡 Sugerowane relacje do dodania" i opisem
  - [x] Form: `method="POST"` action na `/trees/{treeId}/persons/{personId}/suggestions`
  - [x] `Csrf::hiddenInput()`
  - [x] Foreach `$suggestions` → checkbox + avatar + imię + powód (reason)
  - [x] Wartość checkboxa: `htmlspecialchars(json_encode(['type' => $s['type'], 'targetPersonId' => $s['targetPerson']->id]))`
  - [x] Checkboxy domyślnie zaznaczone (`checked`)
  - [x] Przyciski: "Dodaj zaznaczone" (primary) + "Pomiń →" (link do `/trees/{id}/persons/{pid}`)
  - [x] Styl panelu: `border border-primary/30 bg-primary/5` — wyróżniony ale nie nachalny
  - [x] `<?php endif; ?>` na końcu bloku

**Weryfikacja:**
- [x] Otworzyć stronę osoby z `?suggest=1` gdy są sugestie → panel widoczny
- [x] Bez sugestii lub bez `?suggest=1` → panel niewidoczny
- [x] Kliknąć "Pomiń" → URL bez `?suggest=1`, panel znika
- [x] Zaznaczyć sugestie + kliknąć "Dodaj zaznaczone" → relacje dodane, redirect do show

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

### 🔴 Blocking — naprawione 2026-04-08

> Weryfikacja: 60/60 testów green, phpstan 0 errors. GEDCOM eksport produkuje poprawne FAM z CHIL.

- [x] 🔴 [B1] **`src/Services/GedcomService.php:447-454`** — wszystkie odwołania używają camelCase (`personAId`/`personBId`/`startDate`); model `Relationship` używa camelCase, snake_case zwracało cicho `null`
- [x] 🔴 [B2] **`src/Services/GedcomService.php:486-497`** — set-based dedup `$parentChildSet` (nested array `[parentId][childId] => true`) eliminuje duplikaty z forward+inverse rekordów relacji przed redukcją do `$parentToChildren`
- [x] 🔴 [B3] **`src/Services/SuggestionService.php:118-143`** — `case 'child'` sprawdza `relRepo->exists($candidateChildId, $personId, 'parent', $treeId)` w linii 126 przed sugerowaniem rodzeństwa dziecka jako własne dziecko (chroni przed false positives z poprzednich małżeństw drugiego rodzica)
- [x] 🔴 [B4] **`src/views/pages/trees/persons/show.php:361-368`** — `typeLabel` zgodne z konwencją FORM-interpretation: `'parent' => 'rodzic'`, `'child' => 'dziecko'`

### 🟠 Important — zweryfikowane i naprawione 2026-04-08

- [x] 🟠 [I1] **`src/Controllers/SuggestionController.php`** — full error handling: `$rejected[]` zbiera odrzucone z powodami, flash `warning`/`error`/`success`/`info` w zależności od wyniku
- [x] 🟠 [I2] **`src/Services/GedcomService.php:422-425`** — importFamilies() insertuje OBA kierunki: `(aId, bId, 'spouse')` + `(bId, aId, 'spouse')`
- [x] 🟠 [I3] **`src/Services/RelationshipService.php:58-59`** — `(int) substr($parent->birthDate, 0, 4)` — porównanie int, nie string
- [x] 🟠 [I4] **`src/Services/SuggestionService.php:49-52`** — `$relsCache` memoization eliminuje N+1; `$relsOf()` closure cachuje wyniki per personId
- [x] 🟠 [I5] **`src/Controllers/RelationshipController.php:50`** — `compute()` w `showCreate()` jest intentional: `relationship-create.php:179-220` wyświetla panel sugestii w formularzu tworzenia relacji
- [x] 🟠 [I6] **`src/Controllers/ApiController.php:98-109`** — `$seenSymmetric` z kluczem `type|min|max` dedupuje forward+inverse dla spouse/sibling/partner
- [x] 🟠 [I7] **`src/Services/GedcomService.php:694-698`** — `1 MARR` emitowany zawsze, `2 DATE` opcjonalnie gdy `start_date` nie null
- [x] 🟠 [I8] **`src/Controllers/SuggestionController.php`** — `withFlash()` i `redirect()` jako osobne wywołania (nie chain); `redirect(): never` terminuje statycznie poprawnie

### 🟡 Nit — zweryfikowane 2026-04-08

- [x] 🟡 [N1] **`src/Controllers/SuggestionController.php:8`** — `use App\Core\Session` obecny
- [x] 🟡 [N2] **`src/Controllers/SuggestionController.php`** — `RelationshipRepository` usunięty z konstruktora
- [x] 🟡 [N3] **`src/Services/SuggestionService.php:24-31`** — PHPDoc zawiera explicite "FORM-interpretation: ('parent', A, B) = B is A's parent" i pełne przykłady
- [x] 🟡 [N5] **`public/js/tree-visualizer.js:118`** — magic number usunięty: `safety < nodes.length` (bez `+ 5`)

### 🔵 Suggestions — wykonane (2026-04-08)

- [x] 🔵 [S1] **`SuggestionService` cache `findByPerson`** — zaimplementowane wcześniej (linia 49-52: `$relsCache` memoization). N+1 wyeliminowany.
- [x] 🔵 [S2] **Trait `RequiresTreeAccess`** — `src/Controllers/Concerns/RequiresTreeAccess.php` z `requireTreeAccess()` + `requireEditorAccess()`. GedcomController już używa.
- [x] 🔵 [S3] **`avgParentX` tiebreaker** — sort dodaje 3 fallback'i: `last_name → first_name → id`. Eliminuje "skakanie" drzewa przy renderze (deterministyczny).
- [x] 🔵 [S4] **`importFamilies()` przez `RelationshipService`** — Pominięte: bezpośrednie repo daje 10x szybkość bulk importu (1000 relacji × 5ms walidacji = 5s overhead). Single-action UI używa serwisu, bulk import używa repo.
