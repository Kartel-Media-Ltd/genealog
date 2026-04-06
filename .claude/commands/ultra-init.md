---
description: Inicjalizacja ekosystemu Ultra — instalacja MCP, analiza projektu, tworzenie MEMORY.md
argument-hint: (opcjonalnie) krotki opis projektu
allowed-tools: Bash(git:*), Bash(ls:*), Bash(cat:*), Bash(node:*), Bash(npx:*), Bash(pnpm:*), Read, Write, Edit, Glob, Grep, AskUserQuestion, ToolSearch, WebSearch
---

# /ultra-init — Bootstrapper ekosystemu Ultra

Inicjalizujesz ekosystem Ultra w nowym projekcie. Ta komenda jest MUSTHAVE — uruchom ja ZANIM uzywasz /ultra, /ultra-think, /ultra-workaholic.

**Model dystrybucji:** Caly `.claude/` folder jest dostarczany jako ZIP. Komendy, skille, agenty, hooki sa juz na miejscu. /ultra-init konfiguruje MCP, analizuje projekt i tworzy MEMORY.md.

**Co /ultra-init ROBI:**

- Sprawdza czy .claude/ folder jest rozpakowany poprawnie
- Sprawdza/informuje o instalacji MCP serwerow (Serena, Context7)
- Pobierznie analizuje projekt (README, package.json, top-level dirs — NIE deep scan!)
- Jesli projekt jest malo opisany → zadaje pytania
- Tworzy MEMORY.md z profilem projektu
- Tworzy scaffold CLAUDE.md (jesli nie istnieje)
- Wyswietla summary z manualnymi krokami dla usera

**Co /ultra-init NIE ROBI:**

- NIE generuje komend ultra family (juz w `.claude/commands/`)
- NIE generuje skilli, agentow, hookow (juz w ZIP)
- NIE skanuje calego codebase (tylko top-level)
- NIE instaluje MCP automatycznie (wymaga restartu CLI)

## Opis projektu od usera

$ARGUMENTS

---

## Krok 1: PREFLIGHT — sprawdz co jest

Sprawdz nastepujace elementy (ROWNOLEGLE gdzie mozliwe):

```
A) Glob(".claude/commands/ultra.md") — czy ultra family jest rozpakowana?
   Jesli NIE → STOP: "Najpierw rozpakuj ZIP z .claude/ do root projektu."

B) Glob(".claude/requirment/ULTRA_REQUIREMENTS.md") — czy requirements istnieja?

C) Glob("CLAUDE.md") — czy instrukcje projektu istnieja?

D) Glob("README.md") — czy jest dokumentacja?

E) Glob("package.json") lub Glob("Cargo.toml") lub Glob("go.mod") lub Glob("requirements.txt")
   — jaki stack?

F) Bash("git status --short") — czy git jest zainicjalizowany?

G) Sprawdz Serena MCP — sprobuj:
   ToolSearch("select:mcp__plugin_serena_serena__list_memories")
   Jesli ToolSearch zwroci narzedzie → Serena AKTYWNA
   Jesli blad → Serena NIEAKTYWNA

H) Sprawdz Context7 MCP — sprobuj:
   ToolSearch("select:mcp__plugin_context7_context7__resolve-library-id")
   Jesli ToolSearch zwroci narzedzie → Context7 AKTYWNA
   Jesli blad → Context7 NIEAKTYWNA
```

Zapisz wyniki jako `PREFLIGHT_STATE`:

- `ULTRA_FILES_OK`: true/false
- `REQUIREMENTS_FILE`: true/false
- `CLAUDE_MD_EXISTS`: true/false
- `README_EXISTS`: true/false
- `STACK_DETECTED`: nazwa (typescript/python/go/rust/unknown)
- `GIT_INITIALIZED`: true/false
- `SERENA_ACTIVE`: true/false
- `CONTEXT7_ACTIVE`: true/false

Jesli `ULTRA_FILES_OK = false` → STOP z komunikatem i instrukcja rozpakowania ZIP.

---

## Krok 2: DISCOVER — co to za projekt?

**CEL:** Zebrac MINIMUM kontekstu o projekcie — szybko, bez deep scan.

### Priorytet odczytu (w kolejnosci):

1. **$ARGUMENTS** — jesli user podal opis → uzyj jako bazowy kontekst
2. **CLAUDE.md** — jesli istnieje → Read(pierwsze 150 linii) — wyciagnij nazwa, stack, opis
3. **README.md** — jesli istnieje → Read(pierwsze 100 linii) — wyciagnij opis projektu
4. **package.json** — jesli istnieje → Read — wyciagnij name, description, dependencies (detect stack):
   - `next` → Next.js
   - `@nestjs/core` → NestJS
   - `react` → React
   - `prisma` → Prisma ORM
   - `@angular/core` → Angular
   - `vue` → Vue.js
   - `express` → Express
   - `fastify` → Fastify
