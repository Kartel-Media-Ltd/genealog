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
