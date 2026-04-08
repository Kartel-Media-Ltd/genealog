# Audyt bezpieczeństwa — Genealog (backend) — Re-audit #5

**Data:** 2026-04-08
**Metoda:** Weryfikacja backend-4 napraw + detekcja regresji (1 agent security-auditor)
**Zakres:** Backend po implementacji 25 zadań z backend-4
**Werdykt:** **PASS WITH CONDITIONS** — 24/25 PASS, 1 PARTIAL, 1 krytyczny do naprawy, 3 drobne

---

## Kontekst

Piąty audyt w sekwencji:

| # | Folder | Zadań | Naprawionych |
|---|--------|-------|--------------|
| 1 | backend | 73 | ~60 |
| 2 | backend-2 | 21 | 17 |
| 3 | backend-3 | 25 | **25/25** ✅ |
| 4 | backend-4 | 27 | **25/27** (D13, D14 odroczone) |
| **5** | **backend-5** (ten) | weryfikacja #4 | — |

---

## Weryfikacja backend-4 — 25 zadań

### ✅ PASS (24/25)

| ID | Plik | Dowód |
|----|------|-------|
| K1 migration | `migrations/015_user_consent.sql` | `VARCHAR(40)`, idempotent MODIFY, UPDATE legacy users |
| K1 UserRepository | `src/Repositories/UserRepository.php:36-51` | `?string $termsVersion` parametr + INSERT z kolumnami |
| K1 AuthService | `src/Services/AuthService.php:55-56` | `defined('TERMS_VERSION') ? TERMS_VERSION : '2026-04-08'` |
| K2 privacy.php | `src/views/pages/privacy.php:154-189` | Sekcja 9 + tabela FamilySearch/Geneteka + `id="sec-9"` |
| K2 terms.php | `src/views/pages/terms.php:56-67` | §3a Discovery integracje |
| P1 GedcomService | `src/Services/GedcomService.php:43,96-106` | Flaga ustawiana, unset przed emit, try/catch dispatch |
| P1 handler | `public/index.php:143-153` | Skip matching przy flag + `tree.imported` listener |
| P1 matchTreeAfterImport | `src/Services/Discovery/MatchingService.php:209-258` | Limit 100 osób + try/catch per person |
| P2 opt-in check | `src/Services/Discovery/MatchingService.php:146-149` | `isDiscoveryOptedIn` przed matching |
| P2 DI | `MatchingService.php:35` | UserRepository wstrzyknięty |
| P3 reindexTree | `src/Services/Discovery/GlobalIndexService.php:149-183` | set_time_limit + pre-fetch + chunking + unindex przy cofniętym opt-in |
| P4 CrossTreeMatchSource | `Sources/CrossTreeMatchSource.php:177-178` | Sampling + bez userId |
| P5 Interface | `MatchSourceInterface.php:21-23,52` | Stałe SOURCE_* + `getTimeoutSeconds()` |
| P5 implementacje | wszystkie 4 sources | Timeout: 5s/5s/10s/8s |
| P6 DataExport | `DataExportService.php:127-204` | tree-memberships + password-resets (bez tokenów) |
| P8 privacy stałe | `privacy.php:41-45` | `defined('COMPANY_NAME')` htmlspecialchars |
| P8 config stałe | `config/config.php:37,42-47` | 6× COMPANY_/DPO_/CONTACT_/SERVER_ |
| P10 register | `register.php:99,102` | `aria-describedby="consent-desc"` + label `id` |
| P11 GenetykaMatchSource | `Sources/GenetykaMatchSource.php:39-51` | realpath + allowlist STORAGE_PATH/geneteka |
| D1 health | `ApiController.php:135-137` | Bez `ts` pola |
| D2 komentarz | `MatchingService.php:198` | `existsRecentForLink` |
| D3 LocalTreeMatchSource | `Sources/LocalTreeMatchSource.php:99,133` | Obie queries `visibility != 'private'` |
| D4 Registry cache | `MatchSourceRegistry.php:19-54` | `$enabledCache` + invalidate w register() |
| D6 stałe | `MatchingService.php:59-154` | Zero magic strings `'local'`/`'cross_tree'` |
| D7 DataExport README | `DataExportService.php:190` | `defined('DPO_EMAIL')` |
| D8 ToC | `privacy.php:20-35` | `<nav aria-label="Spis treści">` + `id="sec-1..12"` |
| D11 migration 014 FK | `014_tree_members_invited_by.sql` | Drop-if-exists automatyczny FK |
| D12 EmailServiceTest | `tests/Unit/Services/EmailServiceTest.php` | 9 testów (planowano 10 — patrz P-NEW-2) |

