---
description: Implementacja feature z gotowego planu. Czyta plan z dev/active/, wykonuje faza po fazie. Autonomiczny tryb pracy.
argument-hint: "[sciezka-do-planu] (np. dev/active/rooms, dev/active/calendar-events)"
allowed-tools: Task, Read, Write, Edit, MultiEdit, Glob, Grep, Bash, AskUserQuestion, ToolSearch, Skill(code-review:*), Skill(ux-ui-guidelines:*), Skill(security-audit:*), Skill(checkpoint:*), Skill(ralph-loop:*)
---

# /ultra-workaholic — Czysta Implementacja

Jestes elitarnym developerem. Implementujesz feature z gotowego planu w `dev/active/`.
Stack i standardy projektu sa w CLAUDE.md — stosuj je AUTOMATYCZNIE.
Dzialasz w 5 fazach: Bootstrap → Walidacja → Implementacja → Code Review → Finalizacja.

**WAZNE:** Ta komenda IMPLEMENTUJE — wymaga gotowego planu z `/ultra-think`.

## Sciezka do planu

$ARGUMENTS

---

## AUTONOMICZNY TRYB PRACY (auto-accept)

**ZASADA:** Wykonuj komendy i operacje SAMODZIELNIE, o ile nie wychodza poza projekt.

### Co robic BEZ pytania usera:

- Czytanie dowolnych plikow w projekcie
- Edycja plikow w projekcie
- Git operations (add, commit, branch, checkout, diff, log, status)
- Uruchamianie lint, build, type-check
- Tworzenie folderow/plikow w projekcie
- Uruchamianie Serena, Context7, Postgres MCP i innych aktywnych MCP
- WebSearch/WebFetch do researchu
- Uruchamianie subagentow (Task)
- npx, pnpm, nx komendy w ramach projektu

### Co WYMAGA pytania usera:

- **Database changes:** `pnpm db:push`, `pnpm db:migrate`, `prisma db push` — NIGDY samodzielnie
- **Git push** — zawsze pytaj
- **Usuwanie branchy/resetowanie** — zawsze pytaj
- **Operacje poza projektem** — zawsze pytaj
- **Seed scripts** — informuj: `USER_ACTION: npx tsx libs/prisma/db/seed-*.ts`

---

## ZASADY MINIMALIZACJI KOSZTOW

1. **OBOWIAZKOWE Serena MCP** — `get_symbols_overview`, `find_symbol`, `search_for_pattern` zamiast Read/cat.
2. **Context7 MCP** dla docs frameworkow (ZANIM siegniesz po WebSearch).
3. **CLAUDE.md jest juz zaladowane** — NIE powtarzaj standardow w promptach subagentow.
4. **Nigdy nie czytaj calych plikow bez potrzeby** — `find_symbol(include_body=false)` najpierw.

---

## Faza 0: Bootstrap (MCP + odczyt planu)

### Krok 1: Aktywuj Serene + Context7

**OBOWIAZKOWE:**

```
ToolSearch -> "select:mcp__plugin_serena_serena__activate_project"
mcp__plugin_serena_serena__activate_project(project: "genealog")
ToolSearch -> "+serena find_symbol get_symbols search_for_pattern find_referencing_symbols"
ToolSearch -> "+context7 resolve-library query-docs"
```

### Krok 2: Przeczytaj WSZYSTKIE pliki planu

Przeczytaj KAZDY plik z `$ARGUMENTS/`:

- `[feature]-plan.md` — architektura (modele, endpointy, permissions)
- `[feature]-kontekst.md` — research i decyzje
- `[feature]-zadania.md` — checklist implementacji (NAJWAZNIEJSZY!)
- `[feature]-audit.md` — raport audytu (co unikac)
- `[feature]-uxui.md` — UX/UI design (wireframes, komponenty)

### Krok 3: Sprawdz git

```bash
git branch --show-current
git status --short
git log --oneline -5
```

---

## Faza 1: Walidacja planu

Sprawdz kompletnosc:

| Plik                    | Wymagany? | Brak → akcja                              |
| ----------------------- | --------- | ----------------------------------------- |
| `[feature]-zadania.md`  | TAK       | STOP → "Brak planu. Uruchom /ultra-think" |
| `[feature]-plan.md`     | TAK       | STOP → "Brak architektury."               |
| `[feature]-kontekst.md` | Zalecany  | Kontynuuj z ostrzezeniem                  |
| `[feature]-audit.md`    | Zalecany  | Kontynuuj z ostrzezeniem                  |
| `[feature]-uxui.md`     | Zalecany  | Kontynuuj z ostrzezeniem                  |

Sprawdz czy `[feature]-zadania.md` zawiera:

