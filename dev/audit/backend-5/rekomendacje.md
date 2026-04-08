# Rekomendacje — Re-audit #5 backend

**Data:** 2026-04-08
**Zakres:** Weryfikacja backend-4 (25 napraw) + detekcja nowych problemów

---

## Executive summary

**Backend-4 zweryfikowany: 24/25 PASS + 1 PARTIAL, 0 regresji.**

- 🎯 **Trend pozytywny:** pierwszy audyt w sekwencji z K=1, P=4, D=3 (vs poprzednio 2K/14P/14D)
- 🚀 **Pozytywów najwięcej w historii** (26 vs max. 29 z baseline)
- ✅ **System zbliża się do production-ready** — wszystkie nowe znaleziska to quick wins (<15 min łącznie)

---

## Tabela findings

| ID | Kategoria | Priorytet | Plik:linia | Effort | Quick win |
|----|-----------|-----------|------------|--------|-----------|
| K1-NEW | Code quality | 🔴 KRYT | `GenetykaMatchSource.php:45` | XS (2 min) | ✅ |
| P1-NEW | Performance | 🟠 POW | `MatchingService.php:232` | XS (2 min) | ✅ |
| P2-NEW | NIS2 | 🟠 POW | `incident-response.md` | XS (2 min) | ✅ |
| P3-NEW | Testing | 🟠 POW | `EmailServiceTest.php` | XS (5 min) | ✅ |
| D1-NEW | Maintainability | 🟡 DROB | `MatchSourceRegistry.php` | S (15 min) | ❌ (future) |
| D2-NEW | UX | 🟡 DROB | `MatchingService::matchTreeAfterImport` | S (30 min) | ❌ (planowanie) |
| D3-NEW | Docs | 🟡 DROB | (duplikat P2-NEW) | — | — |

**Wszystkie krytyczne i poważne:** quick wins.

---

## Quick wins (<15 min łącznie)

### 1. K1-NEW — Nawiasy w GenetykaMatchSource

**Plik:** `src/Services/Discovery/Sources/GenetykaMatchSource.php:45`

```php
// PRZED:
if ($real === false || $allowed === false || !str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && $real !== $allowed) {

// PO:
if ($real === false
    || $allowed === false
    || (!str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && $real !== $allowed)
) {
```

Czas: 2 min

---

### 2. P1-NEW — matchTreeAfterImport timeout 120→300s

**Plik:** `src/Services/Discovery/MatchingService.php:232`

```php
// PRZED:
@set_time_limit(120);

// PO:
@set_time_limit(300); // spójnie z GlobalIndexService::reindexTree
```

Czas: 2 min

---

### 3. P2-NEW — incident-response scenariusz E etykieta

**Plik:** `docs/security/incident-response.md`

Sekcja 5 traktuje o external dependency outage, ale brak jawnego nagłówka `### E) ...`. Rename dla spójności z konwencją A-D.

Czas: 2 min

---

### 4. P3-NEW — Dodaj 10. test EmailService (tab injection)

**Plik:** `tests/Unit/Services/EmailServiceTest.php`

```php
public function testSanitizeHeaderHandlesTabInjection(): void
{
    // Tab nie jest w whitelist usuwania (CR/LF/NULL) — dokumentujemy intended behavior
    $result = $this->sanitize->invoke($this->service, "Jan\tKowalski");
    $this->assertSame("Jan\tKowalski", $result);

    // Ale nie powinno pozwalać na header injection — tab nie jest separatorem nagłówka SMTP
}
```

Czas: 5 min

---

**Łącznie quick wins:** ~11 minut.

Po ich wdrożeniu — re-audit #6 jest **opcjonalny**, system osiąga status "PASS" (bez "WITH CONDITIONS").

---

## Backlog (post-deploy)

### D1-NEW — MatchSourceRegistry unregister()
Niski priorytet. Dodać gdy pojawi się potrzeba dynamicznej rekonfiguracji.

### D2-NEW — matchTreeAfterImport limit powiadomień
Dodaj licznik + break po N=10 powiadomień. User widzi "Dopasowano X osób — kliknij aby zobaczyć listę". Poprawa UX przy dużych importach.

### Deferred z backend-3/4
- **P5** CSP nonce-based
- **P12** DPIA
- **P14** MFA TOTP
- **D13** AccountDeletionServiceTest (wymaga Database mock)
- **D14** unindexTree batching (wymaga async jobs)

### Deferred z backend-2 Faza 3 (architektura)
- Container.php activation
- Async EventDispatcher + job queue
- Redis session handler
- GedcomService split (Parser/Importer/Exporter)

---

## Compliance status

| Standard | Ocena | Trend |
|----------|-------|-------|
| **OWASP Top 10** | PASS | ↑↑ |
| **RODO** | PASS (warunkowo: uzupełnij env vars COMPANY_*/DPO_EMAIL) | ↑↑ |
| **NIS2** | PASS WITH CONDITIONS (P2-NEW etykieta) | ↑ |
| **WCAG 2.1 AA** | PASS | ↑ |
| **Architektura** | PASS WITH CONDITIONS (K1-NEW precedence) | ↑ |

---

## Następne kroki

### Sprint 1 (15 minut)
1. Napraw K1-NEW, P1-NEW, P2-NEW, P3-NEW (quick wins)
2. Uruchom PHPUnit — powinno przejść 71/71 (dodany 1 test)

### Sprint 2 (USER_ACTION — bez mojej pracy)
1. Uzupełnij `.env.local`: `COMPANY_NAME`, `COMPANY_ADDRESS`, `COMPANY_NIP`, `DPO_EMAIL`, `CONTACT_EMAIL`, `SERVER_LOCATION`
2. DPO review Privacy Policy + Terms
3. SCC z FamilySearch Inc. (jeśli aktywujesz `FAMILYSEARCH_CLIENT_ID`)
4. External pentest (teraz jest stable baseline)

### Sprint 3 (post-deploy, optymalizacje)
1. AccountDeletionServiceTest (D13 backend-4)
2. Container.php activation (backend-2 Faza 3)
3. Font Awesome migration (plan gotowy w `dev/active/font-awesome/`)
4. Tree sharing feature (0/64 zadań)

---

## Podsumowanie jakości

**Czego trzeba żeby audit #6 był K=0, P=0?**

1. Zrób 4 quick wins (<15 min)
2. Uzupełnij env vars RODO (1 h prawnicza)
3. Dodaj etykietę "Scenariusz E" w incident-response

To jest **pierwszy raz w całej sekwencji audytów** kiedy finisz w zasięgu ręki bez dużego refactoringu.

**Ogólny kierunek:** po zamknięciu K1-NEW i P1-P3-NEW system jest gotowy do zewnętrznego pentesta i first production deploy.
