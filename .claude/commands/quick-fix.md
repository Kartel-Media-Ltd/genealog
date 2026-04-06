---
description: Szybka naprawa jednego problemu — diagnoza, fix, weryfikacja. Lzejszy niz /ultra.
argument-hint: Opis problemu (np. "fix cache invalidation in rooms", "napraw blad w RoomForm walidacji")
allowed-tools: Task, Read, Write, Edit, MultiEdit, Glob, Grep, Bash(git:*), Bash(mkdir:*), Bash(ls:*), Bash(npx:*), Bash(pnpm:*), Bash(nx:*), Bash(node:*), AskUserQuestion, ToolSearch
---

# /quick-fix — Szybka naprawa problemu

Diagnozujesz i naprawiasz JEDEN konkretny problem. Bez pelnego researchu — szybko i celnie.
Uzywaj do: bugow, drobnych poprawek, single-concern fixes.
Jesli problem wymaga zmian w >5 plikach lub nowej architektury → uzyj `/ultra` zamiast tego.

## Zadanie

$ARGUMENTS

## Kroki

### 1. Aktywuj Serene (szybko)

```
ToolSearch("select:mcp__plugin_serena_serena__activate_project")
mcp__plugin_serena_serena__activate_project(project: "genealog")
ToolSearch("+serena find_symbol get_symbols search_for_pattern find_referencing_symbols")
```

### 2. Diagnoza — zlokalizuj problem

Na podstawie $ARGUMENTS okresl:

- **Gdzie** jest problem? (ktory modul, plik, symbol)
- **Co** dokladnie jest zle?
- **Dlaczego** to sie dzieje?

Uzyj Sereny:

```
mcp__plugin_serena_serena__search_for_pattern(
  substring_pattern: "[szukaj kluczowego kodu/bledu z $ARGUMENTS]",
  restrict_search_to_code_files: true
)
```

Potem `find_symbol(include_body=true)` na podejrzanych symbolach.

Jesli problem jest na frontendzie — sprawdz tez queries + API route + backend endpoint.
Jesli problem jest na backendzie — sprawdz tez repository + service + controller chain.

### 3. Analiza wplywu

ZANIM naprawisz — sprawdz co moze sie zepsuc:

```
mcp__plugin_serena_serena__find_referencing_symbols(
  name_path: "[symbol ktory bedziesz zmieniac]",
  relative_path: "[plik]"
)
```

Jesli symbol ma >10 references → zapytaj usera czy na pewno chce zmienic (moze byc breaking change).

### 4. Implementacja fixa

Napraw problem uzywajac Edit/Write. Zasady:

- **Minimalna zmiana** — nie refaktoruj sasiadujacego kodu
- **Nie dodawaj feature'ow** — napraw TYLKO to co jest w $ARGUMENTS
- **Zachowaj konwencje** — importy z `@z`, response format `{data, meta}`, etc.
- **Nie dodawaj console.log** — uzywaj ich tylko tymczasowo i usun przed commitem

### 5. Weryfikacja

Po naprawie sprawdz:

- Czy fix nie psuje importow (Grep na nazwy zmienione)
- Czy typowanie jest OK (jesli zmieniles typy)
- Czy cache invalidation jest poprawna (jesli zmieniles dane)

### 6. Podsumowanie

```
## Quick Fix — gotowe!

**Problem:** [1 zdanie co bylo zle]
**Przyczyna:** [1 zdanie dlaczego]
**Fix:** [1 zdanie co zmieniono]

### Zmienione pliki:
- [plik:linia] — [co zmieniono]

### Wplyw:
- [co moze byc dotknietete — references z kroku 3]

### Test:
- [jak user moze zweryfikowac fix]
```

### 7. Git commit (TYLKO jesli user poprosi)

```bash
git add [zmienione pliki]
git commit -m "fix: [krotki opis fixa]

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```
