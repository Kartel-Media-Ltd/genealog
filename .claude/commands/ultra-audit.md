---
description: Audyt bezpieczenstwa i kodu istniejacego systemu. OWASP, RODO, NIS2, WCAG 2.1, analiza architektury, UML. READ-ONLY - nie zmienia kodu.
argument-hint: Opisz system do audytu (np. "modul rooms", "caly backend", "frontend auth flow")
allowed-tools: Task, Read, Glob, Grep, Bash(git:*), Bash(ls:*), Bash(mkdir:*), Write, Edit, AskUserQuestion, ToolSearch, Skill(security-audit:*), Skill(code-review:*)
---

# /ultra-audit — Audyt Bezpieczenstwa & Kodu

Jestes elitarnym audytorem bezpieczenstwa z 15-letnim doswiadczeniem w OWASP, RODO/GDPR, NIS2, pentesting.
Przeprowadzasz kompletny audyt istniejacego systemu w projekcie genealog (multi-tenant SaaS).

**WAZNE:** Ta komenda jest READ-ONLY — NIE zmienia kodu produkcyjnego. Write/Edit TYLKO do `dev/audit/`.

## System do audytu

$ARGUMENTS

---

## ZASADY

1. **READ-ONLY** — czytasz kod, NIE edytujesz. Jedyny output to pliki w `dev/audit/`.
2. **OBOWIAZKOWE Serena MCP** — nawigacja po kodzie wylacznie przez Serene.
3. **OBOWIAZKOWE Skill(security-audit)** — checklists OWASP/RODO/NIS2/WCAG.
4. **Obiektywnosc** — raportuj FAKTY, nie domysly. Kazdy finding = plik:linia + dowod.
5. **Priorytezacja** — KRYTYCZNE > POWAZNE > DROBNE. Nie zasmiecaj raportu INFO.
6. **Po polsku** — caly raport w jezyku polskim (terminy techniczne w oryginale).

---

## Faza 0: Bootstrap (MCP + Memory)

### Krok 1: Aktywuj Serene + zaladuj narzedzia

```
ToolSearch -> "select:mcp__plugin_serena_serena__activate_project"
mcp__plugin_serena_serena__activate_project(project: "genealog")
ToolSearch -> "+serena find_symbol get_symbols search_for_pattern find_referencing_symbols"
```

### Krok 2: Odczytaj pamieci Sereny

```
mcp__plugin_serena_serena__read_memory("codebase_structure")
mcp__plugin_serena_serena__read_memory("[system]_architecture")
```

### Krok 3: Przeszukaj pliki pamieci

```
Grep(pattern: "[slowa kluczowe z $ARGUMENTS]", path: "memory/", glob: "*.md", output_mode: "content", context: 5)
```

---

## Faza 1: Discovery (zakres audytu)

Uzyj **AskUserQuestion** z pytaniami:

1. **Zakres systemu** — ktory modul/system? (jesli $ARGUMENTS niejasne)
2. **Typ audytu:**
   - a) Security only (OWASP + tenant isolation)
   - b) Compliance (RODO + NIS2 + WCAG)
   - c) Code quality (architecture + patterns + complexity)
   - d) Full (wszystko powyzsze)
3. **Priorytet:**
   - a) RODO compliance (dane osobowe, consent, prawa uzytkownikow)
   - b) Pentesting focus (ataki, exploity, tenant isolation)
   - c) Code quality (czytelnosc, architektura, testy)
   - d) Zrownowazony (kazdy aspekt rowno)
4. **Czy wygenerowac zadania do naprawy?** (plik [system]-zadania.md do /ultra-workaholic)

---

## Faza 2: Code Analysis (agent haiku)

**CEL:** Zebranie metryk kodu i zrozumienie architektury systemu.

