---
description: Inteligentny orkiestrator feature development v3 - research, planowanie, architektura
argument-hint: Opisz feature (np. "system rooms powiazany z facilities, pokoje statyczne i wirtualne")
allowed-tools: Task, Read, Write, Edit, MultiEdit, Glob, Grep, Bash, AskUserQuestion, ToolSearch, EnterPlanMode, ExitPlanMode, Skill(ralph-loop:*), Skill(checkpoint:*)
---

## Kontekst sesji

Na poczatku sesji wykonaj `git branch --show-current`, `git status --short`, `git log --oneline -5` zeby zrozumiec stan repo.

- Sprawdz czy jestes na wlasciwym branchu (oczekiwany: feature/_ lub feat/_)
- Sprawdz czy sa uncommitted changes z poprzedniej sesji

# /ultra v3 - Inteligentny orkiestrator feature development

Jestes elitarnym architektem software. Tworzysz kompletny plan nowego feature'a dla projektu genealog.
Stack i standardy projektu sa w CLAUDE.md — NIE powtarzaj ich w promptach subagentow.
Dzialasz w 8 fazach: Bootstrap → Discovery → Research → Architecture → Audit → Review+Auto-Switch → Setup → Documentation.
Fazy 0-4 = PLANOWANIE (plan mode). Fazy 5-7 = EDYCJA (editor mode). Przejscie jest AUTOMATYCZNE.

## Zadanie od uzytkownika

$ARGUMENTS

---

## KLASYFIKACJA KOSZTU (automatyczna)

Przed rozpoczeciem OKLASYFIKUJ feature jako jeden z 3 typow:

| Typ          | Opis                                                    | Research         | Architecture   | Audit                      | Max rownolegych agentow |
| ------------ | ------------------------------------------------------- | ---------------- | -------------- | -------------------------- | ----------------------- |
| **CRUD**     | Standardowy CRUD (lista, formularz, szczegoly)          | 1 agent haiku    | 1 agent sonnet | self-audit sonnet          | 2-3                     |
| **STANDARD** | CRUD + niestandardowe elementy (integracje, scheduling) | 1-2 agenty haiku | 1 agent sonnet | self-audit sonnet + Gemini | 3-4                     |
| **COMPLEX**  | Nowy pattern UX, real-time, zewn. API, event-driven     | 2-3 agenty haiku | 1 agent sonnet | self-audit sonnet + Gemini | 4-5                     |

**WAZNE:** Gemini audit TYLKO dla STANDARD i COMPLEX. Dla CRUD wystarczy self-audit na Sonnet.

---

## TRYB PRACY: Plan Mode → Editor Mode

### Reguly:

1. **START:** Jesli NIE jestes w plan mode → NATYCHMIAST uzyj `EnterPlanMode`
2. **FAZY 0-4 (Plan Mode):** TYLKO read/search/ask. ZABRONIONE: Write, Edit, git commit
3. **FAZA 4 → 5 (AUTO-SWITCH):** Po wyswietleniu podsumowania → `ExitPlanMode` AUTOMATYCZNIE (bez pytan!)
4. **FAZY 5-7 (Editor Mode):** Write, Edit, Bash, git commit dozwolone

**WYJATEK:** Audyt zwrocil FAIL → zapytaj usera zanim przejdziesz do edycji.

---

## ZASADY MINIMALIZACJI KOSZTOW

1. **OBOWIAZKOWE Serena MCP** — `get_symbols_overview`, `find_symbol`, `search_for_pattern` zamiast Read/cat na plikach zrodlowych. Serena MUSI byc aktywna od Fazy 0 i uzywana we WSZYSTKICH fazach wymagajacych analizy kodu.
2. **OBOWIAZKOWE MCP-first** — Context7 MCP dla docs frameworkow (ZANIM siegniesz po WebSearch). Keycloak MCP dla auth. Postgres MCP dla query. Uzywaj AKTYWNYCH serwerow MCP — nie ignoruj ich.
3. **Memory-first** — sprawdz pamieci Sereny ZANIM uruchomisz research
4. **CLAUDE.md jest juz zaladowane** — NIE powtarzaj standardow projektu w promptach subagentow
5. **Modele:** Research = haiku, Architecture = sonnet, Audit = sonnet (CRUD) / sonnet+gemini (COMPLEX)
6. **Nigdy nie czytaj calych plikow** — `find_symbol(include_body=false)` najpierw
7. **Po Discovery:** /compact (streszcza historie, oszczedza input tokens w kolejnych fazach)

---

## ZASADY ROWNOLEGLYCH AGENTOW (fan-out/fan-in)

**Oficjalne zalecenie:** 3-5 rownolegych agentow to sweet spot. 2 to za malo dla wielowarstwowych features.

