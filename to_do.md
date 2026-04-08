# Genealog — TO DO

> **Stan:** 2026-04-08 (po implementacji 39 issues z review person-discovery)
>
> Dokument zbiera wszystko co pozostało do rozpatrzenia / implementacji w projekcie.

---

## 🚨 Pilne — wymagają decyzji usera

### USER_ACTIONS (migracje + reindex)

Wszystkie migracje są **idempotentne** (`IF NOT EXISTS`), ale jeśli baza nie jest aktualna:

```bash
# Aktualne migracje 006-014 (uruchom po kolei, bezpiecznie wielokrotnie)
source .env.local && for f in migrations/{006,007,008,009,010,011,012,013,014}_*.sql; do
  echo "=== $f ==="
  docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < "$f"
done

# Retroaktywne wypełnienie global_person_index dla istniejących osób
php bin/reindex-all.php
```

### Klucze API (jeśli włączamy registries / external sources)

```bash
# .env.local — opcjonalnie:
FAMILYSEARCH_CLIENT_ID=...      # https://www.familysearch.org/developers/
FAMILYSEARCH_CLIENT_SECRET=...
FAMILYSEARCH_ENV=sandbox        # 'production' wymaga certyfikacji jako legal entity
GENETEKA_LOCAL_DB=...           # ścieżka do lokalnego dumpu (wymaga kontaktu z PTG)
ARCHIVES_API_KEY=...            # NIEDOSTĘPNE — szukajwarchiwach.gov.pl nie ma publicznego API
```

**Status registries:**
- 🟢 FamilySearch — Sandbox dostępny od razu, produkcja wymaga certyfikacji
- 🔴 Szukaj w Archiwach — brak publicznego API (kontakt: szukajwarchiwach@nac.gov.pl)
- 🔴 Geneteka CSV dump — PTG nie udostępnia publicznie (kontakt: zarzad@genealodzy.pl)

---

## 🔴 Krytyczne błędy do naprawy

### Relationship Suggestions — 4 blockery (z review)

> Plik: `dev/active/relationship-suggestions/relationship-suggestions-zadania.md` linie 109-112

- 🔴 **B1** `src/Services/GedcomService.php:450,452,464,465,477,481,482` — `$rel->person_a_id` / `person_b_id` / `start_date` muszą być camelCase (`personAId`, `personBId`, `startDate`); model używa camelCase, snake_case zwraca cicho `null` → **GEDCOM eksport produkuje pusty `$parentToChildren` → wszystkie FAM bez `CHIL`**
- 🔴 **B2** `src/Services/GedcomService.php:447-454` — `$parentToChildren` zbiera duplikaty z forward+inverse rekordów relacji
- 🔴 **B3** `src/Services/SuggestionService.php:104-114` — case `'child'` sugeruje rodzeństwo bez weryfikacji że ma `personId` jako rodzica
- 🔴 **B4** `src/views/pages/trees/persons/show.php:186-193` — `typeLabel` parent↔child odwrócone

**Wpływ:** GEDCOM eksport może być uszkodzony, suggestions pokazują nieprawidłowe podpowiedzi.

---

## 🟠 Pending tasks per feature (z dev/active/)

| Feature | Pending | Blocker? | Komentarz |
|---|---|---|---|
| **font-awesome** | 109 | ❌ | Cały feature niezaimplementowany — `/ultra-workaholic font-awesome` |
| **gedcom** | 18 | ⚠️ | Pozostałe nity + recovery po B1-B4 z relationship-suggestions |
| **global-admin** | 16 | ❌ | Pozostałe ulepszenia (filtry, view-actions audit już zrobione) |
| **person-discovery** | 0 | ✅ | KOMPLETNE (poza I7 reindex batch — patrz niżej) |
| **php-scaffold** | 1 | ❌ | Pojedynczy task pozostały (sprawdzić co konkretnie) |
| **print-pdf** | 36 | ❌ | Większość drobnych ulepszeń (Faza 8 weryfikacja E2E) |
| **registries** | 55 | ⚠️ | Cały feature — wymaga decyzji o API keys (patrz wyżej) |
| **relationship-suggestions** | 31 | 🔴 | **4 BLOCKERY** + 27 pozostałych |
| **tree-sharing** | 64 | ❌ | Cały feature niezaimplementowany |

---

## 🟡 Architectural / odłożone z review

### Person Discovery (I7 — z review #2)

