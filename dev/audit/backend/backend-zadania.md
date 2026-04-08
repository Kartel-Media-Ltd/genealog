# Backend — lista zadań audytu
**Data:** 2026-04-07 | **System:** Genealog PHP 8.2 MVC
**Format:** kompatybilny z `/ultra-workaholic` — fazy + atomowe checkboxy
**Status implementacji (2026-04-08):** ~60/73 zadań wykonanych. Pominięte: Discovery split, async jobs, strict_types w widokach (długoterminowe / niskoryzykowne).

---

## Faza 1: Krytyczne fixy

> Czas szacowany: ~4-6h | Blokuje: bezpieczeństwo + RODO | Wykonać przed kolejnym release

- [ ] **public/index.php:111** — przekazać `$userRepo` do `AuthMiddleware`: zmienić `new AuthMiddleware($response)` na `new AuthMiddleware($response, $userRepo)`.
- [ ] **AuthMiddleware.php:28-38** — zweryfikować że logika `session_version` jest teraz wywoływana po poprawce konstruktora.
- [ ] **AuthMiddleware.php** — przetestować ręcznie: zablokować użytkownika w panelu admina, odświeżyć stronę z aktywną sesją — powinien dostać przekierowanie do /login.
- [ ] **UserRepository.php:113** — zmienić `deactivate()` tak by anonimizował email → `deleted-{uuid}@deleted.local` zamiast tylko ustawiać `is_active=0`.
- [ ] **src/Services/AccountDeletionService.php** — stworzyć nowy serwis: anonimizacja imienia/nazwiska → `[Usunięto]`, email → `deleted-{uuid}@deleted.local`.
- [ ] **AccountDeletionService** — dodać krok: soft delete lub anonimizacja osób z drzew użytkownika (tylko drzewa gdzie user jest sole owner).
- [ ] **migrations/010_account_deletion.sql** — zmienić FK `source_audit_log.user_id` z `ON DELETE RESTRICT` na `ON DELETE SET NULL`.
- [ ] **SettingsController::deleteAccount** — zintegrować z `AccountDeletionService` zamiast bezpośredniego `$userRepo->deactivate()`.
- [ ] **SettingsController** — przetestować przepływ usunięcia konta end-to-end (login → usuń → próba ponownego loginu → 403).

---

## Faza 2: Security fixes

> Czas szacowany: ~6-8h | Priorytet: OWASP P1-P5 | Wykonać w ciągu 2 tygodni