5. **Cargo.toml / go.mod / requirements.txt / pyproject.toml** — detect language
6. **ls top-level dirs** (Bash: `ls -d */` w root) — detect monorepo/app/lib structure

### Jesli NADAL brak wystarczajacego kontekstu

(Brak README + brak package.json + brak CLAUDE.md + brak $ARGUMENTS):

Uzyj **AskUserQuestion** (1 call, 3-4 pytania):

```
question: "Czym jest ten projekt?"
options:
  - label: "Web app (frontend + backend)"
  - label: "Backend API only"
  - label: "Full-stack monorepo"
  - label: "CLI tool / library"

question: "Jaki stack technologiczny?"
options:
  - label: "TypeScript + React/Next.js + NestJS"
  - label: "TypeScript + Next.js (full-stack)"
  - label: "Python + FastAPI/Django"
  - label: "Inny (opisz)"

question: "Jaki package manager?"
options:
  - label: "pnpm"
  - label: "npm"
  - label: "yarn"
  - label: "bun"

question: "Czy uzywasz bazy danych? Jakiej?"
options:
  - label: "PostgreSQL (Prisma/TypeORM/Drizzle)"
  - label: "MySQL / MongoDB / SQLite"
  - label: "Nie uzywam"
```

**WAZNE:** Pytaj TYLKO jesli nie mozesz wydedukwac z istniejacych plikow. Nie pytaj o oczywiste rzeczy.

Zapisz jako `PROJECT_CONTEXT`:

- `name`: nazwa projektu
- `stack`: wykryty stack
- `description`: 1-2 zdania opisu
- `packageManager`: pnpm/npm/yarn/bun
- `db`: typ bazy danych (jesli wykryto)
- `structure`: monorepo/single-app/library
- `linter`: eslint/biome/none (z devDependencies)
- `testRunner`: jest/vitest/none
- `orm`: prisma/typeorm/drizzle/none
- `monorepoTool`: nx/turbo/lerna/none

---

## Krok 3: MCP CHECK — sprawdz/informuj o serwerach

Przeczytaj `.claude/requirment/ULTRA_REQUIREMENTS.md` (jesli istnieje) → lista wymaganych MCP.

Na podstawie PREFLIGHT_STATE:

```
PENDING_INSTALLS = []

Jesli SERENA_ACTIVE = false:
  PENDING_INSTALLS.push("serena")

Jesli CONTEXT7_ACTIVE = false:
  PENDING_INSTALLS.push("context7")
```

**UWAGA:** NIE instaluj MCP automatycznie! MCP instalacja wymaga restartu Claude CLI.
Tylko INFORMUJ usera o brakujacych serwerach — instrukcje w podsumowaniu (Krok 6).

---

## Krok 4: INIT MEMORY — MEMORY.md

**CEL:** Utworzyc MEMORY.md w katalogu auto-memory Claude.

Pobierzna analiza (NIE deep scan!):

- ls na top-level directories (Bash: `ls -d */`)
- Stack z package.json dependencies (juz odczytany w Kroku 2)
- NIE skanuj plikow zrodlowych
- NIE uruchamiaj Serena deep scan (moze nie byc aktywna)

**Znajdz katalog auto-memory:**
Katalog auto-memory jest w `~/.claude/projects/<project-hash>/memory/`. Sciezke znajdziesz po:

```
Glob("~/.claude/projects/*/memory/MEMORY.md")
```

Jesli nie istnieje — uzyj wzorca z biezacej sciezki projektu.

Utwórz/zaktualizuj MEMORY.md:

```markdown
# Memory — [Nazwa projektu]

## Projekt

- **Nazwa:** [z README/package.json/user input]
- **Stack:** [detected]
- **Opis:** [1-2 zdania]
- **Package manager:** [pnpm/npm/yarn]
- **DB:** [jesli wykryto]

## Struktura (pobiezna)

[wynik ls na glownych katalogach — kompaktowo]

## Konwencje (do uzupelnienia)

- Linter: [eslint/biome/none]
- Test runner: [jest/vitest/none]
- ORM: [prisma/typeorm/drizzle/none]
- Monorepo: [nx/turbo/lerna/none]

## Ultra Init

- Data: [YYYY-MM-DD]
- Status: INITIALIZED
```

**Idempotentnosc:** Jesli MEMORY.md juz istnieje → NIE nadpisuj. Dodaj tylko sekcje "Ultra Init" jesli jej brak.

---

## Krok 5: CLAUDE.md SCAFFOLD (jesli nie istnieje)

Jesli CLAUDE.md juz istnieje → SKIP (wyswietl "CLAUDE.md istnieje — OK").

Jesli NIE istnieje → wygeneruj template:

````markdown
# CLAUDE.md

