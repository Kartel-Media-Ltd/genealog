---
description: Pre-load kontekstu Serena — modul/feature → symbole, zaleznosci, wzorce. Szybki start pracy.
argument-hint: Nazwa modulu/feature (np. "rooms", "equipment", "media-library")
allowed-tools: Task, Read, Grep, Glob, AskUserQuestion, ToolSearch
---

# /context-prime — Zaladuj kontekst feature'a

Inteligentnie ladujesz pelny kontekst istniejacego modulu/feature'a uzywajac Sereny.
Nie edytujesz niczego — tylko budujesz mental model do dalszej pracy.

## Zadanie

Zaladuj kontekst dla: $ARGUMENTS

## Kroki

### 1. Aktywuj Serene + zaladuj pamieci

```
ToolSearch("select:mcp__plugin_serena_serena__activate_project")
mcp__plugin_serena_serena__activate_project(project: "genealog")
ToolSearch("+serena find_symbol get_symbols search_for_pattern read_memory list_memories")
```

Sprawdz pamieci:

```
mcp__plugin_serena_serena__list_memories()
```

Przeczytaj pamieci pasujace do $ARGUMENTS (np. `rooms_architecture`, `rooms_lessons_learned`, `session_checkpoint`).

### 2. Backend — mapa modulu

Uruchom **rownolegle** (max 3):

**A) Controller — lista endpointow:**

```
mcp__plugin_serena_serena__get_symbols_overview(
  relative_path: "apps/backend/src/app/$ARGUMENTS/$ARGUMENTS.controller.ts",
  depth: 1
)
```

**B) Service — logika biznesowa:**

```
mcp__plugin_serena_serena__get_symbols_overview(
  relative_path: "apps/backend/src/app/$ARGUMENTS/$ARGUMENTS.service.ts",
  depth: 1
)
```

**C) Repository — query patterns:**

```
mcp__plugin_serena_serena__get_symbols_overview(
  relative_path: "apps/backend/src/app/$ARGUMENTS/$ARGUMENTS.repository.ts",
  depth: 1
)
```

Jesli plik nie istnieje (inna nazwa) → uzyj find_file:

```
mcp__plugin_serena_serena__find_file(file_mask: "*.controller.ts", relative_path: "apps/backend/src/app")
```

### 3. Prisma — modele i relacje

```
mcp__plugin_serena_serena__search_for_pattern(
  substring_pattern: "model .*$ARGUMENTS",
  relative_path: "libs/prisma/db/schema.prisma",
  context_lines_after: 25
)
```

### 4. Validation — schematy Zod

```
mcp__plugin_serena_serena__find_file(
  file_mask: "*.schema.ts",
  relative_path: "libs/validation/src/$ARGUMENTS"
)
```

Potem `get_symbols_overview` na znalezionym pliku.

### 5. Frontend — strony i komponenty

**Rownolegle:**

**A) Strona + layout:**

```
mcp__plugin_serena_serena__list_dir(
  relative_path: "apps/frontend/src/app/(protected)/$ARGUMENTS",
  recursive: true, skip_ignored_files: true
)
```

**B) Komponenty:**

```
mcp__plugin_serena_serena__list_dir(
  relative_path: "apps/frontend/src/components/$ARGUMENTS",
  recursive: true, skip_ignored_files: true
)
```

**C) React Query hooks:**

```
mcp__plugin_serena_serena__get_symbols_overview(
  relative_path: "apps/frontend/src/queries/$ARGUMENTS.queries.ts",
  depth: 1
)
```

**D) API routes:**

```
mcp__plugin_serena_serena__list_dir(
  relative_path: "apps/frontend/src/app/api/$ARGUMENTS",
  recursive: true, skip_ignored_files: true
)
```

### 6. Permissions + Cache

```
mcp__plugin_serena_serena__search_for_pattern(
  substring_pattern: "@RequirePermissions|@Cache|@InvalidateCache",
  relative_path: "apps/backend/src/app/$ARGUMENTS",
  restrict_search_to_code_files: true,
  context_lines_after: 1
)
```

### 7. Output — kompaktowy mental model

Wyswietl podsumowanie:

```
## Kontekst: $ARGUMENTS

### Backend
- **Endpointy:** [lista metod z controllera: GET /api/rooms, POST /api/rooms, ...]
- **Serwis:** [lista metod z serwisu]
- **Repository:** [lista metod z repo]

### Prisma
- **Modele:** [nazwy modeli + kluczowe pola]
- **Relacje:** [FK → ktory model]
- **Enumy:** [lista enumow]

### Validation
- **Schematy:** [lista schematow z walidacji]

### Frontend
- **Strony:** [lista plikow page.tsx]
- **Komponenty:** [lista komponentow]
- **Hooks:** [lista useXxx z queries]
- **API routes:** [lista route'ow]

### Permissions & Cache
- **Permissions:** [lista @RequirePermissions]
- **Cache keys:** [lista @Cache]

### Z pamieci Sereny:
- [kluczowe wnioski z pamieci jesli istnialy]

### Gotowy do pracy!
Masz pelny kontekst. Co chcesz zrobic z tym modulem?
```