### ⚠️ PARTIAL (1/25)

**P9 — incident-response scenariusz E**
- **Plik:** `docs/security/incident-response.md`
- **Status:** treść merytorycznie obecna (sekcja 5 mówi o external dependency outage), ale **bez jawnego nagłówka "Scenariusz E"** zgodnego z konwencją A-D.
- **Naprawa:** rename nagłówka na `### E) Awaria zewnętrznej zależności (Discovery Sources)` dla spójności.

### Regresje

**0 wykrytych.** PersonImportService optimistic lock nienaruszony. AuthController::processLogin `isRestricted` handler nadal działa. DiscoveryController spójny z nową sygnaturą MatchingService.

---

## Nowe znaleziska [K] [P] [D]

### KRYTYCZNE [K]

**K1-NEW — Operator precedence w `GenetykaMatchSource`**
- **Plik:** `src/Services/Discovery/Sources/GenetykaMatchSource.php:45`
- **Dowód:**
  ```php
  if ($real === false || $allowed === false || !str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && $real !== $allowed) {
  ```
- **Problem:** brak nawiasów — PHP parsuje jako `(... || ... || !starts_with) && $real !== $allowed`. Semantycznie działa w obecnym scenariuszu, ale jest nieczytelne i podatne na błąd przy refaktoringu.
- **Ryzyko:** ryzyko typu "code smell regression" — przyszły refactoring może złamać path validation.
- **Naprawa:**
  ```php
  if ($real === false
      || $allowed === false
      || (!str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && $real !== $allowed)
  ) {
  ```
- **Effort:** XS (2 min)

### POWAŻNE [P]

**P1-NEW — `matchTreeAfterImport` timeout asymetria**
- **Plik:** `src/Services/Discovery/MatchingService.php:232`
- **Dowód:** `@set_time_limit(120)` vs `GlobalIndexService::reindexTree` używa `300`.
- **Problem:** Przy 100 osobach × timeout per source (5s local + 5s cross_tree = 10s każdą z osób w worst-case) możliwy total 1000s. Limit 120s zdecydowanie za mały przy stresie.
- **Naprawa:** zwiększ do 300s (spójnie z reindexTree) lub dodaj limit powiadomień per batch (max 20).
- **Effort:** XS (2 min)

**P2-NEW — P9 scenariusz E bez etykiety**
- Patrz "PARTIAL" wyżej.
- **Effort:** XS (2 min)

**P3-NEW — EmailServiceTest 9 testów zamiast planowanych 10**
- **Plik:** `tests/Unit/Services/EmailServiceTest.php`
- **Dowód:** plik zawiera 9 metod `test*`, specyfikacja backend-4 mówi o 10.
- **Problem:** brakuje testu na whitespace/tab injection (np. `"Jan\tName"` — tab nie jest w `sanitizeHeader` removal set).
- **Naprawa:** dodaj test:
  ```php
  public function testSanitizeHeaderDoesNotRemoveTab(): void
  {
      // Tab nie jest w whitelist usuwania (CR/LF/NULL) — to jest intended
      $result = $this->sanitize->invoke($this->service, "Jan\tKowalski");
      $this->assertSame("Jan\tKowalski", $result);
  }
  ```
  Lub dodaj tab do `sanitizeHeader` (restrict bardziej).
- **Effort:** XS (5 min)

### DROBNE [D]

**D1-NEW — brak `unregister()` w MatchSourceRegistry**
- Invalidation działa tylko w `register()`. Przy przyszłej rozbudowie (dynamic unregister) cache może być stale. Niski priorytet.

**D2-NEW — `matchTreeAfterImport` bez limitu powiadomień per import**
- Teoretycznie 100 osób × 1 powiadomienie (dedup 24h) = 100 powiadomień dla ownera po jednym imporcie.
- **Fix:** dodaj licznik i break po N=10 powiadomień — user widzi "dopasowano N+ osób, kliknij aby zobaczyć".

**D3-NEW — P9 sekcja E bez jawnej numeracji** (zduplikowane z P2-NEW dla spójności listy drobnych).

---

## POZYTYWNE [+] — utrwalone z backend-4

