# Rekomendacje — re-audyt backendu Genealog
**Data:** 2026-04-07
**Podstawa:** wyniki agentów A (OWASP), B (RODO/NIS2), C (architektura)

---

## Priorytety napraw

### BLOCKING — naprawy wymagane przed każdym deploy (produkcja/staging)

| ID | Problem | Plik:linia | Est. |
|----|---------|-----------|------|
| F-01 | Aktywne tokeny resetu hasła po anonimizacji konta — RODO Art. 17 | `AccountDeletionService.php:37` | 15 min |
| F-02 | Kolizja shortId w anonymize() — RODO Art. 17, drugi user nie może być usunięty | `UserRepository.php:125` | 5 min |
| P-NEW-01 | ProfileController::changePassword() — próg 8 zamiast 12, brak validatePasswordStrength | `ProfileController.php:68` + `profile.php:172,197` | 20 min |
| P-NEW-02 | Brak incrementSessionVersion() po zmianie hasła | `ProfileController.php:78` | 5 min |

**Łączny czas BLOCKING: ~45 minut.**

Uzasadnienie blokowania: F-01 umożliwia dostęp do "usuniętego" konta przez stary token. F-02 rzuca wyjątek uniemożliwiając realizację prawa do usunięcia dla części użytkowników. P-NEW-01/02 są sprzeczne z założeniami bezpieczeństwa sesji (session_version++) i polityką haseł (zdefiniowaną i egzekwowaną przy rejestracji i resecie).

---

### IMPORTANT — naprawy w bieżącym sprincie

| ID | Problem | Plik:linia | Est. |
|----|---------|-----------|------|
| P-NEW-03 | GET /settings/export-data bez CSRF — rate-limit DoS | `index.php:203`, `SettingsController.php:103` | 1h |
| D-NEW-01 | AdminController 5× catch z getMessage() w flash — ryzyko info disclosure | `AdminController.php:109,124,139,154,197` | 30 min |
| F-03 | Rate limit eksportu per IP zamiast per user — RODO Art. 20 | `SettingsController.php:109` | 10 min |
| F-04 | Brak try/finally przy tmpDir — wyciek plików tymczasowych — RODO Art. 5(1)(f) | `DataExportService.php:45-128` | 30 min |
| F-05 | Brak konfiguracji cron dla cleanup audit logu — RODO Art. 5(1)(e) | `bin/cleanup-audit-log.php` brak `infrastructure/cron.example` | 20 min |
| F-06 | `composer audit \|\| true` nie blokuje pipeline — NIS2 Art. 21 | `.github/workflows/ci.yml:34` | 10 min |
| P-NEW-04 | Brak incrementSessionVersion() po zmianie e-mail | `ProfileController.php::changeEmail():107` | 5 min |

**Łączny czas IMPORTANT: ~2h 45 min.**

---

### NIT — poprawki jakościowe (ten sam sprint lub następny)

| ID | Problem | Plik:linia | Est. |
|----|---------|-----------|------|
| D-NEW-02 | MediaService::uploadPhoto — bin2hex zamiast Uuid::generate() | `MediaService.php:54` | 5 min |
| D-NEW-03 | HomeController — silent fail bez error_log | `HomeController.php:28,37` | 10 min |
| schema | password_resets brak cleanup wygasłych tokenów | brak skryptu cron | 20 min |

---

## Quick wins — naprawy do zrobienia natychmiast (< 30 min łącznie)

1. **F-02** (`UserRepository.php:125`) — 1 linia: zamień `substr($id, 0, 8)` na `$id`.
2. **F-01** (`AccountDeletionService.php`) — 1 linia: dodaj `DELETE FROM password_resets WHERE user_id = ?` przed anonymize().
3. **P-NEW-02** (`ProfileController.php:78`) — 1 linia: dodaj `incrementSessionVersion()`.
4. **P-NEW-04** (`ProfileController.php:changeEmail():107`) — 1 linia: dodaj `incrementSessionVersion()`.
5. **F-03** (`SettingsController.php:109`) — 1 linia: zamień klucz rate limitera na `user_id`.
6. **F-06** (`.github/workflows/ci.yml:34`) — usuń `|| true`.
7. **D-NEW-02** (`MediaService.php:54`) — 1 linia: zamień na `Uuid::generate()`.