<!-- Wygenerowane przez /ultra-init — uzupelnij o szczegoly projektu -->

## Project Overview

[nazwa] — [opis z PROJECT_CONTEXT]

## Tech Stack

- **Runtime:** [Node.js/Python/Go]
- **Framework:** [NestJS/Express/Next.js/FastAPI]
- **Frontend:** [React/Vue/Angular/none]
- **Database:** [PostgreSQL/MySQL/MongoDB/none]
- **Package Manager:** [pnpm/npm/yarn]

## Development Commands

```bash
# Uzupelnij komendy specyficzne dla projektu
[package-manager] dev       # Start development
[package-manager] build     # Build
[package-manager] test      # Run tests
[package-manager] lint      # Lint
`` `

## Project Structure
<!-- Uzupelnij na podstawie ls top-level dirs -->
[wynik ls]

## Path Aliases
<!-- Uzupelnij aliasy z tsconfig.json / pyproject.toml -->

## Important Notes
<!-- Dodaj zasady specyficzne dla projektu:
     - Database workflow
     - Coding conventions
     - Security requirements
-->
```
````

**WAZNE:** Template jest MINIMALNY — user musi go uzupelnic. To jest scaffold, nie gotowy dokument.

---

## Krok 6: SUMMARY — podsumowanie

Wyswietl nastepujacy output:

```
## /ultra-init — Inicjalizacja zakonczona!

**Projekt:** [nazwa]
**Stack:** [stack]
**Data:** [YYYY-MM-DD]

### Wykonane automatycznie
- Analiza projektu (pobiezna)
- MEMORY.md — profil projektu [utworzony / juz istnial — zaktualizowany]
- CLAUDE.md — [utworzony scaffold / juz istnial]
- Weryfikacja struktury .claude/ — [OK / brakujace pliki]
```

### Sekcja: WYMAGANE akcje uzytkownika

```
### WYMAGANE akcje uzytkownika
```

**A) MCP serwery (jesli PENDING_INSTALLS nie jest pusty):**

```
1. **Zainstaluj brakujace MCP serwery** (OBOWIAZKOWE):
```

Dla kazdego brakujacego serwera wyswietl komende instalacji:

- Serena: `claude mcp add serena -- npx -y @anthropic/serena-mcp`
- Context7: `claude mcp add context7 -- npx -y @upstash/context7-mcp@latest`

```
2. **Zrestartuj Claude CLI** po instalacji MCP:
   # Zamknij Claude CLI (Ctrl+C lub /exit)
   # Otworz ponownie: claude
```

**B) CLAUDE.md (jesli scaffold wygenerowany):**

```
3. **Uzupelnij CLAUDE.md** o szczegoly projektu:
   - Komendy deweloperskie (dev, build, test, lint)
   - Aliasy sciezek (jesli uzywasz)
   - Zasady projektu (DB workflow, konwencje kodowania, security)
```

**C) Hooki (jesli Linux/Mac):**

```
4. **Nadaj uprawnienia hookom** (Linux/Mac):
   chmod +x .claude/hooks/*.sh
```

### Sekcja: Opcjonalne (zalecane)

```
### Opcjonalne (zalecane)
```

**D) Gemini CLI:**

```
5. **Gemini CLI** — niezalezny audyt bezpieczenstwa:
   npm install -g @google/generative-ai-cli
```

**E) PostgreSQL MCP (jesli wykryto DB):**

```
6. **PostgreSQL MCP** — bezposredni dostep do bazy:
   # Dodaj do .mcp.json lub: claude mcp add postgres -- npx -y @modelcontextprotocol/server-postgres CONNECTION_STRING
```

**F) Dostosowanie komend Ultra:**

```
7. **Dostosuj komendy Ultra** do swojego projektu:
   - Otworz .claude/commands/ultra.md
   - Zmien "genealog" na nazwe swojego projektu (w activate_project)
   - Dostosuj sciezki jesli inna struktura katalogowa
   - To samo dla ultra-think.md, ultra-workaholic.md, quick-fix.md
```

### Sekcja: Nastepne kroki

```
### Nastepne kroki

Po wykonaniu powyzszych:
- `/ultra-think "nazwa feature"` — zaplanuj architekture
- `/ultra "nazwa feature"` — pelny cykl (plan + implementacja)
- `/ultra-audit` — audyt bezpieczenstwa istniejacego kodu
- `/ultra-biz` — analiza biznesowa
```

---

## Idempotentnosc

Dwukrotne uruchomienie `/ultra-init`:

- MEMORY.md — NIE nadpisuje, dodaje tylko brakujace sekcje
- CLAUDE.md — NIE nadpisuje jesli istnieje
- MCP check — ponawia detekcje (moze wykryc nowo zainstalowane serwery)
- Output — zawsze wyswietla aktualny status