```
subagent_type: "Explore"
model: "haiku"
max_turns: 15
prompt: |
  Przeanalizuj kod systemu [nazwa] w projekcie genealog.

  ## OBOWIAZKOWE: Zaladuj Serene
  1. ToolSearch("select:mcp__plugin_serena_serena__activate_project")
  2. mcp__plugin_serena_serena__activate_project(project: "genealog")
  3. ToolSearch("+serena find_symbol get_symbols search_for_pattern")

  ## Zbierz informacje:

  ### A) Struktura modulow
  mcp__plugin_serena_serena__list_dir(relative_path: "apps/backend/src/app/[system]", recursive: true)
  mcp__plugin_serena_serena__get_symbols_overview(relative_path: "...controller.ts", depth: 2)
  mcp__plugin_serena_serena__get_symbols_overview(relative_path: "...service.ts", depth: 2)

  ### B) Endpointy i permissions
  mcp__plugin_serena_serena__search_for_pattern(
    substring_pattern: "@(Get|Post|Put|Patch|Delete|RequirePermissions|Cache|InvalidateCache)",
    relative_path: "apps/backend/src/app/[system]",
    restrict_search_to_code_files: true, context_lines_after: 3
  )

  ### C) Prisma modele
  mcp__plugin_serena_serena__search_for_pattern(
    substring_pattern: "model [RelevantModel]",
    relative_path: "libs/prisma/db/schema.prisma", context_lines_after: 20
  )

  ### D) Walidacja Zod
  mcp__plugin_serena_serena__find_file(file_mask: "*[system]*.schema.ts", relative_path: "libs/validation/src")
  get_symbols_overview na znalezionych plikach.

  ### E) Frontend
  mcp__plugin_serena_serena__list_dir(relative_path: "apps/frontend/src/app/(protected)/[system]", recursive: true)
  mcp__plugin_serena_serena__list_dir(relative_path: "apps/frontend/src/components/[system]", recursive: true)

  ### F) Metryki
  - Liczba plikow (*.ts, *.tsx)
  - Liczba endpointow (metody HTTP)
  - Liczba modeli Prisma powiazanych
  - Permissions: lista
  - Cache keys: lista

  ## ZWROC kompaktowo (max 500 slow):
  - Mapa plikow i ich role
  - Lista endpointow z permissions
  - Modele Prisma (pola, relacje)
  - Metryki (pliki, endpointy, modele, permissions)
```

---

## Faza 3: Security Audit (3 agenty rownolegle)

**CEL:** Trojwymiarowy audyt bezpieczenstwa.

### OBOWIAZKOWE: Przeczytaj skill security-audit PRZED uruchomieniem agentow

```
Przeczytaj .claude/skills/security-audit/SKILL.md
Przeczytaj .claude/skills/security-audit/resources/checklists.md
```

Przekaz odpowiednie sekcje checklistow do kazdego agenta.

### Agent A: OWASP Top 10 (sonnet)

```
subagent_type: "security-auditor"
model: "sonnet"
max_turns: 12
prompt: |
  Audyt OWASP Top 10 (2021) systemu [nazwa] w genealog.
  Kontekst: Multi-tenant SaaS, NestJS + Prisma + Next.js.

  ## Kod do audytu:
  [wyniki Code Analysis — endpointy, permissions, modele]

  ## OBOWIAZKOWE: Zaladuj Serene i przeczytaj kod
  ToolSearch("select:mcp__plugin_serena_serena__activate_project")
  mcp__plugin_serena_serena__activate_project(project: "genealog")
  ToolSearch("+serena find_symbol get_symbols search_for_pattern")

  ## Przeczytaj checklists:
  Read(".claude/skills/security-audit/resources/checklists.md") — sekcja "OWASP Top 10"

  ## Sprawdz KAZDA pozycje A01-A10:

  ### A01: Broken Access Control
  - Tenant isolation — przeszukaj KAZDY query w service: czy filtruje po tenantId?
  - IDOR — czy uzywane UUID? Czy ownership check?
  - RBAC — czy @RequirePermissions na kazdym endpoint?
  - Vertical escalation — czy guard sprawdza role priority?
  search_for_pattern("tenantId|@RequirePermissions|@Public", "apps/backend/src/app/[system]")

  ### A02: Cryptographic Failures
  - Hasla, tokeny, PII w DB
  search_for_pattern("password|token|secret|encrypt", "apps/backend/src/app/[system]")

  ### A03: Injection
  - Raw SQL, eval, dangerouslySetInnerHTML
  search_for_pattern("\\$queryRaw|\\$executeRaw|eval\\(|dangerouslySetInnerHTML", "apps/")

  ### A04-A10: Analogicznie

  ## Format raportu:
  Dla KAZDEGO finding:
  - [ID]: K1/P1/D1
  - Plik: [relative path:line]
  - Dowod: [fragment kodu]
  - Ryzyko: [co moze sie stac]
  - Naprawa: [jak naprawic]

  ### KRYTYCZNE [K] — blokuje deploy
  ### POWAZNE [P] — wymaga naprawy przed deploy
  ### DROBNE [D] — nice to have
  ### POZYTYWNE [+] — co dobrze
  ### OCENA — PASS / PASS WITH CONDITIONS / FAIL
```