**Łącznie: ~20 minut, 7 linii kodu.**

---

## Compliance gaps — naruszenia RODO i NIS2 wymagające dokumentacji

| Artykuł | Naruszenie | Fix | Docelowy termin |
|---------|-----------|-----|----------------|
| RODO Art. 17 | F-01 — tokeny resetu po anonimizacji | DELETE przed anonymize | BLOCKING |
| RODO Art. 17 | F-02 — kolizja shortId blokuje erasure | pełny UUID | BLOCKING |
| RODO Art. 20 | F-03 — rate limit per IP | per user_id | Sprint bieżący |
| RODO Art. 5(1)(f) | F-04 — wyciek pliku tmp | try/finally | Sprint bieżący |
| RODO Art. 5(1)(e) | F-05 — brak cron dla retention | cron.example | Sprint bieżący |
| NIS2 Art. 21 | F-06 — CVE nie blokuje CI | usuń `\|\| true` | Sprint bieżący |

---

## Long-term — architektura (następne 2-3 sprinty)

### Priorytet 1: Aktywacja Container.php (est. 4h)
`src/Core/Container.php` jest gotowy — nie jest używany. Migracja `public/index.php` z 56 ręcznych `new` na `$container->get()`:
- Eliminuje 3× `InvitationController`, 2× `DiscoveryController`, 2× `AdminController`,
- Umożliwia lazy loading i singleton per request,
- Warunek wstępny dla EventDispatcher refactor.

### Priorytet 2: GedcomService przez DI (est. 2h)
`src/Services/GedcomService.php` — 775 LOC, tworzony przez `new` w `TreeController` i wewnątrz `DataExportService`. Po aktywacji kontenera: zarejestrować jako singleton, wstrzykiwać przez konstruktor. Eliminuje shotgun surgery przy zmianie konstruktora.

### Priorytet 3: EventDispatcher static → instance (est. 8h)
Globalny stan statyczny uniemożliwia unit testy event-driven logiki (fingerprint matching, powiadomienia). Refaktor wymaga:
1. Dodania `EventDispatcher` jako DI singleton,
2. Wstrzyknięcia do wszystkich klas korzystających ze statycznej wersji.

Warunek wstępny: Priorytet 1 (kontener).

### Priorytet 4: Redis session handler (est. 6h + infrastruktura)
Plikowy handler sesji blokuje horizontal scaling. Redis handler:
- `session.save_handler = redis` + `session.save_path = tcp://redis:6379`,
- Centralne unieważnianie sesji przez `session_version` nadal działa (klucz sesji),
- Wymaga: Redis w Docker Compose + `ext-redis` lub `predis/predis`.

### Priorytet 5: GedcomService podział (est. 16h)
775 LOC w jednym pliku obsługuje: parser GEDCOM, mapper do Person/Relationship, builder eksportu, walidator. Podział na:
- `GedcomParser` — czyste parsowanie linii do AST,
- `GedcomImporter` — mapper AST → Eloquent entities,
- `GedcomExporter` — builder formatu 5.5.1.

---

## Pozytywne obserwacje

Warto odnotować co działa poprawnie:
- Naprawy z pierwszego audytu są solidne jakościowo (95% kompletności).
- GEDCOM security (rate limit, set_time_limit, filter living, audit log) — wzorcowe.
- Migracja 010 (AccountDeletion, FK SET NULL) — dobra architektura transakcyjna.
- Password reset flow (anti-enumeration, jednorazowy token, session_version++) — kompletny.
- AdminMiddleware (session_version check) — poprawny.
- UUID refaktoryzacja (11 plików) — konsekwentna, poza jednym pominięciem (MediaService).

---

## Szacunkowy roadmap

```
Dzień 1 (BLOCKING)   45 min  → F-01, F-02, P-NEW-01, P-NEW-02
Dzień 1-2 (IMPORTANT) ~3h   → P-NEW-03, D-NEW-01, F-03..F-06, P-NEW-04
Sprint bieżący (NIT)  ~35 min → D-NEW-02, D-NEW-03, password_resets cleanup
Sprint+1 (arch)        4h    → Container.php aktywacja
Sprint+2 (arch)        2h    → GedcomService DI
Sprint+3 (arch)       24h    → EventDispatcher + Redis sessions
```