### Kiedy uruchamiac wielu agentow rownolegle:

- **3+ niezaleznych zadan** bez wspoldzielonego stanu
- **Jasne granice plikow** — kazdy agent operuje na INNYM zbiorze plikow
- **Nie ma zaleznosci** miedzy wynikami agentow (jesli Agent B potrzebuje wyniku Agent A → sekwencyjnie)

### Zasady bezpieczenstwa rownoleglosci:

- **KAZDY agent = wlasny zbior plikow.** Dwoch agentow edytujacych ten sam plik → nadpisania!
- **Uzywaj `isolation: "worktree"`** gdy agenty musza edytowac kod (nie dotyczy read-only research)
- **Fan-in:** Po zakonczeniu agentow — orkiestrator ZAWSZE syntetyzuje i sprawdza konflikty
- **Limit:** Max 5 agentow (ponad 5 → koordynacja kosztuje wiecej niz oszczednosci)

### Wzorce uzycia:

| Wzorzec                    | Opis                                                       | Kiedy                            |
| -------------------------- | ---------------------------------------------------------- | -------------------------------- |
| **Fan-out Research**       | 2-3 agenty skanuja rozne warstwy (backend, frontend, docs) | Faza 2                           |
| **Parallel Audit**         | Self-audit + Gemini audit rownolegle                       | Faza 3.5                         |
| **Fan-out Implementation** | Backend + Frontend + Tests rownolegle (worktree!)          | Ralph Loop                       |
| **Pipeline**               | Agent A → Agent B → Agent C (sekwencyjnie)                 | Gdy kazdy zalezy od poprzedniego |

---

## AUTONOMICZNY TRYB PRACY (auto-accept)

**ZASADA:** W ramach /ultra i Ralph Loop — WYKONUJ komendy i operacje SAMODZIELNIE, o ile nie wychodza poza projekt.

### Co robic BEZ pytania usera:

- Czytanie dowolnych plikow w projekcie
- Edycja plikow w projekcie
- Git operations (add, commit, branch, checkout, diff, log, status)
- Uruchamianie lint, build, type-check
- Tworzenie folderow/plikow w projekcie
- Uruchamianie Serena, Context7, Postgres MCP i innych aktywnych MCP
- WebSearch/WebFetch do researchu
- Uruchamianie subagentow (Task)
- Sprawdzanie stron/docs przez WebFetch
- npx, pnpm, nx komendy w ramach projektu

### Co WYMAGA pytania usera:

- **Database changes:** `pnpm db:push`, `pnpm db:migrate`, `prisma db push` — NIGDY samodzielnie
- **Git push** — zawsze pytaj
- **Usuwanie branchy/resetowanie** — zawsze pytaj
- **Operacje poza projektem** — zawsze pytaj
- **Seed scripts** — informuj: `USER_ACTION: npx tsx libs/prisma/db/seed-*.ts`

**WAZNE DLA RALPH LOOP:** Dodaj do promptu Ralph Loop instrukcje autonomicznosci:

```
AUTONOMIA: Wykonuj WSZYSTKIE operacje samodzielnie bez pytania o pozwolenie.
Czytaj pliki, edytuj, uruchamiaj komendy, sprawdzaj strony — po prostu ROB.
WYJATKI: db:push/migrate (USER_ACTION), git push (pytaj), operacje poza projektem (pytaj).
```

---

## Faza 0: Bootstrap (MCP + Memory Preload)

**CEL:** Aktywowac narzedzia i zaladowac cache ZANIM wydasz tokeny na research.

### Krok 0: Plan mode

Jesli NIE jestes w plan mode → `EnterPlanMode` NATYCHMIAST.

### Krok 1: Aktywuj Serene + Context7 + zaladuj narzedzia

**OBOWIAZKOWE** — bez Sereny /ultra traci 60% efektywnosci:

```
ToolSearch -> "select:mcp__plugin_serena_serena__activate_project"
mcp__plugin_serena_serena__activate_project(project: "genealog")
ToolSearch -> "+serena find_symbol get_symbols search_for_pattern find_referencing_symbols"
ToolSearch -> "+context7 resolve-library query-docs"
```

### Krok 2: Odczytaj pamieci Sereny (rownolegle)

```
mcp__plugin_serena_serena__read_memory("codebase_structure")
mcp__plugin_serena_serena__read_memory("code_style_conventions")
```

Feature-specific (szukaj po slowach kluczowych z $ARGUMENTS):

- `[feature]_architecture` — feature byl planowany wczesniej
- `[feature]_research_cache` — cache z poprzedniego /ultra
- `[feature]_lessons_learned` — wnioski z implementacji

**Decision na podstawie pamieci:**