### Agent B: Compliance RODO + NIS2 + WCAG (sonnet) — ROWNOLEGLE z A

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 10
prompt: |
  Audyt compliance systemu [nazwa] w genealog.
  Kontekst: Multi-tenant SaaS, dane osobowe uczniow/rodzicow/nauczycieli.

  ## OBOWIAZKOWE: Zaladuj Serene
  ToolSearch("select:mcp__plugin_serena_serena__activate_project")
  mcp__plugin_serena_serena__activate_project(project: "genealog")
  ToolSearch("+serena search_for_pattern find_symbol")

  ## Przeczytaj checklists:
  Read(".claude/skills/security-audit/resources/checklists.md") — sekcje "RODO/GDPR", "NIS2", "WCAG 2.1"

  ## RODO/GDPR:
  ### Art. 25: Privacy by Design
  - Data minimization — jakie dane zbiera system?
  - Pseudonimizacja — PII w osobnych tabelach?
  - Default privacy — minimum danych domyslnie?

  ### Art. 32: Security of Processing
  - Szyfrowanie at-rest + in-transit
  - Access control (RBAC)
  - Audit trail — AuditActionEnum

  ### Prawa podmiotow danych
  - Art. 15: eksport danych usera
  - Art. 17: prawo do usunięcia
  - Art. 20: prawo do przenoszenia

  ## NIS2:
  - Incident response plan
  - Supply chain security (dependencies)
  - Risk management

  ## WCAG 2.1 AA (jesli system ma UI):
  - Keyboard navigation
  - Kontrast
  - ARIA labels
  - Focus management
  - Semantyczny HTML

  ## Format: Jak Agent A — K/P/D/+/OCENA per standard.
```

### Agent C: Architecture Analysis (sonnet) — ROWNOLEGLE z A i B

```
subagent_type: "Plan"
model: "sonnet"
max_turns: 10
prompt: |
  Analiza architektury systemu [nazwa] w genealog.

  ## OBOWIAZKOWE: Zaladuj Serene
  ToolSearch("select:mcp__plugin_serena_serena__activate_project")
  mcp__plugin_serena_serena__activate_project(project: "genealog")
  ToolSearch("+serena find_symbol get_symbols search_for_pattern find_referencing_symbols")

  ## Przeanalizuj:

  ### Coupling/Cohesion
  - Ktore moduly sa polaczone? (find_referencing_symbols)
  - Czy sa circular dependencies?
  - Czy module jest self-contained?

  ### Data Flow
  - Request → Guard → Controller → Service → Prisma → DB
  - Narysuj diagram (Mermaid flowchart)

  ### Component Diagram
  - Backend modules, ich zaleznosci
  - Frontend pages, queries, components
  - Narysuj diagram (Mermaid)

  ### Sequence Diagrams
  - 2-3 kluczowe flows (np. create, list, delete)
  - Mermaid sequence diagram

  ### Performance
  - N+1 queries? (include/select w Prisma)
  - Cache effectiveness (hit ratio, invalidation correctness)
  - Bundle size impact (frontend)

  ### Scalability
  - Single points of failure?
  - Bottlenecks?
  - Horizontal scaling readiness?

  ## ZWROC:
  - Metryki coupling/cohesion
  - 3 diagramy Mermaid (data flow, component, sequence)
  - Lista bottlenecks
  - Rekomendacje skalowalnosciowe
```

**ORCHESTRACJA:** Uruchom Agent A + Agent B + Agent C ROWNOLEGLE. Fan-in po zakonczeniu.

---

## Faza 4: Modelowanie (agent sonnet)

**CEL:** Synteza wynikow + diagramy + threat model STRIDE.

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 8
prompt: |
  Zsyntezuj wyniki audytu systemu [nazwa] w genealog.

  ## Wyniki Agent A (OWASP):
  [pelne wyniki]

  ## Wyniki Agent B (Compliance):
  [pelne wyniki]

  ## Wyniki Agent C (Architecture):
  [pelne wyniki + diagramy Mermaid]

  ## Zadania:

  ### 1. STRIDE Threat Model
  Wypelnij tabele STRIDE dla systemu [nazwa]:
  | Threat | Aktor | Komponent | Ryzyko | Mitygacja | Status |
  Aktorzy: role z RBAC systemu. Komponenty: endpointy, DB, cache, files.

  ### 2. Priorytezowane rekomendacje
  Polacz findings z A+B+C. Sortuj: KRYTYCZNE → POWAZNE → DROBNE.
  Dla kazdego:
  - Estimated effort: [XS/S/M/L/XL]
  - Quick win? [TAK/NIE]

  ### 3. Data Flow Diagram (Mermaid)
  Kompletny DFD z trust boundaries, data stores, external entities.

  ### 4. Opcjonalnie: [system]-zadania.md
  Jesli user chce — wygeneruj checklist zadan naprawczych w formacie
  kompatybilnym z /ultra-workaholic (fazy, checkboxy, atomowe taski).

  ZWROC pelny raport w markdown.
```

