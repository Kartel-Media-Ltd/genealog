# Backend — zadania naprawcze (re-audit #5)

**Data:** 2026-04-08
**Format:** atomowe zadania 2-15 min
**Zakres:** 4 quick wins (K1-NEW + P1-NEW + P2-NEW + P3-NEW)

---

## Status

- [x] Faza 1 — Quick wins (4 zadania, ~15 min łącznie)
- [ ] Faza 2 — DROBNE (odroczone, backlog)

---

## Faza 1 — Quick wins (~15 min)

### ZAD-1.1 ✅ — K1-NEW: Nawiasy w GenetykaMatchSource operator precedence
**Plik:** `src/Services/Discovery/Sources/GenetykaMatchSource.php:45`
**Czas:** 2 min

Zamień:
```php
if ($real === false || $allowed === false || !str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && $real !== $allowed) {
```

na:
```php
if ($real === false
    || $allowed === false
    || (!str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && $real !== $allowed)
) {
```

**Cel:** jawne nawiasy dla czytelności + ochrona przed regresją przy refaktoringu.

**Weryfikacja:** `php -l src/Services/Discovery/Sources/GenetykaMatchSource.php` → no syntax errors.

---

### ZAD-1.2 ✅ — P1-NEW: matchTreeAfterImport timeout 120→300s
**Plik:** `src/Services/Discovery/MatchingService.php:232`
**Czas:** 2 min

```php
// PRZED:
@set_time_limit(120);

// PO:
@set_time_limit(300); // spójnie z GlobalIndexService::reindexTree
```

**Cel:** przy 100 osobach × 10s (local+cross worst-case) total może przekroczyć 120s → timeout. 300s daje bezpieczny zapas.

---

### ZAD-1.3 ✅ — P2-NEW: Etykieta "Scenariusz E" w incident-response.md
**Plik:** `docs/security/incident-response.md`
**Czas:** 2 min

Sekcja omawiająca external dependency outage powinna mieć nagłówek:
```markdown
### E) Awaria zewnętrznej zależności (Discovery Sources)
```

spójnie z istniejącymi A)/B)/C)/D).

**Weryfikacja:** `grep "Scenariusz E" docs/security/incident-response.md` lub `grep "E) Awaria"`.

---

### ZAD-1.4 ✅ — P3-NEW: 10. test w EmailServiceTest (tab injection)
**Plik:** `tests/Unit/Services/EmailServiceTest.php`
**Czas:** 5 min

Dodaj po `testSanitizeHeaderHandlesOnlyWhitespaceInjection`:

```php
public function testSanitizeHeaderPreservesTabCharacter(): void
{
    // Tab NIE jest w whitelist usuwania — sanitizeHeader celowo usuwa tylko CR/LF/NULL.
    // Ten test dokumentuje intended behavior: tab nie jest niebezpieczny w kontekście
    // SMTP header injection (tylko CR/LF rozdziela nagłówki RFC 5322).
    $result = $this->sanitize->invoke($this->service, "Jan\tKowalski");
    $this->assertSame("Jan\tKowalski", $result);
}
```

**Weryfikacja:**
```bash
./vendor/bin/phpunit tests/Unit/Services/EmailServiceTest.php
# Oczekiwane: 10/10 passed
```

---

## Faza 2 — DROBNE (backlog, nie teraz)

### ZAD-2.1 — D1-NEW: MatchSourceRegistry::unregister() (future)
Dodać gdy pojawi się potrzeba dynamicznej rekonfiguracji runtime. Niski priorytet.

### ZAD-2.2 — D2-NEW: matchTreeAfterImport limit powiadomień
Dodaj licznik + break po N=10 powiadomień per-import-batch.

```php
// W matchTreeAfterImport pętla:
$notificationsSent = 0;
$maxNotifications = 10;

foreach ($persons as $person) {
    if ($notificationsSent >= $maxNotifications) {
        error_log("[MatchingService] matchTreeAfterImport: reached notification cap ({$maxNotifications}) for tree {$treeId}");
        break;
    }
    if ($person->isLiving) continue;
    try {
        $this->findAndNotifyMatches($person, $userId);
        // findAndNotifyMatches może wysłać 0 lub 1 notyfikację — zliczaj tylko gdy miało match
        // (wymaga refactoru — zwrot bool z findAndNotifyMatches)
    } catch (\Throwable $e) {
        error_log(...);
    }
}
```

---

## Podsumowanie

| Faza | Zadań | Czas | Typ |
|------|-------|------|-----|
| Faza 1 Quick wins | 4 | ~15 min | K1-NEW, P1-NEW, P2-NEW, P3-NEW |
| Faza 2 Backlog | 2 | ~30 min | D1-NEW, D2-NEW |

**Po Fazie 1 — opcjonalny re-audit #6.** System osiąga status **PASS** bez warunków. Gotowy do zewnętrznego pentesta.