- STATUS = "IMPLEMENTED" → Zapytaj usera: rozbudowa istniejacego czy nowy feature?
- STATUS = "PLANNED" → Zapytaj usera: kontynuowac plan czy zaczac od nowa?
- Brak pamieci → Standardowy flow

### Krok 3: Przeszukaj pliki pamieci tematycznych

MEMORY.md zawiera tylko indeks — szczegoly systemow sa w osobnych plikach:

- `systems-core.md` — Equipment, Rooms, Calendar, Media Library
- `systems-platform.md` — Notifications, Feature Flags, SSE, Search, Export/Import, Monitoring, Usage
- `systems-admin.md` — Branding, i18n, GDPR, Mailing, Invitations, Integrations
- `patterns.md` — Recent Tenants, Circular Dependencies, Attribute Groups

**Wyszukaj pliki powiazane z $ARGUMENTS:**

```
Grep(pattern: "[slowa kluczowe z $ARGUMENTS]", path: "memory/", glob: "*.md", output_mode: "content", context: 5)
```

Zapisz wyniki jako `MEMORY_CONTEXT` — przekaz do Architecture (Faza 3).

### Krok 4: Okresl flagi skip

| Flaga                  | true jesli...                                                         |
| ---------------------- | --------------------------------------------------------------------- |
| SKIP_CODEBASE_PATTERNS | pamieci `codebase_structure` + `code_style_conventions` wystarczajace |
| SKIP_WEB_RESEARCH      | feature to CRUD lub STANDARD                                          |

---

## Faza 1: Discovery (inteligentne zbieranie wymagan)

**CEL:** Zebrac KOMPLETNY obraz wymagan. Tyle rund ile potrzeba — nie za malo, nie za duzo.

### Strategia pytan:

1. **Przeanalizuj $ARGUMENTS** — ile juz wiesz? Jakie pytania sa KONIECZNE?
2. **Sformuluj WSZYSTKIE pytania** potrzebne do zaprojektowania architektury
3. **1 runda = 1 AskUserQuestion** z WSZYSTKIMI pytaniami naraz

### Runda 1 (ZAWSZE): Glowne pytania

Uzyj **AskUserQuestion** ze WSZYSTKIMI pytaniami ktore sa potrzebne. Dobierz dynamicznie — POMIN te ktore wynikaja z $ARGUMENTS.

**Pula pytan** (wybierz te ktore sa konieczne):

1. **Relacje danych** — Z jakimi modelami powiazany? FK/relacje?
2. **RBAC** — Role i granularnosc uprawnien
3. **UX/Strony** — Lista, formularz, szczegoly, dashboard widget?
4. **Scope** — MVP vs pelna wersja? Co w pierwszej iteracji?
5. **Enumy/Statusy** — Jakie stany? Workflow statusow?
6. **Edge cases** — Co jesli...? Limity? Walidacja?
7. **Nowe paczki** — Potrzebne dodatkowe biblioteki?
8. **Workflow/UX flow** — Jak user przechodzi miedzy ekranami?
9. **Integracje** — Z jakimi zewnetrznymi systemami?
10. **Multi-tenancy** — Scope danych: per-tenant, per-company, globalny?

**Format:** Opcje z opisami, nie otwarte pytania.

**JESLI >5 PYTAN:** Na koncu dodaj pole "Uwagi":

```
Dodatkowe uwagi / komentarze:
Jesli masz jakies dodatkowe uwagi, wymagania lub pomysly ktore nie mieszcza sie
w powyzszych pytaniach — wpisz je tutaj. Czesto po przeczytaniu tylu pytan
nasuwaja sie dodatkowe przemyslenia.
```

### Rundy dodatkowe (tyle ile potrzeba)

Po kazdej rundzie ocen:

- **Mam pelny obraz?** → ZAKONCZ Discovery
- **Brakuje informacji?** → Kolejna runda z KONKRETNYMI pytaniami follow-up

**NIE MA LIMITU RUND.** Lepiej zapytac raz wiecej niz zgadywac w architekturze.

**ZAKONCZ Discovery gdy:** masz pewnosc ze mozesz zaprojektowac architekture bez zgadywania.

### Po Discovery

Zapisz jako `DISCOVERY_RESULTS`. Przy przekazywaniu do agentow:

- **Haiku (Research)**: STRESZCZAJ do 1-3 zdan
- **Sonnet (Architecture)**: Przekaz PELNE wyniki

---

## Faza 2: Research (1-3 agenty haiku — rownolegle!)

**CEL:** Zebranie kontekstu z codebase — TANIO, z OBOWIAZKOWYM uzyciem Serena MCP.

### DECISION TREE

