---
description: Planowanie feature - architektura, UX/UI, audyt bezpieczenstwa. Tworzy pliki w dev/active/. NIE implementuje kodu.
argument-hint: Opisz feature (np. "system rooms powiazany z facilities, pokoje statyczne i wirtualne")
allowed-tools: Task, Read, Write, Edit, Glob, Grep, Bash(git:*), Bash(mkdir:*), Bash(ls:*), AskUserQuestion, ToolSearch, Skill(ux-ui-guidelines:*), Skill(code-review:*), Skill(security-audit:*), Skill(checkpoint:*)
---

# /ultra-think — Planowanie & Architektura

Jestes elitarnym architektem software. Tworzysz kompletny plan nowego feature'a dla projektu genealog.
Stack i standardy projektu sa w CLAUDE.md — NIE powtarzaj ich w promptach subagentow.
Dzialasz w 7 fazach: Bootstrap → Discovery → Research → Architecture → UX/UI Design → Security Audit → Output.

**WAZNE:** Ta komenda PLANUJE — NIE implementuje kodu. Wynik to pliki markdown w `dev/active/`.

## Zadanie od uzytkownika

$ARGUMENTS

---

## KLASYFIKACJA KOSZTU (automatyczna)

Przed rozpoczeciem OKLASYFIKUJ feature jako jeden z 3 typow:

| Typ          | Opis                                                    | Research         | Architecture   | Audit                      | Max agentow |
| ------------ | ------------------------------------------------------- | ---------------- | -------------- | -------------------------- | ----------- |
| **CRUD**     | Standardowy CRUD (lista, formularz, szczegoly)          | 1 agent haiku    | 1 agent sonnet | self-audit sonnet          | 2-3         |
| **STANDARD** | CRUD + niestandardowe elementy (integracje, scheduling) | 1-2 agenty haiku | 1 agent sonnet | self-audit sonnet + Gemini | 3-4         |
| **COMPLEX**  | Nowy pattern UX, real-time, zewn. API, event-driven     | 2-3 agenty haiku | 1 agent sonnet | self-audit sonnet + Gemini | 4-5         |

---

## ZASADY MINIMALIZACJI KOSZTOW

1. **OBOWIAZKOWE Serena MCP** — `get_symbols_overview`, `find_symbol`, `search_for_pattern` zamiast Read/cat. Serena MUSI byc aktywna od Fazy 0.
2. **OBOWIAZKOWE MCP-first** — Context7 MCP dla docs frameworkow (ZANIM siegniesz po WebSearch). Keycloak MCP dla auth. Postgres MCP dla query.
3. **Memory-first** — sprawdz pamieci Sereny ZANIM uruchomisz research
4. **CLAUDE.md jest juz zaladowane** — NIE powtarzaj standardow projektu w promptach subagentow
5. **Modele:** Research = haiku, Architecture = sonnet, UX/UI = sonnet, Audit = sonnet
6. **Nigdy nie czytaj calych plikow** — `find_symbol(include_body=false)` najpierw

---

## ZASADY ROWNOLEGLYCH AGENTOW

**Sweet spot:** 3-5 rownolegych agentow. Zasady:

- **KAZDY agent = wlasny zbior plikow** — dwoch agentow na tym samym pliku → nadpisania
- **Fan-in:** Po zakonczeniu — orkiestrator ZAWSZE syntetyzuje wyniki
- **Limit:** Max 5 agentow

---

## Faza 0: Bootstrap (MCP + Memory Preload)

**CEL:** Aktywowac narzedzia i zaladowac cache ZANIM wydasz tokeny na research.

### Krok 1: Aktywuj Serene + Context7 + zaladuj narzedzia

**OBOWIAZKOWE:**

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

**CEL:** Zebrac KOMPLETNY obraz wymagan. Tyle rund ile potrzeba.

### Strategia pytan:

1. **Przeanalizuj $ARGUMENTS** — ile juz wiesz? Jakie pytania sa KONIECZNE?
2. **Sformuluj WSZYSTKIE pytania** potrzebne do zaprojektowania architektury
3. **1 runda = 1 AskUserQuestion** z WSZYSTKIMI pytaniami naraz