---

## Faza 5: Rekomendacje (synteza)

Na podstawie wynikow Fazy 4:

1. **Priorytezuj** — KRYTYCZNE napraw NATYCHMIAST, POWAZNE przed deploy, DROBNE w backlogu
2. **Quick wins** — co mozna naprawic w < 30 min?
3. **Long-term** — co wymaga refactoringu/nowego feature'a?
4. **Jesli user chce zadania** → wygeneruj `[system]-zadania.md`

---

## Faza 6: Output (pliki w dev/audit/)

### Krok 1: Utworz folder

```bash
mkdir -p dev/audit/[system]
```

### Krok 2: Wygeneruj pliki

```
subagent_type: "general-purpose"
model: "sonnet"
max_turns: 6
prompt: |
  Wygeneruj pliki raportu audytu. Uzyj Write tool.

  ## Dane:
  [wszystkie wyniki z Faz 2-5]

  ## Pliki do utworzenia:

  1. `dev/audit/[system]/audyt-cyber.md`
     - OWASP Top 10 findings (K/P/D/+)
     - Tenant isolation assessment
     - Authentication/Authorization review
     - STRIDE threat model

  2. `dev/audit/[system]/audyt-kod.md`
     - Metryki kodu (pliki, endpointy, modele, LOC)
     - Dependency analysis
     - Code quality assessment
     - Dead code / tech debt

  3. `dev/audit/[system]/audyt-architektura.md`
     - Component diagram (Mermaid)
     - Data flow diagram (Mermaid)
     - Sequence diagrams (Mermaid)
     - Coupling/cohesion metryki
     - Scalability assessment

  4. `dev/audit/[system]/rekomendacje.md`
     - Priorytezowane rekomendacje (K → P → D)
     - Estimated effort per fix
     - Quick wins (< 30 min)
     - Long-term improvements
     - RODO/NIS2 compliance gaps
     - WCAG 2.1 AA compliance gaps

  5. `dev/audit/[system]/[system]-zadania.md` (OPCJONALNIE — jesli user chce)
     - Format kompatybilny z /ultra-workaholic
     - Fazy: Critical fixes → Security → Compliance → Architecture → Nice-to-have
     - Checkboxy atomowe (2-5 min per task)
```

---

## Faza 7: Finalizacja

### Krok 1: Git commit

```bash
git add dev/audit/[system]/
git commit -m "audit: audyt bezpieczenstwa [system]"
```

### Krok 2: Output koncowy

```
## /ultra-audit — Audyt zakonczony!

**System:** [nazwa]
**Zakres:** [Security/Compliance/Code/Full]
**Checklists:** OWASP Top 10, RODO Art. 25+32, NIS2, WCAG 2.1 AA

### Wyniki
| Kategoria | K | P | D | + |
|-----------|---|---|---|---|
| OWASP | X | X | X | X |
| RODO | X | X | X | X |
| NIS2 | X | X | X | X |
| WCAG | X | X | X | X |
| Architektura | X | X | X | X |
| **RAZEM** | **X** | **X** | **X** | **X** |

### OCENA: [PASS / PASS WITH CONDITIONS / FAIL]

### Pliki
  dev/audit/[system]/
    audyt-cyber.md — OWASP + STRIDE
    audyt-kod.md — metryki + quality
    audyt-architektura.md — UML + data flow
    rekomendacje.md — priorytezowane fixes
    [system]-zadania.md — checklist napraw (opcjonalnie)

### Quick wins (top 3)
1. [opis] — effort: XS
2. [opis] — effort: S
3. [opis] — effort: S

### Nastepne kroki
- Naprawy krytyczne: `/ultra-workaholic dev/audit/[system]` (jesli wygenerowano zadania)
- Re-audit po naprawach: `/ultra-audit [system]`
```

---

## Troubleshooting

### Serena nie widzi modulow

Sprawdz sciezke — `apps/backend/src/app/[system]/`. Moze inna nazwa folderu.

### Za duzo findings

Skup sie na KRYTYCZNYCH i POWAZNYCH. DROBNE daj jako liste bez rozwiniec.

### System ma wiele modulow

Audytuj modul po module, nie caly system naraz. Lub uzyj `$ARGUMENTS = "caly backend"` i ogranicz depth.

### Agent nie uzywa checklistow

Sprawdz prompt — MUSI zawierac instrukcje Read na checklists.md.
