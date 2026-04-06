---
description: Generuj tytul + body PR z git history. Opcjonalnie tworzy PR przez gh CLI.
argument-hint: Opcjonalnie base branch (domyslnie "main")
allowed-tools: Bash(git:*), Bash(gh:*), Read, Grep, AskUserQuestion
---

# /pr-summary — Generuj opis Pull Request

Analizujesz git history brancha i generujesz profesjonalny opis PR.

## Zadanie

Base branch: $ARGUMENTS (domyslnie: main)

## Kroki

### 1. Zbierz dane z git (rownolegle)

```bash
git log $BASE..HEAD --oneline --no-merges
```

```bash
git diff $BASE...HEAD --stat
```

```bash
git log $BASE..HEAD --format="%s" --no-merges
```

```bash
git branch --show-current
```

Gdzie $BASE = argument usera lub "main".

### 2. Przeanalizuj zmiany

Na podstawie commit messages i diff stat:
- Okresl typ zmian: feature / bugfix / refactor / chore / docs
- Zidentyfikuj glowne obszary (backend, frontend, prisma, validation)
- Policz: ile plikow, ile insertions/deletions
- Wyodrebnij kluczowe commity (nie szum typu "wip", "fix typo")

### 3. Wygeneruj PR description

**Tytul:** max 70 znakow, imperative mood, po angielsku.
Format: `[type]: [krotki opis]`
Przyklady:
- `feat: add rooms management system with CRUD and equipment assignment`
- `fix: resolve cache invalidation for equipment updates`
- `refactor: migrate equipment images to media library system`

**Body:**

```markdown
## Summary
- [2-4 bullet points opisujace CO i DLACZEGO]

## Changes
### Backend
- [lista zmian backend jesli sa]

### Frontend
- [lista zmian frontend jesli sa]

### Database
- [nowe modele/migracje jesli sa]

### Other
- [validation, permissions, docs, etc.]

## Test plan
- [ ] [konkretne kroki testowania — np. "Create a room, verify it appears in list"]
- [ ] [kazdy krok testowalny recznie]

## Notes
- [dodatkowe uwagi: breaking changes, migration steps, env vars, etc.]
- [POMIN sekcje Notes jesli nie ma nic do dodania]

---
Generated with [Claude Code](https://claude.com/claude-code)
```

### 4. Wyswietl i zapytaj

Wyswietl wygenerowany tytul + body, potem zapytaj:

AskUserQuestion:
- "Co dalej?"
- Opcje:
  - "Utworz PR (gh pr create)" — uruchom `gh pr create --title "..." --body "..."`
  - "Kopiuj do clipboard" — wyswietl w formacie do skopiowania
  - "Popraw" — zapytaj co zmienic
  - "Anuluj"

### 5. Jesli user wybral "Utworz PR"

```bash
gh pr create --title "[tytul]" --body "$(cat <<'EOF'
[body z kroku 3]
EOF
)"
```

Wyswietl URL nowego PR.
