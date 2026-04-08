# Audyt jakości kodu — Genealog Backend
**Data:** 2026-04-07 | **Scope:** Metryki, dependency analysis, DRY, dead code, tech debt
**Werdykt:** PASS WITH CONDITIONS — 2 Poważne, 3 Drobne

---

## 1. Metryki projektu

### Struktura plików

| Warstwa | Liczba plików | Szacowany LOC |
|---------|--------------|---------------|
| Controllers (15) | 15 plików | ~2 000 LOC |
| Services (13+) | 13 plików | ~2 300 LOC |
| Repositories (8) | 8 plików | ~900 LOC |
| Core (12) | 12 plików | ~500 LOC |
| Models (4) | 4 pliki | ~200 LOC |
| Middleware (2) | 2 pliki | ~150 LOC |
| Views (atoms/molecules/organisms/templates/pages) | ~40+ plików | ~1 500 LOC |
| **Łącznie** | **~95 plików** | **~7 550 LOC** |

### Top 5 największych klas

| Klasa / plik | Szacowany LOC | Uwagi |
|---|---|---|
| `GedcomService.php` | 775 LOC | God class — parser + importer + exporter w jednym |
| `PersonController.php` | ~250 LOC | 9 zależności, jednorodny ale duży |
| `DiscoveryController.php` | ~230 LOC | 9 zależności, niejednorodny — do podziału |
| `AuthService.php` | ~200 LOC | Akceptowalny |
| `GlobalIndexService.php` | ~180 LOC | Akceptowalny, złożona logika RODO |

---

## 2. Analiza zależności (Composer)

### Brak `composer audit` w CI/CD
Projekt nie ma GitHub Actions ani żadnego pipeline CI. Brak automatycznej weryfikacji CVE w zainstalowanych pakietach. Jednorazowo uruchomiony `composer audit` powinien być częścią każdego buildu.

**Zalecenie:** Dodać `.github/workflows/ci.yml` z krokiem `composer audit --no-dev`.

### Brak `fisharebest/gedcom`
CLAUDE.md rekomenduje `fisharebest/gedcom` (MIT) jako gotowy parser GEDCOM. Zamiast tego projekt posiada własny `GedcomService.php` (775 LOC). Skutki:
- Większa powierzchnia ataku przy parsowaniu złośliwych plików `.ged`.
- Brak testów jednostkowych dla parsera.
- Konieczność utrzymania zgodności ze standardem GEDCOM 5.5.1 ręcznie.

### Zidentyfikowane pakiety Composer (szacunkowo)
Projekt używa minimalnego zestawu zależności (PHP bez frameworka). Brak znanych podatnych pakietów na podstawie opisu — jednak weryfikacja możliwa dopiero po `composer audit`.

---

## 3. Naruszenia DRY — UUID generation

### Problem (Poważne — K-P1)
Generowanie UUID zduplikowane w **8+ klasach**:

| Plik | Linia | Fragment kodu |
|------|-------|---------------|
| `AuthService.php` | 96 | `bin2hex(random_bytes(16))` |
| `PersonService.php` | 158 | `bin2hex(random_bytes(16))` |
| `GlobalIndexService.php` | 200 | `bin2hex(random_bytes(16))` |
| `NotificationService.php` | 108 | `bin2hex(random_bytes(16))` |
| `TreeService.php` | 55 | `bin2hex(random_bytes(16))` |
| `GedcomService.php` | 768 | `bin2hex(random_bytes(16))` |
| `InvitationService.php` | 139 | `bin2hex(random_bytes(16))` |
| `DiscoveryRepository.php` | 111 | `bin2hex(random_bytes(16))` |

