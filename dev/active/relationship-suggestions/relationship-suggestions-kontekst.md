# Kontekst: Relationship Suggestions

## Problem użytkownika

Gdy dodaje się nową osobę i wskazuje rodzeństwo z kimś już istniejącym w drzewie, to oboje mają tych samych rodziców — ale system nie wie o tym automatycznie. Trzeba ręcznie wchodzić w edycję nowej osoby i dodawać relacje parent, sibling z każdą kolejną osobą osobno. Przy rodzinie z 4 dzieci i 2 rodzicami = 6 oddzielnych formularzy.

## Decyzje projektowe

### Trigger: `?suggest=1` query param (nie flash)

Flash message znikałby przy odświeżeniu. Query param `?suggest=1` działa przewidywalnie, a panel znika gdy użytkownik kliknie "Pomiń" (link bez paramu). Sugestie są obliczane on-demand, nie cachowane.

### Dlaczego tylko po zapisaniu relacji, nie po zapisaniu osoby?

Nowa osoba bez żadnej relacji nie ma kontekstu do sugestii. Sugestie mają sens dopiero gdy istnieje co najmniej jedna relacja dająca "punkt zaczepienia" w grafie.

### Checkboxy + "Dodaj zaznaczone" (a nie jeden klik per relacja)

User wybrał tę opcję. Daje kontrolę — można odhaczyć np. nieznany drugi rodzic. Domyślnie wszystkie zaznaczone = szybka ścieżka.

### SuggestionService jako osobna klasa (nie w PersonController)

Logika inferowania relacji będzie rosła (np. w przyszłości: wspólni dziadkowie, kuzyni). Osobna klasa pozwala testować i rozbudowywać niezależnie.

### Wartości checkboxów jako JSON

`name="suggestions[]"` z wartością `{"type":"child","targetPersonId":"uuid"}`. Backend dekoduje JSON i waliduje — unika osobnych pól per sugestia, prosta iteracja po tablicy.

## Istniejące wzorce w kodzie

- `RelationshipService::create()` już weryfikuje obie osoby w treeId i tworzy inverse — sugestie go reużywają
- `RelationshipRepository::exists()` już istnieje — do filtrowania sugestii
- `RelationshipRepository::findByPerson()` zwraca relacje z joined person data — do budowania grafu
- `PersonController` już ma wzorzec `requireTreeAccess()` i `requirePersonInTree()` — SuggestionController kopiuje

## Co NIE jest w scope

- Sugestie w formularzu tworzenia osoby (live AJAX) — zbyt kompleksowe, przyszłość
- Sugestie na stronie edycji osoby — tylko na show po dodaniu relacji
- Cache sugestii — obliczane na żywo, drzewa genealogiczne są małe (do kilkuset osób)
- Powiadomienia o sugestiach — osobny feature z tabeli notifications

---

## Code review (2026-04-07)

Przeprowadzony kompleksowy review zadania + powiązanych zmian z tej samej domeny (semantyka relacji parent/child). Pełny raport: [`review-2026-04-07.md`](./review-2026-04-07.md).

### Werdykt: ❌ FAIL — 4 blocking, 8 important, 6 nit, 4 suggestions

### Kluczowe wnioski

1. **Konwencja semantyki relacji jest spójna** — cały kod (po refactorze ApiController/GedcomService/JS) używa **FORM-interpretation**: `('parent', A, B)` = "A ma B jako rodzica", więc B jest rodzicem. SuggestionService był pisany od początku zgodnie z tą konwencją.

2. **Bug dziedziczony [B1]:** `GedcomService::export()` używa snake_case (`$rel->person_a_id`) podczas gdy model `Relationship` ma camelCase (`personAId`). Z `readonly` properties PHP zwraca `null` cicho — eksport produkuje GEDCOM bez relacji parent/child. Bug istniał już PRZED zmianami z tej fazy, ale ujawniony przez review.

3. **Bug semantyczny w widoku [B4]:** `show.php:186-193` — `typeLabel` mapuje `'parent' => 'dziecko'` zamiast `'parent' => 'rodzic'`. Użytkownik widzi sugestie z odwróconymi etykietami.

4. **Bug logiki sugestii [B3]:** SuggestionService case `'child'` sugeruje rodzeństwo dziecka B jako WŁASNE dziecko A, bez weryfikacji że ten sibling ma A jako rodzica. Może sugerować dzieci z innego związku B.

5. **Brak deduplikacji forward+inverse [B2, I6]:** `RelationshipService::create()` zawsze zapisuje obie strony pary (forward + inverse). `ApiController` ma dedup dla parent/child (`$parentSet`), ale `GedcomService::export()` i `ApiController` dla spouse/sibling NIE mają. Rezultat: duplikaty CHIL w eksporcie GEDCOM, podwójne linie w D3.

6. **Walidacja dat [I3]:** Porównanie stringów `Y-m-d` działa przypadkowo — bezpieczniejsze byłoby `substr(0,4)` jako int.

### Pozytywne wnioski

- Architektura SuggestionService jest czysta, dobrze rozdzielone case'y per typ relacji
- Kolejność walidacji w SuggestionController jest wzorowa: CSRF → auth → IDOR person → IDOR target → business
- `tree-visualizer.js` z Kahn's algo + path-compressed union-find + iteracyjnym handlingiem cykli — solidnie
- CSRF tokeny i `htmlspecialchars` są w widoku

### Następny krok

Poprawki blocking (B1-B4) MUSZĄ być wykonane przed completion. Important (I1-I8) zalecane. Nit + Suggestions opcjonalnie.