- [ ] Fazy z checkboxami `- [ ]`
- [ ] Atomowe zadania (2-5 min kazde)
- [ ] USER_ACTION markers dla db:migrate, seed

Jesli brak planu → AskUserQuestion: "Brak planu implementacji. Uruchom najpierw `/ultra-think [feature]`."

---

## Faza 2: Implementacja (petla po fazach)

**CEL:** Wykonac KAZDA faze z `[feature]-zadania.md` sekwencyjnie.

### Petla implementacji:

````
Dla kazdej FAZY z zadania.md:
  1. Wyswietl: "## Faza N: [nazwa fazy]"
  2. Odczytaj zadania fazy (checkboxy)

  Dla kazdego ZADANIA w fazie:
    a. Jesli USER_ACTION → STOP + wyswietl komunikat:
       "USER_ACTION: [komenda]. Wykonaj ja i powiedz 'kontynuuj'."
    b. Jesli normalne zadanie → wykonaj:
       - Uzyj Serena do nawigacji po kodzie (OBOWIAZKOWE)
       - Write/Edit do tworzenia/modyfikacji plikow
       - Bash do git/lint/build
    c. Po wykonaniu → oznacz ✅ w zadania.md:
       Edit: `- [ ] zadanie` → `- [x] zadanie`

  3. Po zakonczeniu WSZYSTKICH zadan fazy:
     - Uruchom ESLint fix na zmienionych plikach:
       ```bash
       CHANGED=$(git diff --name-only --diff-filter=ACMR HEAD | grep -E '\.(ts|tsx)$' | head -30)
       [ -n "$CHANGED" ] && npx eslint --fix --no-error-on-unmatched-pattern $CHANGED 2>/dev/null
       ```
     - Sprawdz czy sa NIENAPRAWIALNE bledy (unused vars, missing useState):
       ```bash
       [ -n "$CHANGED" ] && npx eslint --no-error-on-unmatched-pattern $CHANGED 2>&1 | grep -E "no-unused-vars|is defined but never used|is assigned .* but never used" | head -10
       ```
       Jesli sa → napraw recznie (usun zmienna lub dodaj uzycie) i powtorz
     - Git commit: `feat([feature]): faza N — [opis]`
     - Wyswietl podsumowanie fazy

  4. Przejdz do nastepnej fazy
````

### Zasady implementacji:

**Backend:**

- Prisma schema → `libs/prisma/db/schema.prisma`
- Migration SQL → `libs/prisma/db/prisma-migrations/YYYYMMDDHHMMSS_[nazwa]/migration.sql`
- NestJS modules → `apps/backend/src/app/[feature]/`
- Permissions seed → `libs/prisma/db/seed-[feature]-permissions.ts`
- Dekoratory: `@Cache`, `@InvalidateCache`, `@RequirePermissions`
- Response format: `{ data, meta }`
- Import z `@prisma`, NIGDY z `@prisma/client`

**Validation:**

- Zod schemas → `libs/validation/src/[feature]/`
- Export z `libs/validation/src/index.ts`
- Import jako `@z`
- Zod v4 syntax: `z.email()`, `z.uuid()`, `z.iso.datetime()`

**Frontend:**

- Pages → `apps/frontend/src/app/(protected)/[feature]/`
- API routes → `apps/frontend/src/app/api/[feature]/`
- Queries → `apps/frontend/src/queries/[feature].queries.ts`
- Components → `apps/frontend/src/components/[feature]/`
- `usePageBreadcrumbs()` na KAZDEJ stronie
- DataTable + ContextMenu (right-click) — OBOWIAZKOWE
- React Query response unwrap: `response.data`

### Serena do nawigacji:

```
// Zamiast Read na calym pliku:
mcp__plugin_serena_serena__get_symbols_overview(relative_path: "...", depth: 1)
mcp__plugin_serena_serena__find_symbol(name_path_pattern: "ClassName/methodName", include_body: true)

// Zamiast Grep na calym projekcie:
mcp__plugin_serena_serena__search_for_pattern(substring_pattern: "...", relative_path: "...")

// Edycja symboliczna (zamiast Edit z duzymi blokami):
mcp__plugin_serena_serena__replace_symbol_body(...)
mcp__plugin_serena_serena__insert_after_symbol(...)
```

---

## Faza 3: Weryfikacja koncowa

Po zakonczeniu WSZYSTKICH faz implementacji:

### Krok 1: ESLint Fix + Lint + Build

**A) ESLint auto-fix na WSZYSTKICH zmienionych plikach:**

```bash
CHANGED=$(git diff --name-only --diff-filter=ACMR HEAD | grep -E '\.(ts|tsx)$')
[ -n "$CHANGED" ] && npx eslint --fix --no-error-on-unmatched-pattern $CHANGED 2>/dev/null
```

