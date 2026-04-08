# Audyt cyberbezpieczeństwa — re-audyt backendu Genealog
**Data:** 2026-04-07
**Werdykt:** PASS WITH CONDITIONS
**Poprzedni audyt:** `dev/audit/backend/`
**Zakres:** walidacja 60/73 fixów + nowe findings

---

## 1. Walidacja napraw z poprzedniego audytu

| ID | Status | Plik:linia | Komentarz |
|----|--------|-----------|-----------|
| K1 AuthMiddleware userRepo | ✅ FIXED | `public/index.php:118` | `$userRepo` wstrzyknięty poprawnie |
| K2 AccountDeletionService + migracja 010 | ✅ FIXED | `src/Services/AccountDeletionService.php` | Transakcja, anonimizacja, FK SET NULL |
| P1 catch refactor — Auth/Tree/Person/Rel | ✅ FIXED | wiele kontrolerów | Refaktoryzacja kompletna dla 4 kontrolerów |
| P1 AdminController catch | ⚠️ PARTIAL | `src/Controllers/AdminController.php:109,124,139,154,197` | **5 metod nadal używa `catch (\Exception)` z `$e->getMessage()` w flash bez `error_log`** |
| P2 password reset | ✅ FIXED | `src/Services/PasswordResetService.php` | Pełny flow działa |
| P5 GEDCOM rate limit | ✅ FIXED | `src/Controllers/TreeController.php` | Rate limit + `set_time_limit` + audit |
| P GEDCOM filter living | ✅ FIXED | `src/Services/GedcomService.php::buildIndi()` | Żyjące osoby filtrowane przy eksporcie |
| R3 invite rate limit | ✅ FIXED | `src/Repositories/InvitationRepository.php` | Limit 20/h |
| D6 AdminMiddleware | ✅ FIXED | `src/Core/AdminMiddleware.php` | `userRepo` + `session_version` |
| UUID extraction | ✅ FIXED | 11+ plików | Single source of truth |
| BCRYPT_COST | ✅ FIXED | `src/Services/AuthService.php`, ProfileService, PasswordResetService | Stała wspólna |
| validatePasswordStrength register | ✅ FIXED | `src/Controllers/AuthController.php::register()` | Wywoływane |

**Podsumowanie walidacji:** 11/12 fixów kompletnych, 1 częściowy (AdminController).

---

## 2. Nowe findings — bezpieczeństwo

### P-NEW-01 — Niespójna walidacja siły hasła w ProfileController
**Priorytet:** POWAŻNY (P)
**Plik:** `src/Controllers/ProfileController.php:68`

Metoda `changePassword()` sprawdza `mb_strlen($new) < 8` zamiast 12 i nie wywołuje `validatePasswordStrength()`. Widok `src/views/pages/profile.php:172,197` zawiera `minlength="8"` i komunikat "co najmniej 8 znaków".

Skutek: zalogowany użytkownik może ustawić hasło słabsze niż przy rejestracji lub resecie. Niespójność z `AuthController::register()` i `PasswordResetService::complete()`, które wymuszają 12 znaków + walidację siły.

**Fix:** zmienić próg na 12 i wywołać `validatePasswordStrength()`. Zaktualizować `minlength` i komunikat w widoku.

---

### P-NEW-02 — Brak `incrementSessionVersion()` po zmianie hasła
**Priorytet:** POWAŻNY (P)
**Plik:** `src/Controllers/ProfileController.php:78`

Po pomyślnej zmianie hasła przez `changePassword()` brakuje wywołania `incrementSessionVersion()`. `PasswordResetService::complete()` to robi (`src/Services/PasswordResetService.php`) — ProfileController nie. Konsekwencja: skradzione sesje pozostają ważne po zmianie hasła przez prawowitego właściciela konta.

**Fix:** dodać `$this->sessionVersionService->incrementSessionVersion($userId)` po zmianie hasła.

---

### P-NEW-03 — GET `/settings/export-data` z efektem ubocznym (brak CSRF)
**Priorytet:** POWAŻNY (P)
**Plik:** `public/index.php:203`, `src/Controllers/SettingsController.php:103`

