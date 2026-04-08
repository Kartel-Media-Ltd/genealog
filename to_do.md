# Genealog — TO DO

> **Stan:** 2026-04-08 (po 5 audytach bezpieczeństwa — backend, backend-2, backend-3, backend-4, backend-5; łącznie ~134+ zadań naprawczych zrealizowanych)
>
> Dokument zbiera wszystko co pozostało do rozpatrzenia / implementacji w projekcie.
>
> **Ostatnie iteracje audytów:**
> - ✅ `/ultra-audit backend` → `dev/audit/backend/` (73 zadań, ~60 zrealizowanych)
> - ✅ `/ultra-audit backend-2` → 21 zadań, 17 zrealizowanych (Faza 3 architektury świadomie odroczona)
> - ✅ `/ultra-audit backend-3` → 25 zadań, **25/25 zrealizowane**
> - ✅ `/ultra-audit backend-4` → 27 zadań, **25/27 zrealizowane** (D13, D14 odroczone)
> - ✅ `/ultra-audit backend-5` → 4 zadania, **4/4 zrealizowane** — system osiąga status **PASS** (bez warunków)

---

## 🚨 Pilne — wymagają decyzji usera

### USER_ACTIONS (migracje + reindex)

Wszystkie migracje są **idempotentne** (`IF NOT EXISTS` / drop-if-exists pattern), bezpieczne przy wielokrotnym uruchomieniu.

```bash
# Aktualne migracje 006-015 (uruchom po kolei)
source .env.local && for f in migrations/{006,007,008,009,010,011,012,013,014,015}_*.sql; do
  echo "=== $f ==="
  docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < "$f"
done

# Retroaktywne wypełnienie global_person_index dla istniejących osób
php bin/reindex-all.php
```

**Nowe migracje z backend-3/backend-4:**
- `012_persons_indexes.sql` — P8 performance index `persons(tree_id, gedcom_xref)`
- `013_user_restriction.sql` — RODO Art. 18 right to restriction
- `014_tree_members_invited_by.sql` — FK fix (brakująca kolumna)
- `015_user_consent.sql` — RODO Art. 7(1) persystencja zgody (`terms_accepted_at`, `terms_version`)

### USER_ACTIONS (env vars — RODO compliance)

Uzupełnij `.env.local` o dane administratora i DPO (używane w Privacy Policy, Terms, eksporcie danych):

```bash
COMPANY_NAME="Nazwa Sp. z o.o."
COMPANY_ADDRESS="ul. Przykładowa 1, 00-000 Warszawa"
COMPANY_NIP="1234567890"
DPO_EMAIL="dpo@example.com"
CONTACT_EMAIL="kontakt@example.com"
SERVER_LOCATION="Hetzner DC Frankfurt (DE)"
```

Bez tych wartości Privacy Policy będzie wyświetlać `[TODO]` placeholdery.

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

## ✅ Audyty bezpieczeństwa backend-3 + backend-4 + backend-5 — KOMPLETNE (2026-04-08)

**Backend-3** (`dev/audit/backend-3/`) — 25/25 zadań:
- ✅ **K1** SMTP header injection — `EmailService::sanitizeHeader()` + MIME boundary `bin2hex(random_bytes)`
- ✅ **K2** `findSoleOwnedTreeIds` utrata danych innych userów — `NOT EXISTS` + `transferOwnership`
- ✅ **K3** Privacy Policy + Terms + Consent flow przy rejestracji
- ✅ **P1-P11, P13** Security + RODO + NIS2 (XSS, MIME bypass, invitation email check, rate limit /register, tmpPath leak, session_version sync, index, depth cap, DataExport rozszerzony, RODO Art. 18, backup plan)
- ✅ **D1-D12** WCAG skip link, aria-live, health endpoint, .env.example

