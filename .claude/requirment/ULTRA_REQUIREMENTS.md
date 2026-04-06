# /ultra v3 - Wymagania do poprawnego dzialania

Dokument opisuje wszystkie pluginy, serwery MCP, skille, narzedzia CLI, hooki i konfiguracje wymagane do pelnego uzycia komendy `/ultra v3` w Claude Code.

---

## Spis tresci

1. [Serwery MCP (obowiazkowe)](#1-serwery-mcp-obowiazkowe)
2. [Serwery MCP (opcjonalne)](#2-serwery-mcp-opcjonalne)
3. [Pluginy Claude Code](#3-pluginy-claude-code)
4. [Skille (slash commands)](#4-skille-slash-commands)
5. [Agenty niestandardowe](#5-agenty-niestandardowe)
6. [Narzedzia CLI](#6-narzedzia-cli)
7. [Hooki](#7-hooki)
8. [Pliki pamieci](#8-pliki-pamieci)
9. [Konfiguracja plikow](#9-konfiguracja-plikow)
10. [Wbudowane narzedzia Claude Code](#10-wbudowane-narzedzia-claude-code)
11. [Macierz zaleznosci per faza](#11-macierz-zaleznosci-per-faza)
12. [Szybka instalacja](#12-szybka-instalacja)

---

## 1. Serwery MCP (obowiazkowe)

### Serena (`plugin:serena:serena`)

**Rola:** Semantyczna analiza kodu, pamiec miedzy sesjami, nawigacja po symbolach.

**Uzywane narzedzia:**
| Narzedzie | Faza /ultra | Cel |
|-----------|-------------|-----|
| `activate_project` | 0 (Bootstrap) | Aktywacja projektu w Serenie |
| `read_memory` | 0, 2 | Odczyt cache'u z poprzednich sesji |
| `write_memory` | 2, 5, 7 | Zapis research cache, architektury, lessons learned |
| `list_memories` | 0 | Sprawdzenie istniejacych pamieci |
| `edit_memory` | 7 | Aktualizacja istniejacych pamieci |
| `get_symbols_overview` | 2 (Research) | Przeglad symboli w plikach |
| `find_symbol` | 2 | Wyszukiwanie konkretnych symboli |
| `search_for_pattern` | 2 | Wyszukiwanie wzorcow w kodzie |
| `find_file` | 2 | Wyszukiwanie plikow po masce |
| `list_dir` | 2 | Listowanie katalogow |

**Instalacja:**
```bash
claude mcp add serena -- npx -y @anthropic/serena-mcp
```
Lub jako plugin Claude Code — zalezy od wersji Sereny. Sprawdz aktualna dokumentacje.

**Konfiguracja:** Serena wymaga onboardingu (`onboarding` tool) i aktywacji projektu przy pierwszym uzyciu.

---

### Context7 (`plugin:context7:context7`)

**Rola:** Pobieranie aktualnej dokumentacji frameworkow (NestJS, Prisma, Next.js, etc.) zamiast WebSearch.

**Uzywane narzedzia:**
| Narzedzie | Faza /ultra | Cel |
|-----------|-------------|-----|
| `resolve-library-id` | 2 (Research) | Rozwiazanie nazwy biblioteki na ID |
| `query-docs` | 2 | Pobranie dokumentacji |

**Instalacja:**
```bash
claude mcp add context7 -- npx -y @anthropic/context7-mcp
```

**Waznosc:** SREDNIA. Mozna zastapic przez WebSearch, ale Context7 jest tanszy (mniej tokenow) i szybszy.

---

## 2. Serwery MCP (opcjonalne)

Te serwery nie sa bezposrednio uzywane przez `/ultra`, ale moga byc wykorzystane przez subagentow lub podczas implementacji:

| Serwer | Paczka npm/pip | Cel |
|--------|---------------|-----|
| PostgreSQL | `@modelcontextprotocol/server-postgres` | Bezposredni dostep do DB (debug, query) |
| Prisma | `prisma mcp` (wbudowane w Prisma) | Status migracji, studio |
| Redis | `redis-mcp-server` (pip/uvx) | Inspekcja cache, kolejek |
| Nx | `nx-mcp@latest` | Analiza workspace, grafu zaleznosci |
| Playwright | `@playwright/mcp@latest` | Testy E2E, screenshoty |
| Sentry | `https://mcp.sentry.dev/mcp` (HTTP) | Monitoring bledow |
| shadcn | `shadcn@latest mcp` | Wyszukiwanie komponentow UI |

**Konfiguracja w `.mcp.json`:**
```json
{
  "mcpServers": {
    "nx-mcp": {
      "type": "stdio",
      "command": "npx",
      "args": ["nx-mcp@latest"]
    },
    "postgres": {
      "type": "stdio",
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-postgres", "<CONNECTION_STRING>"]
    },
    "prisma": {
      "type": "stdio",
      "command": "npx",
      "args": ["-y", "prisma", "mcp"]
    },
    "redis": {
      "type": "stdio",
      "command": "uvx",
      "args": ["--from", "redis-mcp-server@latest", "redis-mcp-server", "--url", "<REDIS_URL>"]
    }
  }
}
```

---

## 3. Pluginy Claude Code

### Ralph Loop (`ralph-loop`)

**Rola:** Autonomiczny tryb implementacji — Claude pracuje sam az do zakonczenia wszystkich faz.

**Uzywane w Fazie 5** (Setup) — jesli uzytkownik wybierze tryb "Ralph Loop (autonomicznie)".

**Dostepne skille:**
- `/ralph-loop` — uruchomienie petli
- `/cancel-ralph` — anulowanie
- `/help` — dokumentacja

**Instalacja:**
```bash
claude install ralph-loop
```
Lub recznie: dodaj pliki pluginu do `.claude/plugins/ralph-loop/`.

**Waznosc:** OPCJONALNA. Jesli uzytkownik nie chce trybu autonomicznego, Ralph Loop nie jest potrzebny. Tryb "Interaktywnie" uzywa `/dev-docs-execute` zamiast tego.

---

## 4. Skille (slash commands)

Pliki `.md` w `.claude/commands/`. Kazdy musi istniec w repozytorium.

### Obowiazkowe (uzywane bezposrednio przez /ultra lub jego flow)

| Skill | Plik | Rola w /ultra |
|-------|------|---------------|
| `/ultra` | `.claude/commands/ultra.md` | Glowny orkiestrator |
| `/dev-docs-execute` | `.claude/commands/dev-docs-execute.md` | Wykonanie kolejnej fazy (tryb interaktywny) |
| `/checkpoint` | `.claude/commands/checkpoint.md` | Zapis stanu sesji (WIP commit + Serena memory) |

### Zalecane (pelny cykl zycia feature'a)

| Skill | Plik | Rola |
|-------|------|------|
| `/dev-docs` | `.claude/commands/dev-docs.md` | Bazowe planowanie (lzejsza wersja /ultra) |
| `/dev-docs-review` | `.claude/commands/dev-docs-review.md` | Code review fazy przez subagenta |
| `/dev-docs-update` | `.claude/commands/dev-docs-update.md` | Aktualizacja dokumentacji przed compaction |
| `/dev-docs-complete` | `.claude/commands/dev-docs-complete.md` | Archiwizacja ukonczonych zadan |
| `/code-review` | skill z konfiguracji | Pelny code review (PR/fazy) |
| `/tidy` | `.claude/commands/tidy.md` | Cleanup kodu po implementacji |
| `/gemini` | `.claude/commands/gemini.md` | Uruchomienie Gemini CLI jako subagenta |

---

## 5. Agenty niestandardowe

Definiowane w `.claude/agents/` lub jako wbudowane typy subagentow.

### Wbudowane typy (dostepne domyslnie w Claude Code)

| Typ | Faza | Model | Cel |
|-----|------|-------|-----|
| `Explore` | 2 (Research) | haiku | Skanowanie codebase |
| `Plan` | 3 (Architecture) | sonnet | Projektowanie architektury |
| `general-purpose` | 5 (Setup) | sonnet | Generowanie plikow dokumentacji |

### Niestandardowe (wymagaja definicji)

| Agent | Faza | Cel |
|-------|------|-----|
| `web-research-specialist` | 2 (Research, COMPLEX) | Best practices z internetu |
| `security-auditor` | 3.5 (Audit) | Audyt bezpieczenstwa |

**Konfiguracja agentow niestandardowych:**
Pliki definicji w `.claude/agents/` — kazdy agent to plik `.md` z opisem roli i instrukcjami.

Przykladowa definicja `security-auditor`:
```markdown
---
name: security-auditor
description: Proactively scans for security vulnerabilities. Reviews code and infrastructure configurations against common security risks like the OWASP Top 10.
---
```

Przykladowa definicja `web-research-specialist`:
```markdown
---
name: web-research-specialist
description: Use this agent when you need to research information on the internet...
---
```

**Waznosc agentow:**
- `security-auditor` — **OBOWIAZKOWY** (faza 3.5 zawsze uruchamia audyt)
- `web-research-specialist` — **OPCJONALNY** (tylko dla COMPLEX features)

---

## 6. Narzedzia CLI

### Obowiazkowe

| Narzedzie | Cel w /ultra | Instalacja |
|-----------|-------------|------------|
| `git` | Zarzadzanie branchami, commitami | Standardowo zainstalowany |
| `pnpm` (v10+) | Package manager projektu | `npm install -g pnpm` |
| `npx` | Uruchamianie Prisma, MCP serwerow | Czesc Node.js |
| `node` (>=18.x) | Runtime | nodejs.org |
| `nx` | Monorepo tooling | `pnpm add -g nx` lub `npx nx` |

### Opcjonalne

| Narzedzie | Cel w /ultra | Kiedy wymagane | Instalacja |
|-----------|-------------|----------------|------------|
| `gemini` | Niezalezny audyt bezpieczenstwa | STANDARD/COMPLEX features (Faza 3.5) | `npm install -g @google/generative-ai-cli` lub [Gemini CLI docs](https://github.com/google-gemini/gemini-cli) |
| `eslint` | Auto-fix po edycjach (hook) | Jesli hook skonfigurowany | `pnpm add -D eslint` (juz w projekcie) |
| `uvx` | Uruchomienie Redis MCP server | Jesli Redis MCP potrzebny | `pip install uv` |

**Gemini CLI — szczegoly:**
- Uzywany w Fazie 3.5 (Krok 2) jako niezalezny audytor
- Uruchamiany przez: `gemini -p "<prompt>"`
- Wyniki zapisywane w `dev/gemini/`
- Jesli Gemini CLI nie jest zainstalowany — `/ultra` automatycznie pomija ten krok i opiera sie na self-audit (Sonnet)

---

## 7. Hooki

Konfiguracja w `.claude/settings.json` pod kluczem `hooks`.

### Uzywane przez /ultra

| Hook | Typ | Trigger | Cel |
|------|-----|---------|-----|
| `auto-eslint-fix.sh` | PostToolUse | `Edit\|MultiEdit\|Write` | Automatyczny ESLint fix po kazdej edycji |
| `post-tool-use-tracker.sh` | PostToolUse | `Edit\|MultiEdit\|Write` | Sledzenie edytowanych plikow |
| `skill-activation-prompt.sh` | UserPromptSubmit | Kazdy prompt | Automatyczne sugerowanie skilli |

### Konfiguracja w `.claude/settings.json`

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|MultiEdit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "bash $CLAUDE_PROJECT_DIR/.claude/hooks/auto-eslint-fix.sh"
          }
        ]
      },
      {
        "matcher": "Edit|MultiEdit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "$CLAUDE_PROJECT_DIR/.claude/hooks/post-tool-use-tracker.sh"
          }
        ]
      }
    ],
    "UserPromptSubmit": [
      {
        "hooks": [
          {
            "type": "command",
            "command": "$CLAUDE_PROJECT_DIR/.claude/hooks/skill-activation-prompt.sh"
          }
        ]
      }
    ]
  }
}
```

**Waznosc:** OPCJONALNA ale ZALECANA. Hooki automatyzuja linting i tracking — bez nich trzeba reczne `nx lint` po kazdej fazie.

---

## 8. Pliki pamieci

### Auto Memory (Claude Code)

Katalog: `~/.claude/projects/<project-hash>/memory/`

**Wymagane pliki:**
| Plik | Cel |
|------|-----|
| `MEMORY.md` | Glowny indeks — ladowany automatycznie do system prompt |

**Opcjonalne pliki tematyczne** (referencowane przez `/ultra` Faze 0):
| Plik | Zawartosc |
|------|-----------|
| `systems-core.md` | Equipment, Rooms, Calendar, Media Library |
| `systems-platform.md` | Notifications, Feature Flags, SSE, Search, Export/Import |
| `systems-admin.md` | Branding, i18n, GDPR, Mailing, Invitations |
| `patterns.md` | Recent Tenants, Circular Dependencies, Attribute Groups |

Pliki tematyczne sa przeszukiwane przez `Grep` w Fazie 0 (Krok 3) aby znalezc kontekst powiazany z nowym feature'em.

### Serena Memory

Pamiec Sereny jest niezalezna od Auto Memory. Przechowywana przez serwer MCP Sereny.

**Odczytywane w Fazie 0:**
- `codebase_structure` — struktura codebase
- `code_style_conventions` — konwencje kodowania
- `[feature]_architecture` — architektura feature'a (jesli istnial wczesniej)
- `[feature]_research_cache` — cache z researchu
- `[feature]_lessons_learned` — wnioski z implementacji

**Zapisywane:**
- `[feature]_research_cache` — po Fazie 2
- `[feature]_architecture` — w Fazie 5
- `[feature]_lessons_learned` — w Fazie 7
- `session_checkpoint` — przez `/checkpoint`

---

## 9. Konfiguracja plikow

### Wymagana struktura katalogow

```
.claude/
  commands/
    ultra.md                    # OBOWIAZKOWY
    dev-docs-execute.md         # OBOWIAZKOWY
    checkpoint.md               # ZALECANY
    gemini.md                   # OPCJONALNY (dla STANDARD/COMPLEX)
    dev-docs.md                 # ZALECANY
    dev-docs-review.md          # ZALECANY
    dev-docs-update.md          # ZALECANY
    dev-docs-complete.md        # ZALECANY
    tidy.md                     # ZALECANY
  hooks/
    auto-eslint-fix.sh          # ZALECANY
    post-tool-use-tracker.sh    # ZALECANY
    skill-activation-prompt.sh  # OPCJONALNY
  settings.json                 # hooks config
  settings.local.json           # permissions (per-user)
.mcp.json                       # MCP servers config
CLAUDE.md                       # projekt instructions (OBOWIAZKOWY)
dev/
  active/                       # tworzone dynamicznie przez /ultra
  completed/                    # archiwum (przez /dev-docs-complete)
  gemini/                       # output Gemini CLI
```

### `.claude/settings.json` — minimalna konfiguracja

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|MultiEdit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "bash $CLAUDE_PROJECT_DIR/.claude/hooks/auto-eslint-fix.sh"
          }
        ]
      }
    ]
  }
}
```

### `.mcp.json` — minimalna konfiguracja

```json
{
  "mcpServers": {}
}
```

Serena i Context7 sa instalowane jako pluginy Claude Code, nie przez `.mcp.json`.

---

## 10. Wbudowane narzedzia Claude Code

Te narzedzia sa wbudowane w Claude Code i nie wymagaja instalacji:

| Narzedzie | Faza | Cel |
|-----------|------|-----|
| `EnterPlanMode` | 0 | Wejscie w tryb planowania |
| `ExitPlanMode` | 4 | Automatyczne wyjscie po prezentacji planu |
| `AskUserQuestion` | 1 (Discovery) | Zbieranie wymagan od uzytkownika |
| `ToolSearch` | 0 | Ladowanie deferred tools (MCP) |
| `Task` | 2, 3, 3.5, 5 | Uruchamianie subagentow |
| `Read` / `Write` / `Edit` | 5-6 | Operacje na plikach |
| `Glob` / `Grep` | 0, 2 | Wyszukiwanie plikow i wzorcow |
| `Bash` | 5-7 | Git, mkdir, generowanie plikow |
| `WebSearch` | 2 (fallback) | Jesli Context7 niedostepny |

---

## 11. Macierz zaleznosci per faza

| Faza | Serena | Context7 | Ralph Loop | Gemini CLI | Hooki | Auto Memory |
|------|--------|----------|------------|------------|-------|-------------|
| 0 Bootstrap | **TAK** | nie | nie | nie | nie | **TAK** |
| 1 Discovery | nie | nie | nie | nie | nie | nie |
| 2 Research | **TAK** | opcjonalnie | nie | nie | nie | nie |
| 3 Architecture | nie | nie | nie | nie | nie | nie |
| 3.5 Audit | nie | nie | nie | **STANDARD/COMPLEX** | nie | nie |
| 4 Review | nie | nie | nie | nie | nie | nie |
| 5 Setup | **TAK** | nie | **jesli wybrano** | nie | nie | nie |
| 6 Documentation | nie | nie | nie | nie | nie | **TAK** |
| 7 Lessons | **TAK** | nie | nie | nie | nie | nie |
| *implementacja* | opcjonalnie | opcjonalnie | **jesli wybrano** | nie | **TAK** | nie |

---

## 12. Szybka instalacja

### Minimum (CRUD features)

```bash
# 1. Pluginy Claude Code
claude install serena          # semantyczna analiza kodu
claude install context7        # dokumentacja frameworkow

# 2. Kopiuj pliki commands
# Skopiuj .claude/commands/ultra.md i dev-docs-execute.md do swojego projektu

# 3. CLAUDE.md
# Skopiuj lub dostosuj CLAUDE.md z instrukcjami projektu

# 4. Gotowe!
# /ultra "moj nowy feature"
```

### Pelna instalacja (STANDARD/COMPLEX features)

```bash
# 1. Pluginy Claude Code
claude install serena
claude install context7
claude install ralph-loop      # autonomiczny tryb implementacji

# 2. Gemini CLI (dla niezaleznego audytu)
npm install -g @google/generative-ai-cli
# lub: https://github.com/google-gemini/gemini-cli

# 3. Kopiuj WSZYSTKIE pliki commands
cp -r .claude/commands/ <twoj-projekt>/.claude/commands/

# 4. Kopiuj hooki
cp -r .claude/hooks/ <twoj-projekt>/.claude/hooks/
cp .claude/settings.json <twoj-projekt>/.claude/settings.json

# 5. Agenty niestandardowe
cp -r .claude/agents/ <twoj-projekt>/.claude/agents/

# 6. MCP serwery (opcjonalnie)
cp .mcp.json <twoj-projekt>/.mcp.json
# Dostosuj connection stringi w .mcp.json

# 7. CLAUDE.md
# Dostosuj do swojego projektu

# 8. Gotowe!
# /ultra "moj nowy feature"
```

### Weryfikacja instalacji

```
/ultra "test feature"
```

Jesli poprawnie:
- Faza 0 aktywuje Serene i laduje pamieci
- Faza 1 zadaje pytania przez AskUserQuestion
- Jesli Serena nie dziala — zobaczysz blad ToolSearch

**Troubleshooting:**
- "Serena not found" → `claude install serena` lub sprawdz plugin
- "Gemini CLI not found" → `/ultra` pominie Gemini audit (self-audit only)
- "Ralph Loop not found" → opcja "Ralph Loop" nie bedzie dostepna w Fazie 5
- Hook errors → sprawdz `chmod +x .claude/hooks/*.sh`

---

## Podsumowanie waznosci

| Komponent | Waznosc | Bez niego... |
|-----------|---------|-------------|
| Serena MCP | **KRYTYCZNY** | Brak pamieci, brak semantycznej analizy kodu — /ultra straci 60% efektywnosci |
| Context7 MCP | SREDNI | Fallback na WebSearch — wolniejsze i drozsze |
| Ralph Loop | OPCJONALNY | Brak trybu autonomicznego — tylko interaktywny |
| Gemini CLI | OPCJONALNY | Brak niezaleznego audytu — tylko self-audit Sonnet |
| Hooki (ESLint) | ZALECANY | Ręczne `nx lint` po implementacji |
| Auto Memory | ZALECANY | Brak cache'u miedzy sesjami — wiecej tokenow na research |
| CLAUDE.md | **KRYTYCZNY** | Subagenty nie znaja standardow projektu |
| `/dev-docs-execute` | **KRYTYCZNY** | Brak trybu interaktywnej implementacji |
| `/checkpoint` | ZALECANY | Brak snapshot'ow sesji |