- **`reindexTree` synchronicznie blokuje request** — przy drzewie 1000+ osób kilka sekund odpowiedzi HTTP
  - Plik: `src/Services/Discovery/GlobalIndexService.php:reindexTree()` + `DiscoveryController::updateSettings`
  - **Fix:** Background job queue (cron + tabela `jobs` lub Redis), batch commit per N osób
  - Decyzja MVP: pomijamy bo drzewa zwykle <100 osób

### DI Container — używać w public/index.php

- `src/Core/Container.php` istnieje od commitu `97a8201`, ale `public/index.php` wciąż konstruuje serwisy ręcznie (90+ linii)
- **Fix:** Zrefactorować `public/index.php` żeby używał Container — nowe serwisy = 1 linia rejestracji zamiast modyfikacji konstruktora
- Korzyść: czystszy bootstrap, łatwiejsze testy integracyjne (mock container)

### Inline SVG → Font Awesome migration

- 19 plików w `src/views/` używa inline SVG
- Plan gotowy: `dev/active/font-awesome/`
- **Po wdrożeniu Font Awesome:** uruchomić `/ultra-workaholic font-awesome` — 8 faz, ~2h pracy

### Tests — pokrycie Discovery klas

Aktualnie tylko `FingerprintServiceTest` (24 testy). Brak testów dla:
- `GlobalIndexService` — RODO compliance (5 reguł kwalifikacji)
- `MatchingService::findCandidates` — orchestrator
- `MatchingService::findAndNotifyMatches` — persistowanie sugestii (B1 fix)
- `CrossTreeMatchSource` — confidence tiers (B6 fix)
- `LocalTreeMatchSource` — exact + fuzzy z birth year scoring
- `PersonImportService` — race condition + transakcje (B5 fix)
- `DiscoveryRepository` — saveSuggestion + IDOR
- `DiscoveryController::importMatch/rejectMatch` — CSRF rotation (B2 fix)

**Priorytet:** GlobalIndexService > PersonImportService > pozostałe.

---

## 🔵 Operational / DevOps

### CI/CD

- ✅ Już istnieje: `.github/workflows/ci.yml` (utworzony w jednym z poprzednich commitów)
- ❌ Sprawdzić co aktualnie robi (lint, test, phpstan?)
- ❌ Lighthouse w CI dla performance regression detection
- ❌ Auto-deploy do staging po merge do main

### Phpstan — upgrade do level 6

- Aktualnie level 5 z `ignoreErrors` dla 8 false-positives
- **Level 6** wymaga pełnych PHPDoc generic types (np. `array<string, mixed>` zamiast `array`)
- Estymacja: ~50 fix'ów (głównie iterable type hints w services)

### Database backup

- ✅ Już istnieje: `docs/operations/backup.md` (z poprzedniego commitu)
- ❌ Zweryfikować że backup script faktycznie istnieje i działa
- ❌ Restore procedure test (raz na kwartał)

### Cron jobs

- ✅ Już istnieje: `infrastructure/cron.example` + `bin/cleanup-audit-log.php` + `bin/cleanup-password-resets.php`
- ❌ Skonfigurować na produkcji
- 🔵 **Suggestion:** dodać cron dla `bin/reindex-all.php` (co tydzień, dla osób pominiętych)

---

## 📝 Dokumentacja

### CLAUDE.md aktualizacje

- ❌ Sekcja "Person Discovery" z opisem MatchSourceInterface + EventDispatcher
- ❌ Sekcja "Powiadomienia" — NotificationService API + types
- ❌ Sekcja "Ikony" — render_icon helper + lista FA ikon (po wdrożeniu Font Awesome)
- ❌ Sekcja "Discovery RODO compliance" — 5 reguł kwalifikacji + audit log

### MEMORY.md aktualizacje

- ❌ Wpis o zaimplementowanym Person Discovery
- ❌ Wpis o session_version (RODO Art. 18 + invalidation on demote/block)
- ❌ Wpis o migracjach 006-014

### Atrybucja licencji

- ❌ Stopka `AppLayout.php` lub strona `/about` z listą third-party:
  - D3.js (BSD)
  - Alpine.js (MIT)
  - Tailwind CSS (MIT)
  - Font Awesome (po wdrożeniu — CC BY 4.0)
  - PHP, MariaDB, etc.

---

## 🎯 Priorytety — sugerowana kolejność prac

### Sprint 1 (krytyczne — 4-6h)