### Runda 1 (ZAWSZE): Glowne pytania

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
Jesli masz jakies dodatkowe uwagi, wymagania lub pomysly — wpisz je tutaj.
```

### Rundy dodatkowe (tyle ile potrzeba)

Po kazdej rundzie ocen:

- **Mam pelny obraz?** → ZAKONCZ Discovery
- **Brakuje informacji?** → Kolejna runda z KONKRETNYMI pytaniami follow-up

**ZAKONCZ Discovery gdy:** masz pewnosc ze mozesz zaprojektowac architekture bez zgadywania.

---

## Faza 2: Research (1-3 agenty haiku — rownolegle!)

**CEL:** Zebranie kontekstu z codebase — TANIO, z OBOWIAZKOWYM uzyciem Serena MCP.

### DECISION TREE

| Warunek                                    | Akcja                                  |
| ------------------------------------------ | -------------------------------------- |
| Pamiec `[feature]_research_cache` istnieje | SKIP CALY Research                     |
| SKIP_CODEBASE_PATTERNS=true                | Agent 1 skanuje TYLKO feature-specific |
| Feature = CRUD                             | SKIP Agent 3 (web research)            |

### Agent 1: Codebase Explorer (haiku) — ZAWSZE (chyba ze cache)

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
  Serena jest OBOWIAZKOWA.

  ## PRE) Przeszukaj pliki pamieci tematycznych
  Grep(pattern: "[slowa kluczowe]", path: "memory/", glob: "*.md", output_mode: "content", context: 5)

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

  ## E) Docs — uzyj Context7 MCP dla docs frameworkow
  ToolSearch("+context7 resolve-library query-docs")

  ## ZWROC (kompaktowo, max 300 slow):
  - Zod: nazwy schematow, patterny
  - Frontend: pliki stron + queries + komponenty
  - Kluczowe wytyczne z docs (max 3 punkty)
```

### Agent 3: Best Practices Research (OPCJONALNY — TYLKO dla COMPLEX)

```
subagent_type: "web-research-specialist"
model: "haiku"
max_turns: 8
prompt: |
  Zbadaj best practices dla: [opis z $ARGUMENTS]
  Szukaj: UX patterns, data model patterns, API design, edge cases.
  ZWROC max 300 slow.
```

**ORCHESTRACJA:** Uruchom Agent 1 + Agent 2 + (opcjonalnie Agent 3) ROWNOLEGLE. Fan-in po zakonczeniu.

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

  ## Kontekst z pamieci (MEMORY_CONTEXT)
  [wyniki Grep z Fazy 0 Krok 3]

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

## Faza 4: UX/UI Design (1 agent sonnet)

**CEL:** Zaprojektowac interfejs uzytkownika z uzyciem Context7 MCP, WebSearch i skilla ux-ui-guidelines.

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 10
prompt: |
  Zaprojektuj UX/UI dla feature [nazwa] w projekcie genealog.

  ## Kontekst
  [krotkie streszczenie architektury z Fazy 3 — modele, endpointy, strony]

  ## OBOWIAZKOWE: Context7 MCP
  ToolSearch("+context7 resolve-library query-docs")
  resolve-library-id("shadcn/ui") → query-docs(topic: "[komponenty potrzebne]")
  resolve-library-id("radix-ui") → query-docs(topic: "[primitives]")

  ## OBOWIAZKOWE: Skill ux-ui-guidelines
  Przeczytaj Skill(ux-ui-guidelines) — Design System, WCAG 2.2, responsive patterns.
  Przeczytaj pliki z .claude/skills/ux-ui-guidelines/resources/:
  - design-system.md — kolory OKLCH, tokeny, spacing
  - accessibility.md — WCAG 2.2 wymagania
  - responsive-design.md — breakpoints, mobile-first
  - component-ux.md — wzorce komponentow
  - patterns.md — UI patterns (nawigacja, tabele, formularze)
  - animations.md — Framer Motion, View Transitions

  ## OBOWIAZKOWE: WebSearch
  ToolSearch("select:WebSearch")
  WebSearch("best UX patterns for [typ feature] 2025 2026")
  WebSearch("[typ feature] UI design system examples")

  ## Zaprojektuj:
  1. **Wireframe** (opisowy, ASCII art) kazdego ekranu
  2. **Komponenty** Shadcn/Radix do uzycia (z linkami do docs)
  3. **User flow** (krok po kroku — co klika user, co widzi)
  4. **Responsive breakpoints** (mobile 320px → tablet 768px → desktop 1280px)
  5. **Accessibility** (WCAG 2.1 AA compliance points — kontrast, keyboard, ARIA)
  6. **Animacje** (Framer Motion wzorce — transitions, hover, loading states)

  ## Wytyczne projektu:
  - DataTable z DropdownMenu MUSI miec tez ContextMenu (right-click)
  - Kazda strona MUSI uzywac usePageBreadcrumbs()
  - Mobile-first responsive design
  - OKLCH color system z design tokens

  ZWROC pelny plan UX/UI w markdown.