Endpoint eksportu danych jest obsługiwany przez `GET`, który generuje ZIP i nalicza rate limit (1 eksport/24h). Żądanie GET nie wymaga tokenu CSRF. Atakujący może osadzić `<img src="https://app.example/settings/export-data">` na złośliwej stronie i:
1. Wymusić wygenerowanie ZIP (ingerencja w zasoby),
2. Wyczerpać limit dzienny użytkownika — legitymny eksport zablokowany przez 24h (rate-limit DoS).

Endpoint nie zwraca danych w odpowiedzi atakującemu (SameSite cookies), ale efekt uboczny (zużycie rate limitu) jest realnym problemem.

**Fix:** zmienić na POST z formularzem CSRF lub zastosować własny mechanizm jednorazowego tokenu.

---

### P-NEW-04 — Brak `incrementSessionVersion()` po zmianie e-mail
**Priorytet:** POWAŻNY (P)
**Plik:** `src/Controllers/ProfileController.php::changeEmail():107`

Zmiana e-mail (krytyczny identyfikator konta) nie powoduje unieważnienia pozostałych sesji. Analogicznie jak P-NEW-02, skradzione lub równoległe sesje zachowują ważność po aktualizacji adresu.

**Fix:** wywołać `incrementSessionVersion()` po potwierdzeniu zmiany e-mail.

---

## 3. Nowe findings — jakość kodu (defensywna)

### D-NEW-01 — AdminController: 5× `catch (\Exception)` z getMessage() w flash
**Plik:** `src/Controllers/AdminController.php:109,124,139,154,197`

Naprawione w innych kontrolerach (P1), pominięte w AdminController. Problemy:
- `$e->getMessage()` eksponowany w flash message — może ujawnić nazwy tabel, szczegóły SQL, ścieżki plików,
- brak `error_log()` — błędy są nieme dla administratorów systemu.

**Fix:** zamienić 5 bloków catch na logowanie + ogólny komunikat. Batch — ~30 minut pracy.

---

### D-NEW-02 — `MediaService::uploadPhoto:54` — brak UUID
**Plik:** `src/Services/MediaService.php:54`

Jedyne pominięte miejsce w refaktoryzacji UUID. Nadal używa `bin2hex(random_bytes(16))` zamiast `Uuid::generate()`.

**Fix:** 1 linia — zastąpić wywołaniem `Uuid::generate()`.

---

### D-NEW-03 — `HomeController` — silent fail bez logowania
**Plik:** `src/Controllers/HomeController.php:28,37`

Dwa bloki `catch (\Exception)` z pustym ciałem — błędy znikają bez śladu. Trudne do debugowania na produkcji.

**Fix:** dodać `error_log()` w obu blokach.

---

## 4. Pozytywne obserwacje

- **GEDCOM security** — kompletny: rate limit, `set_time_limit`, filtrowanie żyjących osób, audit log. Wzorcowe podejście.
- **Zaproszenia** — rate limit 20/h, token jednorazowy, TTL 7 dni — OK.
- **Password reset** — anti-enumeration, jednorazowy token, `session_version++` — poprawne.
- **AdminMiddleware** — `session_version` check, własny `userRepo` — OK.
- **UUID refaktoryzacja** — 11 plików poprawionych, single source — dobre.
- **BCRYPT_COST jako stała** — spójna polityka hashowania — poprawne.

---

## 5. Werdykt

**PASS WITH CONDITIONS**

Zidentyfikowane naprawy z poprzedniego audytu są w większości kompletne i poprawne jakościowo. Projekt nie ma nowych krytycznych luk bezpieczeństwa aplikacyjnego, ale trzy findings `P-NEW-01..04` muszą zostać naprawione przed wdrożeniem produkcyjnym:

- Niespójność siły hasła w `ProfileController` obniża realne zabezpieczenie konta.
- Brak `session_version++` po `changePassword` i `changeEmail` anuluje ochronę przed kradzieżą sesji.
- GET z efektem ubocznym i bez CSRF umożliwia rate-limit DoS przez CSRF.

`D-NEW-01` (AdminController catch) jest technicznie deferreable, ale powinno być naprawione w tym samym sprincie — ryzyko wycieku informacji przez flash messages jest realne.
