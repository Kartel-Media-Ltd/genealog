---
description: Zapisz snapshot postepow sesji — WIP commit + pamiec Sereny + resume instructions
argument-hint: Krotki opis co robiles (np. "implementacja rooms backend CRUD")
allowed-tools: Bash(git:*), Read, Write, Edit, AskUserQuestion, ToolSearch
---

# /checkpoint — Snapshot postepow sesji

Zapisujesz snapshot biezacej pracy. Uzywany przed context compaction, przerwa, zmiana tematu.
NIGDY nie pushuj do remote — to jest lokalny checkpoint.

## Zadanie

$ARGUMENTS

## Kroki

### 1. Git status + auto-commit WIP

```bash
git status --short
```

Jesli sa zmiany (staged lub unstaged):

```bash
git add -A
git commit -m "wip: checkpoint — $ARGUMENTS

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

Jesli NIE ma zmian → pomin commit, przejdz do kroku 2.

### 2. Zapisz stan sesji do pamieci Sereny

```
ToolSearch("select:mcp__plugin_serena_serena__activate_project")
mcp__plugin_serena_serena__activate_project(project: "genealog")
ToolSearch("+serena write_memory")
```

Zapisz pamiec:

```
mcp__plugin_serena_serena__write_memory(
  memory_file_name: "session_checkpoint",
  content: |
    # Session Checkpoint (YYYY-MM-DD HH:MM)
    ## Branch: [aktualna galaz]
    ## Co zostalo zrobione:
    - [lista wykonanych zmian — max 5 punktow]
    ## Co zostalo do zrobienia:
    - [lista TODO — max 5 punktow]
    ## Kluczowe pliki:
    - [lista plikow nad ktorymi pracowano]
    ## Kontekst:
    - [wazneinformacje ktore nowa sesja musi wiedziec]
    ## Resume command:
    Wpisz: "Kontynuuj prace nad [opis]. Przeczytaj pamiec session_checkpoint z Sereny."
)
```

### 3. Aktualizuj MEMORY.md (opcjonalnie)

Jesli w tej sesji odkryto cos wartego zapamietania na stale (nowy pattern, workaround, konwencja):

- Dodaj do MEMORY.md
- NIE dodawaj sesyjnych szczegolow — tylko trwale wnioski

### 4. Output

```
## Checkpoint zapisany!

**Branch:** [nazwa]
**Commit:** [hash skrocony] — wip: checkpoint — [opis]
**Pamiec Sereny:** session_checkpoint (zaktualizowana)

### Aby wznowic w nowej sesji:
> Kontynuuj prace nad [opis]. Przeczytaj pamiec session_checkpoint z Sereny.

### Pliki w toku:
- [lista zmienionych plikow]
```