**Backend-4** (`dev/audit/backend-4/`) — 25/27 zadań (D13, D14 odroczone):
- ✅ **K1** RODO Art. 7(1) — persystencja zgody (`terms_accepted_at`, `terms_version`, migration 015)
- ✅ **K2** Privacy Policy — nowa sekcja 9 Discovery Sources + tabela rejestrów + SCC info dla FamilySearch USA
- ✅ **P1** Batch `tree.imported` event — eliminacja DoS przy bulk GEDCOM import (5000 osób × matching → 1× `matchTreeAfterImport`)
- ✅ **P2** `isDiscoveryOptedIn` check w `MatchingService::findAndNotifyMatches` (RODO Art. 7(3))
- ✅ **P3** `GlobalIndexService::reindexTree` — pre-fetch tree+opt-in, chunking 100/batch, `set_time_limit(300)`
- ✅ **P4** CrossTreeMatchSource error_log bez userId + sampling 1%
- ✅ **P5** `MatchSourceInterface::getTimeoutSeconds()` + stałe `SOURCE_LOCAL/CROSS_TREE/EXTERNAL`
- ✅ **P6** DataExport `tree-memberships.json` + `password-resets.json` (Art. 15)
- ✅ **P8** Config stałe `COMPANY_*`, `DPO_EMAIL`, `SERVER_LOCATION` — podmiana placeholderów w Privacy/Terms
- ✅ **P9** incident-response.md scenariusz E (external dependency outage)
- ✅ **P10** Consent checkbox `aria-describedby="consent-desc"`
- ✅ **P11** GenetykaMatchSource path validation — `realpath()` + allowlist `STORAGE_PATH/geneteka`
- ✅ **D1-D12** `/health` cleanup, ToC w Privacy Policy, FK automat w migration 014, memoization w Registry, filtr `visibility` w LocalTreeMatchSource, 10 testów dla `EmailService::sanitizeHeader` (K1 regression guard)

**Weryfikacja końcowa:** 70/70 testów PHPUnit passed (wzrost +34 vs poprzednio).

**Odroczone z backend-4 (nie blokują):**
- **D13** — AccountDeletionService unit tests (wymagają Database mock)
- **D14** — `unindexTree` batching (wymaga async jobs)
- **P5/P12/P14 (backend-3)** — CSP nonce, DPIA, MFA TOTP

**Backend-5** (`dev/audit/backend-5/`) — 4/4 zadań (werdykt: **PASS** bez warunków):
- ✅ **K1-NEW** GenetykaMatchSource — jawne nawiasy w operator precedence (`GenetykaMatchSource.php:45`)
- ✅ **P1-NEW** `matchTreeAfterImport` timeout 120→300s (spójnie z `GlobalIndexService::reindexTree`)
- ✅ **P2-NEW** incident-response Scenariusz E — nagłówek `### E) Awaria zewnętrznej zależności` już był obecny
- ✅ **P3-NEW** EmailServiceTest — dodano `testSanitizeHeaderPreservesTabCharacter` (tab RFC 5322, 11/11 passed)

**Backlog z backend-5 (D1-D2-NEW — nie blokują):**
- `MatchSourceRegistry::unregister()` — low priority
- `matchTreeAfterImport` limit powiadomień per import (max 10)

---

## ✅ Relationship Suggestions — KOMPLETNE (2026-04-08)

> Pierwotnie wskazane w `dev/active/relationship-suggestions/review-2026-04-07.md`

Wszystkie fazy (1-4) zaimplementowane. Wszystkie issues z review naprawione:

- ✅ **B1-B4** Blockery naprawione (camelCase, dedup, child-guard, typeLabel)
- ✅ **I1-I8** Important naprawione: flash error handling, GEDCOM spouse inverse, int year compare, N+1 cache, `compute()` intentional, canonical pair dedup, MARR always, separate redirect calls
- ✅ **N1-N5** Nity naprawione: Session import, RelationshipRepository usunięty z SuggestionController, PHPDoc, magic number

**Weryfikacja 2026-04-08:** 60/60 testów green, phpstan 0 errors.

**Pozostało:** Faza 5 — manual E2E testy (patrz `dev/active/relationship-suggestions/relationship-suggestions-zadania.md`)

---

## 🟠 Pending tasks per feature (z dev/active/)

| Feature | Pending | Blocker? | Komentarz |
|---|---|---|---|
| **font-awesome** | 0 | ✅ | Fazy 1-8 kompletne; 141× render_icon(), 0 inline SVG; atrybucja w footerze |
| **gedcom** | 0 | ✅ | Fazy 1-5 kompletne; blocking/important/nity zweryfikowane; E2E = manual |
| **global-admin** | 0 | ✅ | Fazy 1-5 kompletne; wszystkie nity N1-N7 zweryfikowane |
| **person-discovery** | 0 | ✅ | KOMPLETNE (poza I7 reindex batch — patrz niżej) |
| **php-scaffold** | 0 | ✅ | Fazy 1-6 + review kompletne; [USER] zrotuj hasło SMTP w .env.local |
| **print-pdf** | 0 | ✅ | Fazy 1-6 kompletne; blocking/important/nity [x]; Faza 6 = E2E manual |
| **registries** | 55 | ⚠️ | Cały feature — wymaga decyzji o API keys (patrz wyżej) |
| **relationship-suggestions** | 0 | ✅ | Fazy 1-4 kompletne; Faza 5 = manual E2E |
| **tree-sharing** | 0 | ✅ | Fazy 1-8 kompletne; Faza 9 = manual E2E |

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