**B) Sprawdz NIENAPRAWIALNE bledy (unused vars, unused state):**

```bash
[ -n "$CHANGED" ] && npx eslint --no-error-on-unmatched-pattern $CHANGED 2>&1 | grep -E "no-unused-vars|is defined but never used|is assigned .* but never used" | head -20
```

Jesli sa → napraw RECZNIE:

- **Unused import** → usun linie importu
- **Unused variable/function** → usun deklaracje lub dodaj uzycie
- **useState deklaracja bez uzycia setter** → usun `const [_, setX] = useState()` lub dodaj uzycie `setX`
- **useState setter bez uzycia zmiennej** → zamien na `const [, setX] = useState()` lub usun

**C) Full lint + build:**

```bash
nx lint backend
nx lint frontend
nx build backend
nx build frontend --configuration=production
```

Jesli bledy → napraw i powtorz.

### Krok 2: Sprawdz otwarte checkboxy

```
Grep(pattern: "- \\[ \\]", path: "$ARGUMENTS/[feature]-zadania.md")
```

Jesli sa niezrealizowane (nie-USER_ACTION) → realizuj.

---

## Faza 4: Code Review

**OBOWIAZKOWE:** Po zakonczeniu WSZYSTKICH faz uruchom review.

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 10
prompt: |
  Przeprowadz code review zmian w projekcie genealog.

  ## OBOWIAZKOWE: Przeczytaj skill code-review
  Przeczytaj .claude/skills/code-review/SKILL.md — uzyj checklistow i formatu raportu.
  Przeczytaj .claude/skills/code-review/resources/tech-stack-checklist.md
  Przeczytaj .claude/skills/code-review/resources/common-issues.md

  ## Zmienione pliki:
  [wynik git diff --name-only z poczatku feature brancha]

  ## Plan feature'a:
  [krotkie streszczenie z planu]

  ## Sprawdz:
  1. Zgodnosc z planem
  2. Bezpieczenstwo (OWASP basics, tenant isolation)
  3. Wydajnosc (N+1, cache, bundle size)
  4. Jakosc kodu (DRY, nazewnictwo, TypeScript strict)
  5. Standardy projektu (CLAUDE.md compliance)

  Uzyj formatu z SKILL.md. Jesli KRYTYCZNE → szczegolowy opis naprawy.
```

Jesli krytyczne problemy → napraw przed finalizacja.

---

## Faza 5: Finalizacja

### Krok 1: Final ESLint + git commit (jesli byly poprawki z review)

**Przed commitem — OBOWIAZKOWY ESLint pass:**

```bash
CHANGED=$(git diff --name-only --diff-filter=ACMR HEAD | grep -E '\.(ts|tsx)$')
[ -n "$CHANGED" ] && npx eslint --fix --no-error-on-unmatched-pattern $CHANGED 2>/dev/null
# Sprawdz czy zostaly bledy
[ -n "$CHANGED" ] && npx eslint --no-error-on-unmatched-pattern $CHANGED 2>&1 | grep -c "error" | head -1
```

Jesli sa bledy → napraw. Dopiero potem commit:

```bash
git add -A
git commit -m "fix([feature]): poprawki z code review"
```

### Krok 2: Aktualizuj Serena memory

```
mcp__plugin_serena_serena__write_memory(
  memory_file_name: "[feature]_architecture",
  content: "[zaktualizowany content]\n## Status: IMPLEMENTED"
)
```

### Krok 3: Output koncowy

```
## /ultra-workaholic — Implementacja zakonczona!

**Feature:** [nazwa]
**Branch:** [branch]
**Faz wykonanych:** X/Y
**Code Review:** [wynik — ile kryt./pow./drob.]

### Zmienione pliki
[lista plikow pogrupowana: backend, validation, frontend]

### USER_ACTION (jesli pending)
- [ ] `pnpm db:migrate:deploy && pnpm db:generate`
- [ ] `npx tsx libs/prisma/db/seed-[feature]-permissions.ts`

### Nastepne kroki
- `/ultra-audit [feature]` — pelny audyt bezpieczenstwa
- `git push` — push na remote
- Dokumentacja: dodaj w `docs/[feature]/`
```

---

## Troubleshooting

### Brak planu w dev/active/

Uruchom `/ultra-think [feature]` najpierw.

### Lint/build fails

Napraw bledy, powtorz lint. Jesli bledy w plikach spoza feature'a → SKIP (poinformuj usera).

### USER_ACTION — user nie odpowiada

Przejdz do nastepnych zadan ktore NIE wymagaja DB. Wroc do DB-dependent po odpowiedzi usera.

### Agent nie uzywa Sereny

Sprawdz prompt — MUSI zawierac instrukcje aktywacji Sereny.

### Konflikty git

Zapytaj usera: resolve conflicts czy abort.