| Warunek                                    | Akcja                                                      |
| ------------------------------------------ | ---------------------------------------------------------- |
| Pamiec `[feature]_research_cache` istnieje | SKIP CALY Research                                         |
| SKIP_CODEBASE_PATTERNS=true                | Agent 1 skanuje TYLKO feature-specific (nie ogolne wzorce) |
| Feature = CRUD                             | SKIP Agent 2 (web research)                                |

### Agent 1: Codebase Explorer (haiku) — ZAWSZE (chyba ze cache)

Skanuje backend + Prisma + permissions:

```
subagent_type: "Explore"
model: "haiku"
max_turns: 15
prompt: |
  Zbadaj codebase genealog pod katem analogicznego feature'a.
  ZADANIE: [krotki opis z $ARGUMENTS]
  WYMAGANIA: [1-2 zdan streszczenia DISCOVERY_RESULTS]
  Standardy projektu sa w CLAUDE.md (juz zaladowane) — NIE szukaj ich.

  ## OBOWIAZKOWE: Zaladuj Serene
  1. ToolSearch("select:mcp__plugin_serena_serena__activate_project")
  2. mcp__plugin_serena_serena__activate_project(project: "genealog")
  3. ToolSearch("+serena find_symbol get_symbols search_for_pattern")
  Serena jest OBOWIAZKOWA. Uzywaj jej do nawigacji po kodzie zamiast Read.

  ## PRE) Przeszukaj pliki pamieci tematycznych
  Grep(pattern: "[slowa kluczowe z ZADANIA]", path: "memory/", glob: "*.md", output_mode: "content", context: 5)
  Uzyj wynikow do zorientowania sie ktore systemy sa powiazane ZANIM zaczniesz skanowac codebase.

  ## A) Analogiczny modul backendu
  mcp__plugin_serena_serena__find_file(file_mask: "*.module.ts", relative_path: "apps/backend/src/app")
  Wybierz 1-2 analogiczne moduly. Potem:
  mcp__plugin_serena_serena__get_symbols_overview(relative_path: "...controller.ts", depth: 1)

  ## B) Wzorce dekoratorow
  mcp__plugin_serena_serena__search_for_pattern(
    substring_pattern: "@RequirePermissions|@Cache|@InvalidateCache",
    relative_path: "apps/backend/src/app/[analogiczny-modul]",
    restrict_search_to_code_files: true, context_lines_after: 2
  )

  ## C) Prisma schema — TYLKO powiazane modele
  mcp__plugin_serena_serena__search_for_pattern(
    substring_pattern: "model [NazwaPodobnegModelu]",
    relative_path: "libs/prisma/db/schema.prisma", context_lines_after: 15
  )

  ## D) Permissions seed
  mcp__plugin_serena_serena__search_for_pattern(
    substring_pattern: "permissions.*=.*\\[",
    relative_path: "libs/prisma/db", paths_include_glob: "**/seed-*-permissions.ts",
    context_lines_after: 10
  )

  ## ZWROC (kompaktowo, max 300 slow):
  - Analogiczny modul: nazwa + endpointy
  - Dekoratory: @Cache, @InvalidateCache, @RequirePermissions
  - Prisma model-wzorzec: pola, relacje, enumy
  - Permissions seed: format
```

### Agent 2: Frontend + Validation Explorer (haiku) — ROWNOLEGLE z Agent 1

**Uruchamiaj ROWNOLEGLE z Agent 1** (fan-out pattern):

```
subagent_type: "Explore"
model: "haiku"
max_turns: 12
prompt: |
  Zbadaj frontend i walidacje genealog pod katem analogicznego feature'a.
  ZADANIE: [krotki opis z $ARGUMENTS]
  Standardy projektu sa w CLAUDE.md (juz zaladowane) — NIE szukaj ich.

  ## OBOWIAZKOWE: Zaladuj Serene
  1. ToolSearch("select:mcp__plugin_serena_serena__activate_project")
  2. mcp__plugin_serena_serena__activate_project(project: "genealog")
  3. ToolSearch("+serena find_symbol get_symbols search_for_pattern")
  Serena jest OBOWIAZKOWA.

  ## A) Walidacja Zod
  mcp__plugin_serena_serena__find_file(file_mask: "*.schema.ts", relative_path: "libs/validation/src")
  get_symbols_overview na 1 analogicznym pliku.

  ## B) Frontend — strony
  mcp__plugin_serena_serena__list_dir(relative_path: "apps/frontend/src/app/(protected)/[analogiczny]", recursive: true)

  ## C) Frontend — queries
  mcp__plugin_serena_serena__find_file(file_mask: "*.queries.ts", relative_path: "apps/frontend/src/queries")
  get_symbols_overview na analogicznym pliku.

  ## D) Frontend — komponenty
  mcp__plugin_serena_serena__list_dir(relative_path: "apps/frontend/src/components/[analogiczny]", recursive: true)

  ## E) Docs (SELEKTYWNIE — uzyj Context7 MCP dla docs frameworkow!)
  ToolSearch("+context7 resolve-library query-docs")
  Uzyj Context7 jesli potrzebujesz aktualnej dokumentacji NestJS/Prisma/Next.js.
  Uzyj search_for_pattern na docs/ dla wewnetrznej dokumentacji projektu.

  ## ZWROC (kompaktowo, max 300 slow):
  - Zod: nazwy schematow, patterny
  - Frontend: pliki stron + queries + komponenty
  - Kluczowe wytyczne z docs (max 3 punkty)
```

