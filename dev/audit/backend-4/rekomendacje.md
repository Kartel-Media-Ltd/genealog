# Rekomendacje — Re-audit #4 backend

**Data:** 2026-04-08
**Zakres:** weryfikacja backend-3 (25 naprawek) + nowy moduł Discovery Sources

---

## Executive summary

- **Backend-3 zweryfikowany:** 25/25 poprawek PASS, 0 regresji ✅
- **Nowe znaleziska:** 2 krytyczne + 11 poważnych + 14 drobnych w module Discovery oraz w Privacy Policy/consent flow
- **Priorytet:** naprawić K1-K2 (RODO) + P1-P3 (Discovery DoS + opt-in) przed aktywacją external sources

---

## Tabela findings

| ID | Kategoria | Priorytet | Plik:linia | Effort | Quick win | Do zadania |
|----|-----------|-----------|------------|--------|-----------|------------|
| K1 | RODO Art. 7(1) | 🔴 KRYT | `users` table + AuthService | S | ❌ | TAK |
| K2 | RODO Rozdział V | 🔴 KRYT | `privacy.php:117` | M | ❌ | TAK |
| P1 | OWASP A04 DoS | 🟠 POW | `public/index.php:136-139` | M | ❌ | TAK |
| P2 | RODO Art. 7(3) | 🟠 POW | `MatchingService.php:94` | XS | ✅ | TAK |
| P3 | Performance DoS | 🟠 POW | `GlobalIndexService.php:148` | S | ❌ | TAK |
| P4 | OWASP A09 | 🟠 POW | `CrossTreeMatchSource.php:172` | XS | ✅ | TAK |
| P5 | OWASP A04 | 🟠 POW | `MatchSourceInterface.php` | S | ❌ | TAK |
| P6 | RODO Art. 15 | 🟠 POW | `DataExportService.php` | XS | ✅ | TAK |
| P7 | RODO Art. 30 | 🟠 POW | `GenetykaMatchSource.php` | S | ❌ | TAK |
| P8 | RODO Art. 13 | 🟠 POW | privacy.php placeholdery | S | ❌ | TAK |
| P9 | NIS2 | 🟠 POW | `incident-response.md` | S | ❌ | TAK |
| P10 | WCAG 3.3.2 | 🟠 POW | `register.php` consent | XS | ✅ | TAK |
| P11 | OWASP A01 | 🟠 POW | `GenetykaMatchSource.php` ctor | S | ❌ | TAK |
| D1 | A05 | 🟡 DROB | `/health` ts | XS | ✅ | TAK |
| D2 | Code quality | 🟡 DROB | MatchingService comment | XS | ✅ | TAK |
| D3 | A01 defense | 🟡 DROB | `LocalTreeMatchSource` | XS | ✅ | TAK |
| D4 | Performance | 🟡 DROB | Registry cache | XS | ✅ | TAK |
| D5 | Code quality | 🟡 DROB | PersonImportService isLiving | XS | ✅ | TAK |
| D6 | Code quality | 🟡 DROB | MatchingService magic strings | S | ❌ | TAK |
| D7 | UX | 🟡 DROB | DataExport README placeholder | XS | ✅ | TAK |
| D8 | WCAG 2.4.5 | 🟡 DROB | privacy.php ToC | S | ❌ | TAK |
| D9 | WCAG 1.3.1 | 🟡 DROB | AuthLayout lang | XS | ✅ | TAK |
| D10 | Log noise | 🟡 DROB | CrossTreeMatchSource | XS | ✅ | TAK |
| D11 | Schema | 🟡 DROB | migration 014 FK | XS | ✅ | TAK |
| D12 | Quality | 🟡 DROB | Zero testy Discovery | L | ❌ | TAK |
| D13 | Quality | 🟡 DROB | FingerprintService tests | S | ❌ | TAK |
| D14 | Performance | 🟡 DROB | `unindexTree` batching | S | ❌ | ODROCZONE |

---

## Quick wins (<90 min łącznie)

Zadania do zamknięcia w jednej sesji bez głębokiego refactoringu:

1. **P2** — `isDiscoveryOptedIn` check w `findAndNotifyMatches` (5 min) ← KLUCZOWE dla RODO Art. 7(3)
2. **P4** — usuń `userId` z error_log w CrossTreeMatchSource (3 min)
3. **P6** — dodaj `tree-memberships.json` i `password-resets.json` do eksportu (15 min)
4. **P10** — `aria-describedby` na consent checkbox (5 min)
5. **D1** — usuń `ts` z health endpoint (3 min)
6. **D2** — popraw komentarz existsRecentForPerson → existsRecentForLink (2 min)
7. **D3** — filtr visibility w LocalTreeMatchSource (5 min)
8. **D4** — memoization w MatchSourceRegistry (10 min)
9. **D5** — dokumentacja lub implementacja isLiving w local jsonSerialize (10 min)
10. **D7** — placeholder `[EMAIL DPO]` → config w DataExport README (5 min)
11. **D9** — weryfikuj i dodaj `lang="pl"` w AuthLayout (3 min)
12. **D10** — sample log / downgrade w CrossTreeMatchSource (5 min)
13. **D11** — dodaj FK w migration 014 (10 min)

