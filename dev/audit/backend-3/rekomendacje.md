# Rekomendacje — Re-audit #3 backend

**Data:** 2026-04-08
**Kategoryzacja:** priorytet × effort × quick-win flag

---

## Tabela findings

| ID | Kategoria | Priorytet | Plik:linia | Effort | Quick win | Do /ultra-workaholic |
|----|-----------|-----------|------------|--------|-----------|----------------------|
| K1 | OWASP A03 | 🔴 KRYT | `EmailService.php:42,90` | XS | ✅ | TAK |
| K2 | OWASP A01 + RODO | 🔴 KRYT | `AccountDeletionService.php:90-97` | S | ❌ | TAK |
| K3 | RODO Art. 13-14 | 🔴 KRYT | brak PP + `register.php` | M | ❌ | TAK |
| P1 | OWASP A03 (XSS) | 🟠 POW | `trees/persons/index.php:219` | S | ✅ | TAK |
| P2 | OWASP A08 | 🟠 POW | `GedcomController.php:22` | XS | ✅ | TAK |
| P3 | OWASP A01 | 🟠 POW | `InvitationService.php:82-101` | S | ❌ | TAK |
| P4 | OWASP A07 | 🟠 POW | `AuthService.php:25` | XS | ✅ | TAK |
| P5 | OWASP A05 (CSP) | 🟠 POW | `public/index.php:20` | L | ❌ | ODROCZONE |
| P6 | A05 + code quality | 🟠 POW | `GedcomController.php:114,165` | XS | ✅ | TAK |
| P7 | A04 (regresja) | 🟠 POW | `ProfileController.php:85-87` | XS | ✅ | TAK |
| P8 | Performance | 🟠 POW | `migrations/002_trees.sql` | XS | ✅ | TAK |
| P9 | DoS | 🟠 POW | `PersonController.php:63` | S | ❌ | TAK |
| P10 | RODO Art. 15 | 🟠 POW | `DataExportService.php:56` | M | ❌ | TAK |
| P11 | RODO Art. 18 | 🟠 POW | brak | M | ❌ | TAK |
| P12 | RODO Art. 35 | 🟠 POW | brak `docs/compliance/dpia.md` | L | ❌ | ODROCZONE (wymaga prawa) |
| P13 | NIS2 | 🟠 POW | `docs/security/...:77` | S | ❌ | TAK |
| P14 | NIS2 MFA | 🟠 POW | brak | L | ❌ | ODROCZONE |
| D1 | OWASP A03 (nit) | 🟡 DROB | `TreeRepository:183` | XS | ✅ | TAK |
| D2 | OWASP A01 (nit) | 🟡 DROB | `InvitationController:106,128` | XS | ✅ | TAK |
| D3 | Code quality | 🟡 DROB | `EmailService:40,89` | XS | ✅ | TAK |
| D4 | Defense in depth | 🟡 DROB | `PersonController (sortBy)` | XS | ✅ | TAK |
| D5 | DB schema | 🟡 DROB | `migrations/002_trees.sql` | XS | ✅ | TAK |
| D6 | Code quality | 🟡 DROB | `flash-messages.php:28-54` | XS | ✅ | TAK |
| D7 | Code quality | 🟡 DROB | `public/index.php:219,272` | S | ❌ | ODROCZONE (z Container) |
| D8 | Infra | 🟡 DROB | brak `.env.example` etc. | S | ✅ | TAK |
| D9 | Security | 🟡 DROB | `Session::start` 1% check | S | ❌ | TAK |
| D10 | WCAG | 🟡 DROB | `AppLayout.php` | XS | ✅ | TAK |
| D11 | WCAG | 🟡 DROB | `AppLayout.php:148-182` | XS | ✅ | TAK |
| D12 | RODO Art. 21 | 🟡 DROB | `settings.php` | S | ✅ | TAK |
| D13 | RODO Art. 30 | 🟡 DROB | brak `docs/compliance/rcp.md` | M | ❌ | ODROCZONE |

---

## Quick Wins (<30 min łącznie)

Zadania które można zamknąć w 1 sesji bez głębokiego refactoringu:

1. **K1** — email header sanitization (10 min)
2. **P2** — usunąć `application/octet-stream` z GEDCOM MIME whitelist (5 min)
3. **P4** — rate limit dla `/register` (5 min)
4. **P6** — `try/finally` dla tmpPath w GedcomController (5 min)
5. **P7** — fix ProfileController session_version (czytać z DB) (5 min)
6. **P8** — migration index `persons(tree_id, gedcom_xref)` (5 min)
7. **D1** — komentarz `// safe: int cast` przy `LIMIT {(int)$lim}` (3 min × 4 miejsca)
8. **D2** — walidacja formatu tokenu pending_invitation (5 min)
9. **D3** — MIME boundary `md5(uniqid())` → `bin2hex(random_bytes(16))` (3 min)
10. **D4** — walidacja `sortBy` w `PersonController` (5 min)
11. **D6** — flash-messages użycie Session API (10 min)
12. **D10** — WCAG skip link w AppLayout (5 min)
13. **D11** — `aria-live="polite"` na dropdown notyfikacji (5 min)