### Rozwiązanie
Wyekstrahować do `src/Core/Uuid.php`:
```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Uuid
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

Koszt naprawy: XS (~20 min, proste search-replace).

---

## 4. Martwy kod

### AuthMiddleware — session_version check (Poważne)
`AuthMiddleware.php:28-38` zawiera logikę weryfikacji `session_version` przez porównanie z wartością z bazy danych. Kod jest **nigdy nie wykonywany** ponieważ `public/index.php:111` tworzy middleware bez `$userRepo`:

```php
new AuthMiddleware($response)  // brak drugiego argumentu
```

Efekt: zablokowanie konta w panelu admina nie unieważnia aktywnych sesji przez do 8 godzin. Martwy kod stwarzający fałszywe poczucie bezpieczeństwa.

### Stub password reset
`public/index.php:155-156` — handler `POST /forgot-password` zwraca `ok` bez żadnej logiki. Cała funkcjonalność resetowania hasła jest nieistniejąca pomimo widocznego UI.

---

## 5. GedcomService — God Class

### Problem (Drobne — K-D2)
`GedcomService.php` (775 LOC) łączy trzy odrębne odpowiedzialności:
1. **Parsowanie** — przetwarzanie surowego tekstu GEDCOM na struktury PHP.
2. **Import** — zapis sparsowanych danych do bazy MySQL.
3. **Eksport** — generowanie pliku GEDCOM z danych w bazie.

Narusza zasadę Single Responsibility (SRP). Trudny do testowania jednostkowego, trudny do rozszerzania.

### Rekomendacja podziału:
```
src/Services/Gedcom/
├── GedcomParser.php     # GEDCOM text → PHP arrays
├── GedcomImporter.php   # PHP arrays → MySQL (używa repozytoriów)
└── GedcomExporter.php   # MySQL → GEDCOM text (używa repozytoriów)
```

---

## 6. EventDispatcher — static state

### Problem (Drobne — K-D3)
`EventDispatcher` używa stanu statycznego (static properties lub singleton pattern). Utrudnia testowanie jednostkowe — testy wpływają na siebie nawzajem przez wspólny stan. Szczególnie problematyczne przy testowaniu `MatchingService` i `GlobalIndexService`, które są wyzwalane przez eventy.

### Rekomendacja:
Wstrzyknięcie instancji `EventDispatcher` przez konstruktor (Dependency Injection) zamiast statycznego dostępu.

---

## 7. Brak strict_types w widokach

### Problem (Drobne — K-D4)
13 plików widoku w `src/views/` nie zawiera `declare(strict_types=1)`. Wszystkie pliki PHP w projekcie powinny mieć deklarację strict types zgodnie z PSR-12 i konwencjami projektu.

**Pliki do poprawki:** wszystkie `.php` w `src/views/atoms/`, `src/views/molecules/`, `src/views/organisms/`, `src/views/templates/`, `src/views/pages/`.

Uwaga: pliki widoku nie zawierają logiki biznesowej, więc ryzyko błędów typowania jest niskie — ale brak deklaracji jest niespójny z resztą kodu.

---

## 8. Niespójność BCRYPT_COST

### Problem (Drobne — K-D5 / R-D2)
`PASSWORD_BCRYPT` z cost=12 zdefiniowany w `AuthService.php` jest hardkodowany ponownie w `ProfileController.php:78` przy zmianie hasła. Dwa niezależne miejsca — ryzyko niespójności po zmianie kosztu.

### Naprawa:
Wyekstrahować do stałej w konfiguracji lub klasie:
```php
// src/Core/Security.php
final class Security
{
    public const BCRYPT_COST = 12;
}
```

---

## 9. DiscoveryController — bezpośredni dostęp do DB

### Problem (Architektura)
`DiscoveryController` zawiera bezpośrednie wywołania `$db->fetchOne()` zamiast użycia warstwy repozytoriów. Narusza warstwowanie MVC — kontroler nie powinien wykonywać zapytań SQL bezpośrednio.

Ponadto kontroler ma 9 zależności i obsługuje niejednorodne funkcje: wyszukiwanie, import wyników, odrzucanie sugestii, zarządzanie ustawieniami. Powinien zostać podzielony.

---

## 10. Tech debt — zestawienie

| Obszar | Dług | Priorytet |
|--------|------|-----------|
| UUID generation | 8 duplikatów, 1 klasa pomocnicza | Wysoki |
| GedcomService god class | 775 LOC, 3 SRP violations | Średni |
| EventDispatcher static | Brak testowalności | Średni |
| strict_types w views | 13 brakujących deklaracji | Niski |
| BCRYPT_COST duplikacja | 2 hardcoded wartości | Niski |
| DiscoveryController SQL | Bezpośredni DB access w kontrolerze | Średni |
| composer audit | Brak CI check | Wysoki |
| fisharebest/gedcom | Własny parser 775 LOC zamiast MIT library | Średni |
| Password strength | Min 8 znaków, brak entropy | Niski |
| Strict types views | 13 plików | Niski |

---

## 11. Obszary pozytywne

- **Testy jednostkowe istnieją** — `tests/Unit/` pokrywa: Csrf, Router, Session, Request, AuthService. Dobra baza do rozszerzenia.
- **Repozytoria czyste** — 8 repozytoriów, każde z jedną odpowiedzialnością, PDO w każdym.
- **Core minimalistyczny** — Router, Session, CSRF, Request, Response w ~500 LOC. Zrozumiały.
- **Models proste** — 4 modele bez logiki biznesowej, czysty Value Object pattern.
- **Brak frameworka** — mniejszy attack surface, pełna kontrola nad kodem.