### Agent 3: Best Practices Research (OPCJONALNY — TYLKO dla COMPLEX, rownolegle!)

```
subagent_type: "web-research-specialist"
model: "haiku"
max_turns: 8
prompt: |
  Zbadaj best practices dla: [opis z $ARGUMENTS]
  Szukaj: UX patterns, data model patterns, API design, edge cases.
  ZWROC max 300 slow.
```

**ORCHESTRACJA:** Dla STANDARD/COMPLEX — uruchom Agent 1 + Agent 2 + (opcjonalnie Agent 3) ROWNOLEGLE w jednym message. Fan-in po zakonczeniu wszystkich.

### Po agentach: Zapisz cache

```
mcp__plugin_serena_serena__write_memory(
  memory_file_name: "[feature]_research_cache",
  content: "[max 500 slow kluczowych wnioskow z WSZYSTKICH agentow]"
)
```

---

## Faza 3: Architecture (1 agent sonnet)

**CEL:** Kompletna architektura feature'a.

```
subagent_type: "Plan"
model: "sonnet"
max_turns: 12
prompt: |
  Zaprojektuj architekture feature'a dla genealog.
  Standardy projektu (API format, Prisma konwencje, Zod v4, NestJS patterns) sa w CLAUDE.md — stosuj je.

  ## Zadanie
  [pelny opis z $ARGUMENTS]

  ## Wymagania usera
  [pelne DISCOVERY_RESULTS]

  ## Research
  [wyniki Agent 1 + Agent 2 LUB dane z pamieci Sereny]
  [wyniki Agent 3 jesli uruchomiony]

  ## Kontekst z pamieci (MEMORY_CONTEXT)
  [wyniki Grep z Fazy 0 Krok 3 — powiazane systemy, ich endpointy, modele, permissions]

  ## Zaprojektuj:

  ### 1. Prisma Schema
  Nowe modele, enumy, relacje. UUID z dbgenerated("uuid_generate_v4()"), StatusEnum, timestamps.

  ### 2. Migration SQL
  YYYYMMDDHHMMSS_[nazwa]. User NIE MA CREATEDB — migracja recznie.

  ### 3. Backend (NestJS)
  Controller, service, repository, module, dto. Lista endpointow z HTTP, sciezkami, permissions.
  Cache keys + invalidation. Dekoratory: @Cache, @InvalidateCache, @RequirePermissions.

  ### 4. Validation (Zod v4)
  libs/validation/src/[feature]/. Create, Update, Query schemas. z.email(), z.uuid(), z.iso.datetime().

  ### 5. Frontend (Next.js 15)
  Strony app/(protected)/, API routes app/api/, React Query hooks queries/, komponenty components/[feature]/.

  ### 6. Permissions Seed
  seed-[feature]-permissions.ts. Przypisanie do rol.

  ### 7. Fazy implementacji (3-6 faz)
  Faza 1: schema + migration + backend CRUD
  Faza 2: validation + frontend pages
  Faza 3+: wg potrzeb. Ostatnia: dokumentacja w docs/

  ### 8. Ryzyka i edge cases

  ZWROC pelny plan markdown.
```

---

## Faza 3.5: Security & Architecture Audit

**CEL:** Walidacja bezpieczenstwa i architektury ZANIM plan trafi do implementacji.
**ZASADA:** ZAWSZE wykonywana. Tier audytu zalezy od KLASYFIKACJI KOSZTU.

### Krok 1: Self-Audit (ZAWSZE) + Gemini Audit (STANDARD/COMPLEX) — ROWNOLEGLE!

**Uruchamiaj OBA audyty jednoczesnie** (fan-out):

**Self-Audit:**

| Klasyfikacja | Model  | Max turns |
| ------------ | ------ | --------- |
| CRUD         | sonnet | 6         |
| STANDARD     | sonnet | 8         |
| COMPLEX      | sonnet | 8         |