**Łączny czas:** ~75 minut.
**Wartość:** Eliminuje 1 KRYT (K1) + 5 POW (P2, P4, P6, P7, P8) + 6 DROB.

---

## Priorytezacja napraw

### 🔴 Faza 1 — BLOCKING (przed dowolnym deployem)
Cel: eliminuje 3 krytyczne problemy.
**Czas:** ~4-6 godzin.

1. **K1** — Email header sanitization (10 min)
2. **K2** — `findSoleOwnedTreeIds` transfer ownership (1h + test)
3. **K3** — Privacy Policy + Terms + Consent flow (3-4h)

### 🟠 Faza 2 — IMPORTANT (ten sam sprint)
Cel: eliminuje wszystkie XS/S powazne z testowalną poprawą.
**Czas:** ~4-6 godzin.

4. **P1** — XSS addslashes → JSON in Alpine.js (30 min)
5. **P2** — GEDCOM MIME whitelist (5 min)
6. **P3** — Email check przy invitation accept (20 min + test)
7. **P4** — Rate limit /register (5 min)
8. **P6** — GEDCOM tmp try/finally (5 min)
9. **P7** — ProfileController session_version sync (5 min)
10. **P8** — Migration index persons.gedcom_xref (5 min)
11. **P9** — buildPersonHierarchy depth cap (20 min)

### 🟠 Faza 3 — COMPLIANCE (ten sam sprint lub następny)
Cel: RODO/NIS2 minimum viable.
**Czas:** ~8-10 godzin.

12. **P10** — DataExportService rozszerzony (Art. 15) (3-4h)
13. **P11** — Art. 18 right to restriction (4-5h)
14. **P13** — `docs/operations/backup.md` (2h)

### 🟡 Faza 4 — NICE TO HAVE
Cel: jakość, drobne poprawki UX/WCAG.
**Czas:** ~2-3 godziny.

15. **D1-D6, D8-D12** — batch drobnych poprawek.

### ⏭️ Odroczone (post-MVP)
Wymagają osobnych iteracji lub konsultacji prawnych:
- **P5** — CSP nonce-based (L, wymaga refactor layoutów + Alpine.js inline handlers)
- **P12** — DPIA (wymaga audytu prawnego zewnętrznego)
- **P14** — MFA TOTP (L, wymaga nowej feature)
- **D7** — Controller deduplikacja (z backend-2 Faza 3 Container.php)
- **D13** — Rejestr Czynności Przetwarzania (wymaga DPO lub radcy)

---

## Następne kroki

### 1. Naprawa krytycznych
```bash
/ultra-workaholic dev/audit/backend-3
```
Zrealizuje Faza 1 + Faza 2 (14 zadań, ~10h pracy).

### 2. Re-audit po naprawach
```bash
/ultra-audit backend
```
Do katalogu `dev/audit/backend-4/` (piąty audyt w tym projekcie — typowe dla systemów przed first deploy).

### 3. Security review zewnętrzny
Po zamknięciu 3 krytycznych + 7 poważnych quick wins, zlecić pentest zewnętrzny przed first production deploy.

### 4. Compliance ustalenia z prawnikiem
- DPIA (Art. 35)
- Treść Privacy Policy (w kooperacji z DPO lub radcą)
- Rejestr Czynności Przetwarzania (Art. 30)
- Podstawa prawna przetwarzania osób trzecich (data subject nie jest userem — osoby w drzewach)

---

## Metryki kontroli jakości

**Pokrycie standardów:**

| Standard | Sprawdzono sekcji | Pass | Warn | Fail |
|----------|-------------------|------|------|------|
| OWASP Top 10 (A01-A10) | 10/10 | 3 | 5 | 2 |
| RODO (Art. 5, 13-21, 25, 30, 32, 33-35) | 11/11 | 5 | 4 | 2 |
| NIS2 (Art. 21, 23) | 2/2 | 0 | 2 | 0 |
| WCAG 2.1 AA (Perceivable, Operable, Understandable, Robust) | 4/4 | 3 | 1 | 0 |
| Architektura (coupling, scalability, testability) | 6/6 | 2 | 4 | 0 |

**Całość:** PASS WITH CONDITIONS — system nie jest production-ready, ale ścieżka do osiągnięcia gotowości jest jasna i realizowalna w 1-2 sprinty.

**Porównanie z audit #1 (backend):**
- **KRYT:** 2 → 3 (+1) — znaleziono K1 (SMTP) i K2 (sole-owned bug), ubyło K1+K2 z audit #1 (już naprawione)
- **POW:** 17 → 14 (-3) — naprawiono ~10, znaleziono ~7 nowych
- **DROB:** 19 → 13 (-6) — sukces w cleanupie

Trend pozytywny, ale pojawiają się nowe subtelne problemy wraz z pogłębianiem się kodu (regresja P7 to przykład).
