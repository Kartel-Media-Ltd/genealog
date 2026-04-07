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