```
subagent_type: "security-auditor"
model: "sonnet"
max_turns: [wg tabeli]
prompt: |
  Jestes audytorem bezpieczenstwa systemow z 15-letnim doswiadczeniem (OWASP, GDPR/RODO).
  Kontekst: Multi-tenant SaaS (genealog). Stack w CLAUDE.md.

  ## Architektura do audytu: [NAZWA FEATURE]

  ### Data Model:
  [modele Prisma z planu]

  ### Endpointy API:
  [tabela endpointow]

  ### Uprawnienia:
  [tabela per rola]

  ### Cache:
  [cache keys + invalidation]

  ## Audytuj:
  1. BEZPIECZENSTWO: Tenant isolation, RBAC, IDOR, mass assignment, input validation
  2. ARCHITEKTURA: Normalizacja, indeksy, FK constraints, skalowalosc, cache coherence
  3. EDGE CASES: Race conditions, transakcje
  4. TRENDY 2025/2026

  ## Format raportu:
  ### KRYTYCZNE — [ID-K1] problem → naprawa
  ### POWAZNE — [ID-P1] problem → naprawa
  ### DROBNE — [ID-D1] problem → naprawa
  ### POZYTYWNE — [co dobrze]
  ### PODSUMOWANIE — Ocena: PASS / PASS WITH CONDITIONS / FAIL

  Max 600 slow, po polsku, surowo i konkretnie.
```

**Gemini Audit (TYLKO STANDARD/COMPLEX — rownolegle z Self-Audit!):**

```bash
gemini -p "Jestes niezaleznym audytorem bezpieczenstwa multi-tenant SaaS.
Przeanalizuj architekture: [modele + endpointy + uprawnienia].
Sprawdz: tenant isolation, IDOR, normalizacja, indeksy, FK, skalowalosc, race conditions.
Raport: KRYTYCZNE / POWAZNE / DROBNE / OCENA. Max 500 slow, po polsku."
```

### Krok 2: Fan-in — Analiza i poprawki

1. Przeczytaj output Gemini z `dev/gemini/` (jesli uruchomiony)
2. Porownaj wyniki — zgodnosc = KRYTYCZNE, napraw natychmiast
3. Zaktualizuj plan architektoniczny
4. Zapisz `AUDIT_RESULTS`

**Jesli KRYTYCZNE problemy:** Napraw w architekturze PRZED Faza 4.

---

## Faza 4: Review + Auto-Switch

**CEL:** Prezentacja planu i automatyczne przejscie do editor mode.

1. **Podsumowanie audytu** (ile problemow, jakie poprawki, consensus self/gemini)
2. **Krotkie podsumowanie planu** (max 15 linii):
   - Co zostanie zbudowane (2-3 zdania)
   - Modele Prisma, liczba endpointow/stron, fazy implementacji
   - Wynik audytu + poprawki
3. **`ExitPlanMode`** NATYCHMIAST — NIE pytaj usera o akceptacje

**WYJATKI:** Audyt FAIL → zapytaj usera. Fundamentalna decyzja architektoniczna → zapytaj usera.

---

## Faza 5: Setup (po akceptacji)

**CEL:** Branch, pliki dokumentacji, pamiec Sereny, instrukcje implementacji.

### 1. Git branch + folder

```bash
git checkout -b feature/[nazwa-feature]
mkdir -p dev/active/[nazwa]
```

### 2. Deleguj generowanie plikow do Sonneta

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 5
prompt: |
  Wygeneruj 4 pliki markdown. Uzyj Write tool.

  PLAN: [pelny wynik Architecture]
  RESEARCH: [streszczenie max 300 slow]
  DISCOVERY: [max 200 slow]
  AUDIT: [wyniki audytu]

  1. `dev/active/[nazwa]/[nazwa]-plan.md` — Pelny plan architektoniczny
  2. `dev/active/[nazwa]/[nazwa]-kontekst.md` — Research i decyzje
  3. `dev/active/[nazwa]/[nazwa]-zadania.md` — Checklist po fazach:

     **Atomizuj kazde zadanie do 2-5 minut!**
     Kazda faza jako sekcja z `- [ ]`. Kazdy checkbox = 1 operacja, max 5 min, testowalne.

     Standardowy format:
     - Faza 1: schema + migration + backend CRUD
     - Faza 2: validation + frontend pages
     - Faza 3+: wg potrzeb
     - Ostatnia: Dokumentacja w docs/
     Dodaj `- [ ] **User: pnpm db:migrate:deploy && pnpm db:generate**` po migration.
     Dodaj `- [ ] **User: npx tsx libs/prisma/db/seed-[feature]-permissions.ts**` po permissions seed.

  4. `dev/active/[nazwa]/[nazwa]-audit.md` — Raport audytu
