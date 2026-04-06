---
description: Cleanup kodu — usun console.log, popraw importy, formatuj zmienione pliki, sprawdz TS errors
argument-hint: Opcjonalnie sciezka/glob (domyslnie zmienione pliki z git diff)
allowed-tools: Bash(git:*), Bash(npx:*), Bash(pnpm:*), Read, Edit, Grep, Glob, AskUserQuestion
---

# /tidy — Cleanup kodu

Czyscisz kod w zmienionych plikach. Usuwasz smieci, poprawiasz importy, formatujesz.
NIGDY nie dodawaj nowych features — tylko czyszczenie.

## Zadanie

Scope: $ARGUMENTS (domyslnie: pliki zmienione w git)

## Kroki

### 1. Okresl scope plikow

Jesli $ARGUMENTS podaje sciezke/glob → uzyj tego.
W przeciwnym razie:
```bash
git diff --name-only HEAD
```
Plus unstaged:
```bash
git diff --name-only
```
Plus untracked (nowe pliki):
```bash
git ls-files --others --exclude-standard
```

Filtruj: tylko `.ts`, `.tsx`, `.js`, `.jsx`, `.css` pliki. Ignoruj: `.json`, `.md`, `generated/`.

### 2. Usun console.log/console.warn/console.error (KRYTYCZNE)

Dla kazdego pliku ze scope:
```
Grep pattern="console\.(log|warn|error|debug|info)" w plikach ze scope
```

Dla kazdego znalezionego:
- Jesli to **tymczasowy debug log** (np. `console.log('test')`, `console.log(data)`) → USUN
- Jesli to **celowy error handler** (np. w catch block z `console.error`) → ZOSTAW
- Jesli nie jestes pewien → zaznacz do review

### 3. Sprawdz nieuzywane importy

Dla kazdego pliku `.ts`/`.tsx` ze scope:
- Przeczytaj plik
- Znajdz importy ktore nie sa uzywane w reszcie pliku
- USUN nieuzywane importy

**WAZNE:** Nie usuwaj importow ktore:
- Sa type-only (`import type { ... }`) i uzywane w typach
- Sa side-effect importy (`import './styles.css'`)
- Sa re-exporty

### 4. Popraw kolejnosc importow

Standardowa kolejnosc:
1. React/Next.js (`react`, `next/*`)
2. External libraries (`@tanstack/*`, `lucide-react`, etc.)
3. Internal aliases (`@z`, `@prisma`, `@ui`, `@i18n`)
4. Relative imports (`./`, `../`)

Oddziel grupy pustą linią.

### 5. Formatuj (Prettier)

```bash
npx prettier --write [lista plikow ze scope]
```

### 6. Sprawdz TypeScript errors (opcjonalnie)

Jesli scope zawiera pliki `.ts`/`.tsx`:
```bash
npx tsc --noEmit --pretty 2>&1 | head -30
```

Jesli sa bledy w plikach ze scope → wyswietl je (nie naprawiaj automatycznie — to moze byc intencjonalne).

### 7. Raport

```
## Tidy — gotowe!

### Wyczyszczone:
- Usunieto console.log: [liczba] w [lista plikow]
- Usunieto nieuzywane importy: [liczba] w [lista plikow]
- Poprawiono kolejnosc importow: [lista plikow]
- Sformatowano Prettier: [liczba plikow]

### TS errors (jesli sa):
- [lista bledow w scope plikach]

### Scope:
- [liczba] plikow przeskanowanych
```

### 8. Zapytaj usera

AskUserQuestion:
- "Czy commitowac cleanup?"
- Opcje:
  - "Tak, commituj" → `git add [pliki] && git commit -m "chore: tidy up code"`
  - "Nie, zostawiam bez commitu"