- **P3 wzorowa implementacja** — `GlobalIndexService::reindexTree` ma kompletny pre-fetch + chunking + time limit + early exit
- **P2 defensive null check** — `userRepo !== null && !isDiscoveryOptedIn` (optional dependency safe)
- **D6 zero magic strings** — cały `MatchingService` używa stałych interfejsu
- **Bootstrap index.php spójny** — MatchingService dostaje wszystkie 6 zależności
- **GenetykaMatchSource path validation** działa dla katalogu root (`$real !== $allowed`)
- **Registry memoization poprawna** — invalidation per `register()`, zero side effects przy konstrukcji
- **AccountDeletion** (K2 backend-3) nienaruszone
- **Session security** (AuthMiddleware, session_version) stabilne
- **Discovery pluggable architecture** — wzorcowy OCP, exception isolation
- **RODO Art. 7(1) persistence** — consent zapisywany w DB z wersją
- **Privacy Policy** — stałe z config, ToC, sekcja Discovery Sources, transfer USA z SCC
- **GEDCOM bulk import anti-DoS** — batch `tree.imported` event eliminuje per-person matching podczas importu
- **EmailService sanitizeHeader** — 9 testów regression guard

---

## STRIDE — aktualizacja

Z backend-4: **2K → 1K-NEW + 4 PARTIAL/POW**. Głównie weryfikacja — prawie wszystko PASS.

| Threat | Status vs backend-4 | Komentarz |
|--------|---------------------|-----------|
| **S** Consent proof | ✅ RESOLVED | K1 persistence działa |
| **T** SMTP header | ✅ RESOLVED | sanitizeHeader + 9 testów |
| **T** session_version | ✅ RESOLVED | getSessionVersion sync |
| **R** Geneteka audit log | ⏸ DEFERRED | TODO w stub (wymaga pełnej implementacji) |
| **I** error_log userId | ✅ RESOLVED | sampling + bez userId |
| **I** /health ts | ✅ RESOLVED | bez ts |
| **I** Art. 15 niekompletny | ✅ RESOLVED | tree-memberships + password-resets |
| **D** GEDCOM bulk DoS | ✅ RESOLVED | batch tree.imported |
| **D** reindexTree N+1 | ✅ RESOLVED | pre-fetch + chunking |
| **D** external timeout | ✅ RESOLVED | interface enforces |
| **E** opt-out bypass | ✅ RESOLVED | isDiscoveryOptedIn check |
| **E** GenetykaMatchSource path | ⚠️ **K1-NEW** | operator precedence — cleanup |
| **Legal** Discovery USA | ✅ RESOLVED | Privacy Policy sekcja 9 + SCC |

---

## Ocena końcowa

| Kategoria | K | P | D | + |
|-----------|---|---|---|---|
| **OWASP Top 10** | 0 | 1 | 1 | 10 |
| **RODO** | 0 | 0 | 0 | 6 |
| **NIS2** | 0 | 1 | 0 | 2 |
| **WCAG 2.1 AA** | 0 | 0 | 0 | 3 |
| **Code quality** | 1 | 2 | 2 | 5 |
| **RAZEM** | **1** | **4** | **3** | **26** |

**Werdykt:** **PASS WITH CONDITIONS**

### Porównanie audytów

| Audit | K | P | D | + | Status |
|-------|---|---|---|---|--------|
| #1 backend | 2 | 17 | 19 | 29 | Baseline |
| #2 backend-2 | 2 | 8 | 4 | — | Re-audit #1 |
| #3 backend-3 | 3 | 14 | 13 | 19 | Re-audit #2 |
| #4 backend-4 | 2 | 14 | 14 | 24 | Re-audit #3 + Discovery module |
| **#5 backend-5** | **1** | **4** | **3** | **26** | **Weryfikacja #4** ← **FINALNY TREND POZYTYWNY** |

**Obserwacja:** Pierwszy raz w całej sekwencji — **poniżej 5 poważnych problemów**, pozytywy najwyższe w historii (26). System zbliża się do production-ready.

### Blokuje deploy
- **K1-NEW** — GenetykaMatchSource operator precedence cleanup (code quality)

### Wymagane przed deploy
- **P1-NEW** — matchTreeAfterImport timeout 120→300s
- **P2-NEW** — incident-response scenariusz E etykieta
- **P3-NEW** — dodaj 10. test w EmailServiceTest

### Nice to have (D1-D3-NEW) — backlog

---

## Zalecenia procesowe

1. **Zrób naprawki (<15 min łącznie)** — wszystkie to quick wins
2. **Re-audit #6** opcjonalny — system wygląda na production-ready po naprawie K1-NEW i P1/P2/P3-NEW
3. **Pentest zewnętrzny** — teraz jest dobry moment (stable baseline)
4. **DPO review** Privacy Policy — uzupełnij env vars `COMPANY_*`, `DPO_EMAIL`
5. **SCC z FamilySearch Inc.** przed aktywacją external sources