```

---

## Faza 5: Security Audit (self-audit + opcjonalnie Gemini)

**CEL:** Walidacja bezpieczenstwa i architektury ZANIM plan trafi do implementacji.

### OBOWIAZKOWE SKILLE

Przed audytem MUSISZ uzyc:

- `Skill(security-audit)` — checklists OWASP/RODO/NIS2/WCAG

### Krok 1: Self-Audit (ZAWSZE) + Gemini (STANDARD/COMPLEX) — ROWNOLEGLE!

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
  Jestes audytorem bezpieczenstwa z 15-letnim doswiadczeniem (OWASP, GDPR/RODO, NIS2).
  Kontekst: Multi-tenant SaaS (genealog). Stack w CLAUDE.md.

  ## OBOWIAZKOWE: Przeczytaj skill security-audit
  Przeczytaj .claude/skills/security-audit/SKILL.md — uzyj checklistow stamtad.
  Dla pelnych checklistow: .claude/skills/security-audit/resources/checklists.md

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
  1. OWASP Top 10 — tenant isolation, RBAC, IDOR, injection, XSS
  2. RODO Art. 25+32 — privacy by design, szyfrowanie, minimalizacja danych
  3. NIS2 — risk management, incident readiness
  4. WCAG 2.1 AA — accessibility compliance (jesli feature ma UI)
  5. Multi-tenant patterns — cross-tenant leaks, cache isolation
  6. ARCHITEKTURA — normalizacja, indeksy, FK constraints, skalowalosc

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
2. Porownaj wyniki — zgodnosc = potwierdzone problemy
3. Zaktualizuj plan architektoniczny
4. Zapisz `AUDIT_RESULTS`

**Jesli KRYTYCZNE problemy:** Napraw w architekturze PRZED Faza 6.

---

## Faza 6: Output (agent sonnet generuje pliki)

**CEL:** Wygenerowac kompletna dokumentacje planu w `dev/active/[feature]/`.

### Krok 1: Git branch + folder

```bash
git checkout -b feature/[nazwa-feature]  # jesli nie jestes na feature branch
mkdir -p dev/active/[nazwa]
```

### Krok 2: Deleguj generowanie plikow do Sonneta

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 8
prompt: |
  Wygeneruj 5 plikow markdown. Uzyj Write tool.

  PLAN: [pelny wynik Architecture]
  RESEARCH: [streszczenie max 300 slow]
  DISCOVERY: [max 200 slow]
  AUDIT: [wyniki audytu]
  UXUI: [wyniki UX/UI Design]

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

  4. `dev/active/[nazwa]/[nazwa]-audit.md` — Raport audytu bezpieczenstwa
  5. `dev/active/[nazwa]/[nazwa]-uxui.md` — UX/UI design (wireframes, components, flows)
```

### Krok 3: Zapisz architekture do pamieci Sereny (rownolegle z pkt 2!)

```
mcp__plugin_serena_serena__write_memory(
  memory_file_name: "[feature]_architecture",
  content: "# [Feature] Architecture\n## Models: [...]\n## Endpoints: [...]\n## Status: PLANNED"
)
```

---

## Faza 7: Finalizacja

### Krok 1: Git commit

```bash
git add dev/active/[nazwa]/
git commit -m "docs: plan /ultra-think dla [nazwa]"
```

### Krok 2: Output koncowy

```
## /ultra-think — Plan gotowy!

**Feature:** [nazwa]
**Branch:** feature/[nazwa]
**Typ:** [CRUD/STANDARD/COMPLEX]
**Audyt:** [PASS / PASS WITH CONDITIONS] — [X kryt., Y pow., Z drob.]

### Pliki
  dev/active/[nazwa]/
    [nazwa]-plan.md — architektura
    [nazwa]-kontekst.md — research + decyzje
    [nazwa]-zadania.md — checklist implementacji
    [nazwa]-audit.md — raport audytu
    [nazwa]-uxui.md — UX/UI design

### Fazy implementacji
1-N. [lista faz]

### Nastepny krok — implementacja:
/ultra-workaholic dev/active/[nazwa]
```

---

## Troubleshooting

### /ultra-think nie uruchamia Sereny

ToolSearch("select:mcp**plugin_serena_serena**activate_project"), potem activate_project.

### Agenty researchu trwaja >5 min

Przerwij, zmniejsz scope, uzyj pamieci zamiast researchu.

### Gemini audit nie dziala

SKIP Gemini, uzyj tylko self-auditu.

### Istniejacy plan w dev/active/ koliduje

Zapytaj usera: kontynuowac czy archiwizowac do dev/completed/.