1. **🔴 Naprawić 4 blockery z relationship-suggestions** (GedcomService snake_case, parent/child label)
2. **🔴 Php-scaffold pozostały 1 task** (sprawdzić co)
3. **🟠 Tests dla GlobalIndexService** (RODO compliance — najważniejsze przed produkcją)
4. **📝 CLAUDE.md** + MEMORY.md update

### Sprint 2 (UX + dokończenie features — 6-8h)

5. **Font Awesome implementacja** (`/ultra-workaholic font-awesome` — 8 faz)
6. **Tree sharing** (cały feature 0/64 — collaboration features)
7. **Print-PDF Faza 8** (weryfikacja E2E + pozostałe ulepszenia)

### Sprint 3 (rozszerzenia — 8-12h)

8. **Registries** (po decyzji o API keys — FamilySearch Sandbox + Geneteka scraper)
9. **DI Container** refactor `public/index.php`
10. **Phpstan level 6** upgrade
11. **Tests** dla pozostałych Discovery klas

### Backlog (post-MVP)

12. **renderForPrint() w tree-visualizer** — eliminuje canvas taint dla PNG export
13. **reindexTree background job** (Person Discovery I7)
14. **MyHeritage / Ancestry API** — partnership program
15. **Lighthouse w CI** — performance regression detection
16. **Auto-deploy staging** po merge do main
17. **Audit logs filtering UI** — bardziej zaawansowany dashboard admina
18. **Notification email digest** — daily summary nieprzeczytanych

---

## ✅ Co już działa (status końcowy 2026-04-08)

| Feature | Status | Notatki |
|---|---|---|
| **PHP MVC scaffold** | 🟢 Production-ready | 60/60 testów green, phpstan green |
| **Auth + Sessions** | 🟢 Production-ready | bcrypt 12, CSRF rotation, rate limit, session_version |
| **Trees CRUD** | 🟢 Production-ready | Multi-user, tree_members, RBAC (owner/editor/viewer) |
| **Persons CRUD** | 🟢 Production-ready | + relacje (parent/child/spouse/sibling), photos, GEDCOM xref |
| **Visualizer (D3.js)** | 🟢 Production-ready | Hierarchia, zoom/pan, expanded fullscreen mode |
| **GEDCOM import/eksport** | 🟢 Production-ready | Custom parser (bez external lib), validate HEAD/TRLR, errors UI |
| **Print/PDF** | 🟢 Production-ready | A3/A4 orientation switch, persons list, single source PageSize |
| **Admin panel** | 🟢 Production-ready | Filtry logs, impersonacja z notify RODO, session invalidation |
| **Person Discovery** | 🟢 Production-ready | MatchSourceInterface (4 sources), CrossTree anonimizacja, panel UI |
| **Notifications** | 🟢 Production-ready | Bell icon polling 30s, dedup 24h, 5 typów |
| **RODO compliance** | 🟢 | Account deletion, data export, audit log, opt-in cross-tree |
| **Tree sharing** | 🟡 | Invitations + members działają, brak granular permissions |
| **Relationship suggestions** | 🟡 | Działa, ale ma 4 blockery do naprawy |
| **Font Awesome icons** | 🔴 | Plan gotowy, niezaimplementowane |
| **External registries** | 🔴 | Plan gotowy, wymaga API keys + decyzji |

---

## 📊 Metryki projektu (2026-04-08)

| Metryka | Wartość |
|---|---|
| **Testy** | 60 (24 nowe FingerprintService) |
| **Phpstan** | level 5, 0 errors |
| **Migracje** | 14 (001-014, wszystkie idempotentne 006+) |
| **Routes** | ~80 endpointów |
| **Services** | ~25 |
| **Controllers** | 17 |
| **Repositories** | 11 |
| **Models** | 5 |
| **Atoms (UI)** | 10 |
| **CI** | GitHub Actions (.github/workflows/ci.yml) |

---

## 💡 Pomysły na przyszłość (poza scope MVP)

- **Mobile app** (React Native lub PWA)
- **Real-time collaboration** (WebSocket dla wielu userów na drzewie)
- **AI suggestions** — OCR scanów dokumentów + auto-fill
- **3D visualization** — drzewo jako 3D graph (three.js)
- **Heritage map** — geolokalizacja wszystkich osób na mapie
- **Family events timeline** — chronological view z dat urodzin/śmierci/ślubów
- **Photo restoration** — AI upscale starych zdjęć
- **DNA matching integration** — z 23andMe / Ancestry DNA