- [ ] **AuthController.php:56** — oddzielić `catch \InvalidArgumentException` (user-facing) od `catch \Throwable` (log + generic message).
- [ ] **AuthController.php:96** — ten sam wzorzec catch — logować stack przez `error_log()`, flash generic message.
- [ ] **InvitationController.php:61** — ten sam wzorzec catch — nie ujawniać `$e->getMessage()` w flash.
- [ ] **TreeController.php:69** — ten sam wzorzec catch.
- [ ] **TreeController.php:133** — ten sam wzorzec catch.
- [ ] **RelationshipController.php:82** — ten sam wzorzec catch.
- [ ] **PersonController.php:158** — ten sam wzorzec catch.
- [ ] **PersonController.php:230** — ten sam wzorzec catch.
- [ ] **PersonController.php:282** — ten sam wzorzec catch.
- [ ] **AdminController.php:249** — ten sam wzorzec catch + sprawdzić flash przed session_destroy.
- [ ] **src/Services/PasswordResetService.php** — stworzyć serwis: generowanie tokenu `bin2hex(random_bytes(32))`, INSERT do `password_resets`, walidacja TTL 1h.
- [ ] **migrations/011_password_resets.sql** — tabela: `id, user_id FK, token CHAR(64), expires_at, used_at`.
- [ ] **src/Services/EmailService.php** — stworzyć prosty wrapper na `php mail()` lub PHPMailer.
- [ ] **public/index.php:155-156** — zmienić stub `POST /forgot-password` → wywołanie `PasswordResetService::initiate(email)`.
- [ ] **Router (index.php)** — dodać: `GET /reset-password/{token}` → formularz nowego hasła.
- [ ] **Router (index.php)** — dodać: `POST /reset-password/{token}` → `PasswordResetService::complete(token, newPassword)`.
- [ ] **public/index.php:20** — dodać generowanie nonce: `$nonce = base64_encode(random_bytes(16));`.
- [ ] **public/index.php** — zaktualizować nagłówek CSP: zastąpić `'unsafe-inline'` przez `'nonce-{$nonce}'`.
- [ ] **src/views/templates/AppLayout.php** — przekazać `$nonce` do wszystkich tagów `<script>` i `<style>`.
- [ ] **public/js/** — przenieść konfigurację Tailwind inline do osobnego pliku `tailwind.config.js` aby nie wymagać unsafe-eval.
- [ ] **GedcomController.php:50** — dodać rate limit check na początku `import()`: `$rateLimiter->isLimited($ip, 'gedcom_import', 5, 3600)`.
- [ ] **GedcomController.php** — dodać `set_time_limit(300)` na początku metody `import()`.
- [ ] **GedcomController.php** — dodać limit rekordów: przerwać import po N=5000 rekordów i zwrócić komunikat użytkownikowi.
- [ ] **GedcomController.php** — po pomyślnym imporcie: INSERT do `source_audit_log` z typem `gedcom_import`, `user_id`, `tree_id`, liczba zaimportowanych rekordów.
- [ ] **InvitationController.php** — dodać rate limit na akcje `show()` i `accept()`: `$rateLimiter->isLimited($ip, 'invite_token', 20, 3600)`.
- [ ] **GedcomService.php lub GedcomController::export()** — filtrować osoby: nie eksportować `is_living=1` z pełnymi danymi. Dla żyjących: eksportuj tylko `INDI @xref@` z `BIRT/DATE YYYY` (tylko rok).

---

## Faza 3: Compliance — RODO + NIS2

> Czas szacowany: ~5-8h | Obligatoryjne: Art. 17, Art. 20, Art. 5(1)(e)

- [ ] **src/Controllers/SettingsController.php** — dodać akcję `GET /settings/export-data` → inicjuje generowanie ZIP z danymi konta.
- [ ] **src/Services/DataExportService.php** — stworzyć serwis generujący ZIP: `account.json` (imię, email, data rejestracji) + `tree-{id}.ged` per każde drzewo użytkownika.
- [ ] **DataExportService** — dodać limit: max 1 eksport / 24h per user (rate limit z `$rateLimiter`).
- [ ] **src/views/pages/settings/index.php** — dodać przycisk "Pobierz moje dane (RODO Art. 20)" linkujący do `/settings/export-data`.
- [ ] **bin/cleanup-audit-log.php** — stworzyć skrypt cron: `DELETE FROM source_audit_log WHERE created_at < NOW() - INTERVAL 3 YEAR`.
- [ ] **migrations/012_audit_log_expires.sql** — opcjonalnie: dodać kolumnę `expires_at TIMESTAMP` do `source_audit_log` + INDEX.
- [ ] **docs/security/incident-response.md** — stworzyć dokument: role, procedury zgłaszania incydentów, kontakty UODO, timeline (NIS2 Art. 23: 24h → 72h → 1 miesiąc).
- [ ] **.github/workflows/ci.yml** — stworzyć pipeline: `composer install` → `composer audit --no-dev` → `./vendor/bin/phpunit tests/`.
- [ ] **composer.json** — rozważyć dodanie `fisharebest/gedcom`: `composer require fisharebest/gedcom` i refactor parsera.

---

## Faza 4: Architektura

> Czas szacowany: ~12-16h | Priorytet: wydajność + testowalność + DRY

- [ ] **src/Core/Uuid.php** — stworzyć klasę: `final class Uuid { public static function generate(): string { return bin2hex(random_bytes(16)); } }`.
- [ ] **AuthService.php:96** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **PersonService.php:158** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **GlobalIndexService.php:200** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **NotificationService.php:108** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **TreeService.php:55** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **GedcomService.php:768** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **InvitationService.php:139** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **DiscoveryRepository.php:111** — zastąpić `bin2hex(random_bytes(16))` przez `Uuid::generate()`.
- [ ] **src/Controllers/DiscoverySettingsController.php** — wydzielić z DiscoveryController akcje: `settings()`, `updateSettings()`, `optIn()`, `optOut()`.
- [ ] **src/Controllers/DiscoveryApiController.php** — wydzielić z DiscoveryController akcje API: `search()`, `import()`, `reject()`.
- [ ] **DiscoveryController** — usunąć bezpośrednie wywołania `$db->fetchOne()` — przenieść do `DiscoveryRepository`.
- [ ] **src/Services/AuthService.php** — zastąpić własną logikę rate-limiting dla logowania wywołaniem `$rateLimiter->isLimited()` (usunąć duplikat logiki).
- [ ] **migrations/013_async_jobs.sql** — tabela `async_jobs (id, type, payload JSON, status ENUM, created_at, processed_at)` dla asynchronicznego matchingu.
- [ ] **EventDispatcher.php** — zmienić `person.created` handler aby zamiast bezpośredniego wywołania MatchingService wstawiał job do `async_jobs`.
- [ ] **bin/process-async-jobs.php** — stworzyć worker procesujący `async_jobs WHERE status='pending' LIMIT 10`: wywołuje MatchingService, GlobalIndexService.
- [ ] **Cron / supervisord** — dodać instrukcję uruchamiania workera co 30s lub przez cron `* * * * * php bin/process-async-jobs.php`.
- [ ] **README / CLAUDE.md** — zaktualizować instrukcje uruchomienia o krok workera async jobs.

---

## Faza 5: Code quality / nit

> Czas szacowany: ~2-3h | Priorytet: niski | Dług techniczny

- [ ] **src/Core/Security.php** — stworzyć klasę stałych: `const BCRYPT_COST = 12;`.
- [ ] **AuthService.php** — zastąpić hardkodowane `12` przez `Security::BCRYPT_COST`.
- [ ] **ProfileController.php:78** — zastąpić hardkodowane `12` przez `Security::BCRYPT_COST`.
- [ ] **src/views/atoms/*.php** — dodać `declare(strict_types=1);` na początku każdego pliku atomu.
- [ ] **src/views/molecules/*.php** — dodać `declare(strict_types=1);` na początku każdego pliku molekuły.
- [ ] **src/views/organisms/*.php** — dodać `declare(strict_types=1);` na początku każdego pliku organizmu.
- [ ] **src/views/templates/*.php** — dodać `declare(strict_types=1);` na początku szablonów.
- [ ] **src/views/pages/*.php** — dodać `declare(strict_types=1);` na początku stron.
- [ ] **AuthService.php:35** — podnieść minimalną długość hasła z 8 do 12 znaków.
- [ ] **AuthService.php** — dodać sprawdzenie siły hasła: co najmniej jedna cyfra LUB jeden znak specjalny.
- [ ] **ProfileController.php:102** — owinąć zmianę emaila w transakcję PDO: `$db->beginTransaction()` + `$db->commit()` / `$db->rollBack()`.
- [ ] **AdminMiddleware.php** — przekazać `$userRepo` i dodać DB check `session_version` (analogicznie do naprawy w Fazie 1 dla AuthMiddleware).
- [ ] **src/views/templates/AppLayout.php:366** — dodać komentarz PHPDoc przy `<?= $content ?>` wyjaśniający dlaczego bez escape (trusted controller output).
- [ ] **GedcomService.php** — wydzielić klasę `GedcomParser` (~200 LOC parsowania tekstu GEDCOM na tablice PHP).
- [ ] **GedcomService.php** — wydzielić klasę `GedcomImporter` (~300 LOC zapisu do DB przez repozytoria).
- [ ] **GedcomService.php** — przemianować resztę na `GedcomExporter` (~275 LOC generowania pliku GEDCOM).

---

## Podsumowanie faz

| Faza | Opis | Czas | Priorytet |
|------|------|------|-----------|
| **Faza 1** | Krytyczne fixy (AuthMiddleware + prawo do usunięcia) | ~4-6h | KRYTYCZNY |
| **Faza 2** | Security fixes (15x catch + password reset + CSP + GEDCOM) | ~6-8h | WYSOKI |
| **Faza 3** | Compliance RODO + NIS2 | ~5-8h | WYSOKI |
| **Faza 4** | Architektura (UUID + Discovery split + async) | ~12-16h | ŚREDNI |
| **Faza 5** | Code quality / nit (strict_types + stałe + hasło) | ~2-3h | NISKI |
| **Łącznie** | | **~29-41h** | |