**Łączny czas:** ~85 minut.
**Wartość:** 1 kluczowe POW (P2 — RODO Art. 7(3)), 2 ważne POW (P4, P6) + 10 drobnych.

---

## Priorytezacja napraw

### 🔴 Faza 1 — BLOCKING (przed dowolnym deployem)
**Cel:** eliminacja 2 krytycznych problemów RODO
**Czas:** ~4-6 godzin

1. **K1** — RODO Art. 7(1) persistence consent:
   - Migration 015: `users.terms_accepted_at TIMESTAMP NULL, terms_version VARCHAR(20) NULL`
   - `UserRepository::create()` — parametry
   - `AuthService::register()` — timestamp + version
   - `AuthController::processRegister` — przekazuje timestamp
   
2. **K2** — Privacy Policy dodaj sekcję Discovery Sources:
   - Update `src/views/pages/privacy.php` sekcja 8→9
   - Dodaj informację o FamilySearch (USA) + SCC
   - Dodaj informację o Geneteka (PL)
   - Update `terms.php` paragraf o Discovery

### 🟠 Faza 2 — IMPORTANT (ten sam sprint)
**Cel:** DoS mitigation + RODO enforcement
**Czas:** ~6-8 godzin

3. **P1** — Batch event dla bulk GEDCOM import (M)
4. **P2** — `isDiscoveryOptedIn` check w MatchingService (XS) ← quick win
5. **P3** — reindexTree N+1 + chunking (S)
6. **P4** — error_log sanitize userId (XS) ← quick win
7. **P5** — MatchSourceInterface timeout (S)
8. **P6** — DataExport tree_members + password_resets (XS) ← quick win
9. **P7** — Audit log w Discovery sources search (S)
10. **P8** — Privacy Policy placeholdery (S)
11. **P9** — incident-response.md scenariusz E (S)
12. **P10** — aria-describedby (XS) ← quick win
13. **P11** — GenetykaMatchSource path validation (S)

### 🟡 Faza 3 — NICE TO HAVE (następny sprint)
**Cel:** Quality, WCAG, observability
**Czas:** ~3-4 godziny

14. **D1-D11** — batch drobnych poprawek (patrz quick wins)
15. **D12** — podstawowe testy dla Discovery (priorytet: FingerprintService + EmailService)
16. **D13** — unit testy regression dla FingerprintService

### ⏭️ Odroczone (post-MVP)
- **D14** — unindexTree batching (wymaga async jobs)
- Async EventDispatcher (backend-2 Faza 3)
- Container.php activation
- Redis session handler
- GedcomService split

---

## Oversight — niezrealizowane z backend-3 pending USER_ACTION

Sprawdź przed deployment:

- [ ] Migracje 012, 013, 014 uruchomione?
- [ ] FK `fk_tree_members_invited_by` dodane ręcznie?
- [ ] Placeholdery w privacy.php i terms.php uzupełnione (K2 zajmie się tym częściowo)?
- [ ] Cron jobs z `infrastructure/cron.example` wdrożone?

---

## Metryki jakości

| Kategoria | Sprawdzono | Pass | Warn | Fail |
|-----------|------------|------|------|------|
| Weryfikacja backend-3 | 25 | 25 | 0 | 0 |
| OWASP Top 10 | A01-A10 | 5 | 3 | 2 |
| RODO Art. 5-32 | 10 | 6 | 2 | 2 |
| NIS2 Art. 21, 23 | 2 | 1 | 1 | 0 |
| WCAG 2.1 AA | 4 zasady | 3 | 1 | 0 |
| Architektura Discovery | 8 aspektów | 5 | 3 | 0 |

---

## Następne kroki

1. **Re-audit #5** po naprawie K1, K2 → `dev/audit/backend-5/` (weryfikacja + detect regresje)
2. **Zewnętrzny pentest** po zamknięciu K1-K2 + P1-P11
3. **Unit tests** dla Discovery przed aktywacją external sources
4. **Compliance review** z DPO:
   - Treść Privacy Policy
   - SCC z FamilySearch Inc. (jeśli aktywacja)
   - DPIA (Art. 35)
   - Rejestr Czynności Przetwarzania (Art. 30)

---

## Porównanie audytów

| Audit | K | P | D | + | Werdykt |
|-------|---|---|---|----|---------|
| #1 backend | 2 | 17 | 19 | 29 | PASS WITH CONDITIONS |
| #2 backend-2 | 2 | 8 | 4 | — | PASS WITH CONDITIONS |
| #3 backend-3 | 3 | 14 | 13 | 19 | PASS WITH CONDITIONS |
| **#4 backend-4** | **2** | **11** | **14** | **24** | **PASS WITH CONDITIONS** |

**Trend:** Krytyczne spadły (3→2), poważne nieco spadły (14→11), drobne lekko wzrosły (13→14).

**Pozytywny sygnał:** 9 dużych POZYTYWÓW vs baseline #1 (29) odzwierciedla że ponad połowa ryzyk OWASP/RODO jest już naprawiona. Pozostałe problemy są skoncentrowane w **nowym obszarze (Discovery Sources)** oraz **compliance gaps** (consent persistence, PP sekcje).

**Oczekiwanie dla audytu #5:** K=0 (wszystkie naprawione), P<5 (tylko odroczone z Faza 3 architektury), D<10.