### Inline SVG → Font Awesome migration ✅

- Migracja ukończona: 141× `render_icon()`, 0 inline SVG pozostało
- Atrybucja w footerze `AppLayout.php` (CC BY 4.0)

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
- ❌ Wpis o migracjach 006-015 (+012 indexes, 013 Art. 18 restriction, 014 FK, 015 consent persistence)

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

1. ~~**🔴 Naprawić 4 blockery z relationship-suggestions**~~ ✅ KOMPLETNE
2. ~~**🔴 Audyty backend-3 + backend-4**~~ ✅ KOMPLETNE (50/52 zadań)
3. **🔴 USER_ACTIONS — migracje 012-015 + env vars RODO** (patrz sekcja na górze)
4. **🔴 Research prawny:** Privacy Policy + Terms + SCC z FamilySearch Inc. przed aktywacją
5. ~~**🟠 Re-audit #5**~~ ✅ KOMPLETNE (`dev/audit/backend-5/` — **PASS** bez warunków, commit `54b25af`)
6. **🟠 Tests: AccountDeletionService (D13 backend-4) + GlobalIndexService** (RODO compliance)
7. **🔴 Php-scaffold pozostały 1 task** (sprawdzić co)
8. **📝 CLAUDE.md** + MEMORY.md update — Discovery, consent flow, Art. 18 restriction

### Sprint 2 (UX + dokończenie features — 6-8h)

5. ~~**Font Awesome implementacja**~~ ✅ ukończone
6. ~~**Tree sharing**~~ ✅ ukończone (Fazy 1-8)
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
| **Relationship suggestions** | 🟢 | Fazy 1-4 kompletne, wszystkie issues z review naprawione |
| **Font Awesome icons** | 🟢 Production-ready | Fazy 1-8 kompletne; 141× render_icon(), 0 inline SVG; atrybucja w footerze |
| **External registries** | 🔴 | Plan gotowy, wymaga API keys + decyzji |

---

## 📊 Metryki projektu (2026-04-08, po backend-5)

| Metryka | Wartość |
|---|---|
| **Testy** | **71** (71/71 green — +1 EmailServiceTest tab injection z backend-5) |
| **Phpstan** | level 5, 0 errors |
| **Migracje** | **15** (001-015, wszystkie idempotentne 006+; 012-015 z backend-3/4) |
| **Routes** | ~82 endpointów (+/privacy, /terms, /health, /settings/restrict) |
| **Services** | ~27 (+Discovery/ subfolder: Matching, PersonImport, Fingerprint, GlobalIndex, 4 MatchSources) |
| **Controllers** | 17 |
| **Repositories** | 11 (+`isDiscoveryOptedIn`, `existsRecentForLink`, `setRestricted`) |
| **Models** | 5 (User z `isRestricted` flag) |
| **Atoms (UI)** | 10 |
| **CI** | GitHub Actions (.github/workflows/ci.yml) — composer audit blokujący |
| **Audyty bezpieczeństwa** | **5 iteracji** (backend, backend-2/3/4/5) — **PASS** ✅ |
| **Backend-5 test coverage** | ~13% (71 testów / ~65 src files) |

### Trend jakości audytów

| Audit | KRYT | POW | DROB | Pozytywne | Status |
|-------|------|-----|------|-----------|--------|
| #1 backend | 2 | 17 | 19 | 29 | 60/73 naprawione |
| #2 backend-2 | 2 | 8 | 4 | — | 17/17 naprawione |
| #3 backend-3 | 3 | 14 | 13 | 19 | 25/25 naprawione ✅ |
| #4 backend-4 | 2 | 14 | 14 | 24 | 25/27 naprawione (D13, D14 odroczone) |
| **#5 backend-5** | **1** | **4** | **3** | **26** | **4/4 naprawione ✅ PASS** |

**Obserwacja:** Pierwszy raz poniżej 5 poważnych problemów. System gotowy do zewnętrznego pentesta.

**Następne kroki:** DPO review + uzupełnienie env vars `COMPANY_*`, `DPO_EMAIL`. SCC z FamilySearch Inc. przed aktywacją external sources.

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