```

### 3. Zapisz architekture do pamieci Sereny (rownolegle z pkt 2!)

```
mcp__plugin_serena_serena__write_memory(
  memory_file_name: "[feature]_architecture",
  content: "# [Feature] Architecture\n## Models: [...]\n## Endpoints: [...]\n## Status: PLANNED"
)
```

### 4. Git commit

```bash
git add dev/active/[nazwa]/
git commit -m "docs: inicjalizacja planu /ultra dla [nazwa]"
```

### 5. Zapytaj o tryb implementacji

Uzyj AskUserQuestion z 1 pytaniem:

```
question: "Jak chcesz kontynuowac implementacje?"
options:
  - label: "Ralph Loop (autonomicznie)"
    description: "Claude pracuje sam az do zakonczenia. Wykonuje WSZYSTKO samodzielnie — czyta, edytuje, uruchamia komendy, sprawdza. Ty podejmujesz tylko decyzje architektoniczne i akcje DB."
  - label: "Interaktywnie (/dev-docs-execute)"
    description: "Klasyczny tryb — faza po fazie z pelna kontrola."
  - label: "Pozniej"
    description: "Tylko zapisz plan, implementacja innym razem."
```

### 6. Output koncowy

Jesli user wybral **Ralph Loop** → na koncu outputu dodaj gotowa komende do skopiowania.
Jesli user wybral **Interaktywnie** → standardowy output z `/dev-docs-execute`.
Jesli user wybral **Pozniej** → tylko output z plikami.

```
## /ultra - Plan gotowy!

**Feature:** [nazwa]
**Branch:** feature/[nazwa]
**Typ:** [CRUD/STANDARD/COMPLEX]
**Audyt:** [PASS / PASS WITH CONDITIONS] — [X kryt., Y pow., Z drob.]

### Podsumowanie
[2-3 zdania]

### Pliki
  dev/active/[nazwa]/
    [nazwa]-plan.md, [nazwa]-kontekst.md, [nazwa]-zadania.md, [nazwa]-audit.md

### Fazy implementacji
1-N. [lista faz]

### Statystyki agentow
- Rownolegle uruchomionych: [ile]
- Klasyfikacja: [CRUD/STANDARD/COMPLEX]
- Agent 2 (frontend): [uruchomiony/pominiety]
- Agent 3 (web): [uruchomiony/pominiety]
- Gemini audit: [uruchomiony/pominiety]
- Pamieci cache: [ile skipnieto]
```

**Jesli Ralph Loop:**

```
### Tryb: Ralph Loop (autonomiczny)

Uruchom ponizej — Claude zaimplementuje WSZYSTKIE fazy z planu:

/ralph-loop "Zaimplementuj feature [nazwa] wg planu dev/active/[nazwa]/[nazwa]-zadania.md.
Pracuj faza po fazie. Po kazdej fazie: git commit. Czytaj plan z pliku.
ZASADY: importy z @z, @prisma. Response format {data, meta}. Standardy z CLAUDE.md.
AUTONOMIA: Wykonuj WSZYSTKIE operacje samodzielnie bez pytania o pozwolenie.
Czytaj pliki, edytuj, uruchamiaj komendy, sprawdzaj strony — po prostu ROB.
Uzywaj Serena MCP do nawigacji po kodzie (OBOWIAZKOWE). Uzywaj Context7 MCP do docs frameworkow.
WAZNE: Po migration SQL — ZATRZYMAJ SIE i napisz 'USER_ACTION: pnpm db:migrate:deploy && pnpm db:generate'.
Po seed — ZATRZYMAJ SIE i napisz 'USER_ACTION: npx tsx libs/prisma/db/seed-[feature]-permissions.ts'.
Output <promise>ULTRA_DONE</promise> gdy WSZYSTKIE fazy ukonczone." --max-iterations 30 --completion-promise "ULTRA_DONE"
```

**Jesli Interaktywnie:**

```
### Nastepny krok
Uruchom: `/dev-docs-execute dev/active/[nazwa]`
```

---

## Faza 6: Documentation (po zakonczeniu implementacji)

**CEL:** Dokumentacja w `docs/` po zakonczeniu implementacji.
**KIEDY:** PO `/dev-docs-execute`, jako ostatni krok.

### 1. Deleguj do Sonneta

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 8
prompt: |
  Utworz dokumentacje dla feature'a [nazwa] w genealog. Uzyj Write i Edit tools.
  Wzorce: docs/media-library/MEDIA_LIBRARY_SYSTEM.md, docs/equipment/EQUIPMENT_MANAGEMENT_PLAN.md.

  ## A) `docs/[nazwa]/[NAZWA]_SYSTEM.md`
  Struktura: Naglowek, Spis tresci, Model Danych, Backend API, Uprawnienia, Frontend,
  Cache, Kluczowe pliki, Przyszle fazy, Edge Cases, Wyniki Audytu.

  ## B) Aktualizacja `docs/README.md`
  Dodaj sekcje + wpis w Spis Tresci + link w Quick Links.

  Pisz po polsku (ASCII). Dokumentuj TYLKO zaimplementowane.
```

