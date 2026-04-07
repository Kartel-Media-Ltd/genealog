# Plan: Relationship Suggestions — Genealog

## Cel

Po dodaniu relacji do nowej osoby, system analizuje graf relacji i sugeruje brakujące, oczywiste powiązania. Użytkownik zaznacza checkboxami które przyjąć i klika "Dodaj zaznaczone" — jedno kliknięcie zamiast wielokrotnego wchodzenia w formularze.

## Typ: STANDARD

---

## Architektura

### Trigger

`RelationshipController::processCreate()` po sukcesie redirectuje na:
```
/trees/{id}/persons/{pid}?suggest=1
```
zamiast dotychczasowego `/trees/{id}/persons/{pid}`.

`PersonController::show()` wykrywa `?suggest=1`, wywołuje `SuggestionService::compute()` i przekazuje wyniki do widoku.

### Algorytm sugestii (`SuggestionService::compute`)

```
Dla każdej relacji osoby A:

  Jeśli A ma rodzeństwo B:
    → Rodzice B (B ma typ 'child' do C/D) → sugeruj 'child' A→C i A→D
    → Inne rodzeństwo B (B ma typ 'sibling' do E) → sugeruj 'sibling' A→E

  Jeśli A ma małżonka/partnera B:
    → Dzieci B (B ma typ 'parent' do F) → sugeruj 'parent' A→F

  Jeśli A ma rodzica B:
    → Inny rodzic B's dzieci (B ma małżonka C) → sugeruj 'spouse' A→C (drugi rodzic)
    → Inne dzieci B (B ma typ 'parent' do G) → sugeruj 'sibling' A→G

Filtruj: pomijaj sugestie gdzie relacja już istnieje (relRepo->exists)
Filtruj: pomijaj A→A
Deduplikuj po (type, targetPersonId)
Dołącz Person object do każdej sugestii (do wyświetlenia imienia/zdjęcia)
```

### Bulk accept endpoint

```
POST /trees/{id}/persons/{pid}/suggestions
Body: suggestions[] = JSON array [{type, targetPersonId}, ...]
```

Iteruje przez tablicę, wywołuje `RelationshipService::create()` dla każdej. Redirect z powrotem na show (BEZ `?suggest=1`, żeby nie zapętlić).

---

## Nowe pliki

### `src/Services/SuggestionService.php`

```php
class SuggestionService {
    public function __construct(
        private RelationshipRepository $relRepo,
        private PersonRepository       $personRepo,
    ) {}

    /** @return array{type:string, targetPerson:Person, reason:string}[] */
    public function compute(string $personId, string $treeId): array;
}
```

### `src/Controllers/SuggestionController.php`

```php
class SuggestionController {
    /** POST /trees/{id}/persons/{pid}/suggestions */
    public function apply(): never;
}
```

---

## Modyfikacje istniejących plików

### `src/Controllers/RelationshipController.php`

`processCreate()` — zmiana redirect:
```php
// przed:
->redirect('/trees/' . $treeId . '/persons/' . $personId);
// po:
->redirect('/trees/' . $treeId . '/persons/' . $personId . '?suggest=1');
```

### `src/Controllers/PersonController.php`

`show()` — dodaj sugestie gdy `?suggest=1`:
```php
$suggestions = [];
if ($this->request->getParam('suggest') === '1' && $canEdit) {
    $suggestions = $this->suggestionService->compute($personId, $treeId);
}
```

### `public/index.php`

Nowa trasa:
```
POST /trees/{id}/persons/{pid}/suggestions → SuggestionController::apply
```

Wire SuggestionService i SuggestionController do DI.

### `src/views/pages/trees/persons/show.php`

Panel sugestii (powyżej panelu relacji, widoczny gdy `!empty($suggestions)`):
```html
<div class="rounded-lg border border-primary/30 bg-primary/5 shadow-sm">
  <div class="border-b px-6 py-4">💡 Sugerowane relacje do dodania</div>
  <form method="POST" action="/trees/{id}/persons/{pid}/suggestions">
    <div class="px-6 py-4 space-y-3">
      foreach ($suggestions as $s):
        <label>
          <input type="checkbox" name="suggestions[]" value="{json}" checked>
          [Imię + zdjęcie] — {reason}
        </label>
      endforeach
    </div>
    <div class="px-6 pb-4">
      <button type="submit">Dodaj zaznaczone</button>
      <a href="?">Pomiń</a>
    </div>
  </form>
</div>
```

---

## Nowa trasa (routing kolejność)

```
POST /trees/{id}/persons/{pid}/suggestions  ← PRZED /trees/{id}/persons/{pid}
```

---

## Edge cases

- Brak sugestii → panel nie renderuje się, show ładuje się normalnie
- Sugestia wskazuje na osobę która już ma tę relację → `relRepo->exists()` filtruje
- Użytkownik klika "Pomiń" → link do `/trees/{id}/persons/{pid}` (bez `?suggest=1`)
- Bulk accept, niektóre relacje już istnieją → `RelationshipService::create()` powinien obsłużyć duplikat gracefully (try/catch, skip)
- Viewer (nie editor) → `$suggestions = []`, panel nie renderuje się

---

## Bezpieczeństwo

- `suggestions[]` w POST body to JSON per sugestia — backend MUSI walidować że `targetPersonId` należy do tego samego `treeId` (IDOR prevention)
- Weryfikacja dostępu: `requireEditorAccess($treeId, $userId)` w `SuggestionController::apply()`
- CSRF token w formularzu sugestii
- `RelationshipService::create()` już weryfikuje że obie osoby należą do treeId