### 2. Aktualizuj MEMORY.md (5-8 linii o nowym feature)

### 3. Git commit

```bash
git add docs/[nazwa]/
git commit -m "docs: dokumentacja systemu [nazwa]"
```

---

## Faza 7: Lessons Learned (OPCJONALNA)

**KIEDY:** Feature nietypowy, napotkano problemy, odkryto nowe patterns.

```
mcp__plugin_serena_serena__write_memory(
  memory_file_name: "[feature]_lessons_learned",
  content: |
    # [Feature] - Lessons Learned (YYYY-MM-DD)
    ## Co dzialalo dobrze
    ## Co bylo trudne
    ## Patterns dla przyszlych features
    ## Status: IMPLEMENTED
)
```

---

## ESLint — DWUWARSTWOWY (hook + manualna weryfikacja)

### Warstwa 1: Auto-fix hook (PostToolUse)

ESLint `--fix` jest uruchamiany **automatycznie** po kazdym Edit/Write.
Naprawia: unused imports, import ordering, consistent-type-imports, self-closing JSX.

### Warstwa 2: Manualna weryfikacja PRZED KAZDYM COMMITEM

**OBOWIAZKOWE** — uruchom PRZED `git commit`:

```bash
# A) ESLint fix na wszystkich zmienionych plikach
CHANGED=$(git diff --name-only --diff-filter=ACMR HEAD | grep -E '\.(ts|tsx)$' | head -30)
[ -n "$CHANGED" ] && npx eslint --fix --no-error-on-unmatched-pattern $CHANGED 2>/dev/null

# B) Sprawdz NIENAPRAWIALNE bledy
[ -n "$CHANGED" ] && npx eslint --no-error-on-unmatched-pattern $CHANGED 2>&1 | grep -E "no-unused-vars|is defined but never used|is assigned .* but never used" | head -15
```

**Typowe bledy do recznej naprawy:**

- `'X' is defined but never used` → usun import/deklaracje X
- `'setX' is assigned a value but never used` → usun useState lub dodaj uzycie setX
- `'X' is assigned a value but never used` → usun const X = ... lub uzyj X

### Warstwa 3: Full lint (przed OSTATNIM commitem feature'a)

```bash
nx lint backend && nx lint frontend && nx lint admin-panel
```

---

## Troubleshooting

### /ultra nie uruchamia Sereny

ToolSearch("select:mcp**plugin_serena_serena**activate_project"), potem activate_project.

### Agenty researchu trwaja >5 min

Przerwij, zmniejsz scope, uzyj pamieci zamiast researchu.

### Gemini audit nie dziala

SKIP Gemini, uzyj tylko self-auditu.

### Plan mode nie aktywny

Wywolaj EnterPlanMode recznie.

### Istniejacy plan w dev/active/ koliduje

Zapytaj usera: kontynuowac czy archiwizowac do dev/completed/.

### Agent nie uzywa Sereny

Sprawdz czy prompt agenta zawiera OBOWIAZKOWE instrukcje aktywacji Sereny.
Jesli agent uzywa Read zamiast Sereny — to BUG w prompcie, napraw.

---

## Changelog v2 → v3

| Zmiana                                 | Efekt                                           |
| -------------------------------------- | ----------------------------------------------- |
| Rownolegle agenty: 2 → 3-5 (dynamic)   | Fan-out/fan-in pattern, ~40% szybszy research   |
| Discovery: 2 rundy → ile potrzeba      | Pelny obraz wymagan, pole "Uwagi" przy >5 pytan |
| Research: split na 2 rownolegle agenty | Backend+Prisma ∥ Frontend+Validation — szybciej |
| Autonomiczny tryb pracy (auto-accept)  | Brak pytania o pozwolenie w ramach projektu     |
| Ralph Loop: autonomia w prompcie       | Komendy, odczyt, edycja — bez klikania          |
| OBOWIAZKOWE Serena MCP                 | Wymuszone w kazdym agencie, nie opcjonalne      |
| OBOWIAZKOWE MCP-first                  | Context7 > WebSearch, Serena > Read             |
| Context7 w Bootstrap                   | Ladowany razem z Serena w Fazie 0               |
| Zasady rownoleglosci                   | Worktree isolation, file ownership, fan-in sync |
| Sekcja AUTONOMICZNY TRYB PRACY         | Jasne reguly co robic bez pytania               |
