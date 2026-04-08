# Rekomendacje — Audyt backendu Genealog
**Data:** 2026-04-07 | **System:** Genealog PHP 8.2 MVC / MariaDB
**Format priorytetów:** K = Krytyczne | P = Poważne | D = Drobne | A = Architektura

---

## Krytyczne — natychmiastowa naprawa

### K1 — AuthMiddleware bez $userRepo (martwy kod session_version)
**Plik:** `public/index.php:111`
**Ryzyko:** Zablokowane konto ma aktywną sesję przez do 8 godzin. Naruszenie RODO Art. 32 (brak kontroli dostępu). Ochrona przed przejętymi kontami nieskuteczna.
**Effort:** XS (~5 minut)
**Quick win:** TAK

```php
// Przed:
new AuthMiddleware($response)

// Po:
new AuthMiddleware($response, $userRepo)
```

---

### K2 — Prawo do usunięcia (RODO Art. 17) — niekompletna implementacja
**Plik:** `UserRepository.php:113`, `SettingsController::deleteAccount`
**Ryzyko:** `deactivate()` tylko ustawia `is_active=0`. Email i dane osobowe pozostają w bazie. Naruszenie Art. 17 RODO — prawo do bycia zapomnianym. Potencjalna kara UODO.
**Effort:** L (~4-6h)
**RODO:** TAK — obligatoryjne

Wymagana implementacja `AccountDeletionService`:
1. Anonimizacja emaila → `deleted-{uuid}@deleted.local`
2. Anonimizacja imienia/nazwiska → `[Usunięto]`
3. Hard delete lub anonimizacja osób z drzew (pytanie biznesowe: co z współdzielonymi drzewami?)
4. Zmiana FK `source_audit_log` z `ON DELETE RESTRICT` na `ON DELETE SET NULL` w migracji

---

## Poważne — naprawa w bieżącym sprincie

### P1 — Wyciek wiadomości wyjątków (15 miejsc)
**Pliki:** AdminController:249, AuthController:56+96, InvitationController:61, TreeController:69+133, RelationshipController:82, PersonController:158+230+282
**Ryzyko:** PDOException może ujawnić schemat bazy, ścieżki serwera, konfigurację. Information Disclosure (OWASP A09).
**Effort:** S (~45 min, refactor catch blocks)
**Quick win:** TAK

Wzorzec naprawy dla każdego z 15 miejsc:
```php
} catch (\InvalidArgumentException $e) {
    Session::flash('error', $e->getMessage()); // safe, user-facing
} catch (\Throwable $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    Session::flash('error', 'Wystąpił błąd wewnętrzny. Spróbuj ponownie.');
}
```

---

### P2 — Password reset — stub bez implementacji
**Plik:** `public/index.php:155-156`
**Ryzyko:** Użytkownicy nie mogą odzyskać kont. False sense of security. Luka funkcjonalna.
**Effort:** L (~6-8h łącznie z EmailService)
**RODO:** PARTIAL — brak dostępu do konta = brak możliwości realizacji praw

Wymagane kroki:
1. Tabela `password_resets (id, user_id, token CHAR(64), expires_at, used_at)`
2. `PasswordResetService::initiate(email)` — generuje token, `bin2hex(random_bytes(32))`
3. `EmailService::send(to, subject, body)` — php mail() lub PHPMailer
4. `GET /reset-password/{token}` — formularz nowego hasła
5. `POST /reset-password/{token}` — weryfikacja + zmiana + unieważnienie tokenu

---

### P3 — CSP unsafe-inline + unsafe-eval
**Plik:** `public/index.php:20`
**Ryzyko:** XSS przez wstrzyknięcie skryptów inline (OWASP A05). Cała polityka CSP nieskuteczna.
**Effort:** M (~2-3h — nonce infrastructure + Tailwind config)
**Quick win:** NIE

Etapy:
1. Generować nonce per-request: `$nonce = base64_encode(random_bytes(16));`
2. Przekazywać nonce do widoków przez zmienną globalną
3. Dodać `nonce="{$nonce}"` do każdego `<script>` i `<style>`
4. Zaktualizować nagłówek CSP: `'nonce-{$nonce}'` zamiast `'unsafe-inline'`
5. Przenieść konfigurację Tailwind do osobnego pliku (nie inline script)

---

### P4 — GedcomController::import bez rate limit i audit log
**Plik:** `GedcomController.php:50-116`
**Ryzyko:** DoS przez wielokrotny import dużych plików. Brak audit trail dla importów (STRIDE: Repudiation).
**Effort:** XS (~20 min)
**Quick win:** TAK

```php
// Dodać na początku GedcomController::import():
if ($rateLimiter->isLimited($ip, 'gedcom_import', 5, 3600)) {
    Session::flash('error', 'Przekroczono limit importów. Spróbuj za godzinę.');
    $this->response->redirect('/tree/' . $treeId . '/gedcom');
    return;
}
// Dodać audit log po imporcie:
$auditRepo->log('gedcom_import', $userId, $treeId, ['filename' => $filename, 'records' => $count]);
```

---

### P5 — GEDCOM export — filtrowanie żyjących osób
**Plik:** `GedcomController.php` (metoda export) / `GedcomService.php`
**Ryzyko:** Eksport zawiera pełne dane osób z `is_living=1`. Naruszenie RODO (PII żyjących osób poza kontrolą systemu).
**Effort:** S (~1h)

```php
// W GedcomExporter / GedcomService::export():
$persons = $this->personRepo->findByTree($treeId, ['is_living' => 0]);
// lub dla żyjących: eksportuj tylko imię + year urodzenia jako BIRT/DATE "YYYY"
```

---

### P6 — Brak rate limit na `/invite/{token}`
**Plik:** Router (public/index.php) / `InvitationController`
**Ryzyko:** Brute force tokenów zaproszeń (STRIDE: Spoofing). Token 64-znakowy jest praktycznie nie do złamania, ale bez limitu — możliwy rekonesans (enumeration).
**Effort:** XS (~15 minut)
**Quick win:** TAK

```php
// W InvitationController::show() / accept():
if ($rateLimiter->isLimited($ip, 'invite_token', 20, 3600)) {
    $this->response->redirect('/?error=too_many_attempts');
    return;
}
```

---

## Drobne — naprawa w wolnym czasie

### D1 — Password strength — tylko min 8 znaków
**Plik:** `AuthService.php:35`
**Ryzyko:** Słabe hasła. Rekomendacja NIST 800-63B: min 8 znaków (akceptowalne), ale brak entropy check pozwala na `password` czy `12345678`.
**Effort:** XS (~15 min)

Zmiana na min 12 znaków + sprawdzenie: cyfra LUB znak specjalny.

---

### D2 — BCRYPT_COST hardkodowany w dwóch miejscach
**Plik:** `AuthService.php`, `ProfileController.php:78`
**Ryzyko:** Niespójność po refactorze. Niskie.
**Effort:** XS (~10 min)

Wyekstrahować do `src/Core/Security::BCRYPT_COST = 12`.

---

### D3 — Race condition przy zmianie emaila
**Plik:** `ProfileController.php:102`
**Ryzyko:** Dwa równoczesne requesty zmiany emaila mogą pozostawić niespójny stan w DB.
**Effort:** XS (~10 min — owinąć w transakcję PDO)

---

### D4 — AdminMiddleware bez DB check session_version
**Plik:** `AdminMiddleware.php`
**Ryzyko:** Skradziona sesja admina ważna do timeout'u bez możliwości unieważnienia.
**Effort:** S (~30 min)

Analogicznie do naprawy K1 — przekazać `$userRepo` i sprawdzać `session_version`.

---

### D5 — strict_types brak w 13 plikach widoku
**Pliki:** `src/views/**/*.php` (atomy, molekuły, organizmy, szablony, strony)
**Ryzyko:** Brak egzekwowania typów. Niskie (widoki bez logiki biznesowej).
**Effort:** XS (~10 min — sed/regex po wszystkich plikach)

---

## Compliance RODO i NIS2

### RODO — luki wymagające naprawy

| Artykuł | Luka | Status | Priorytet |
|---------|------|--------|-----------|
| Art. 17 — Prawo do usunięcia | `deactivate()` tylko `is_active=0`, brak anonimizacji | **OPEN** | Krytyczny |
| Art. 20 — Przenoszalność danych | Brak endpointu `/settings/export-data` (JSON/ZIP konta) | **OPEN** | Poważny |
| Art. 32 — Bezpieczeństwo | AuthMiddleware bez userRepo — zablokowane konta aktywne | **OPEN** | Krytyczny |
| Art. 5(1)(e) — Przechowywanie | source_audit_log bez TTL/retention cron | **OPEN** | Poważny |

### Art. 20 — Data portability (brak implementacji)
**Effort:** M (~3-4h)

Wymagany endpoint `GET /settings/export-data`:
- Dane konta: imię, email, data rejestracji, ustawienia
- GEDCOM pliki dla wszystkich drzew użytkownika (tylko is_living=0)
- Format: ZIP z `account.json` + `tree-{id}.ged` per drzewo
- Limit: max 1 eksport / 24h (rate limit)

### source_audit_log retention cron
**Plik:** Migracja 008, linia 83
**Effort:** XS (~20 min)

Komentarz mówi "retention 3 lata" — brak implementacji. Opcje:
1. Kolumna `expires_at` w `source_audit_log` + INDEX
2. Cron `bin/cleanup-audit-log.php` — DELETE WHERE created_at < NOW() - INTERVAL 3 YEAR
3. MariaDB Event Scheduler (jeśli włączony)

### NIS2 — luki

| Wymaganie | Luka | Priorytet |
|-----------|------|-----------|
| Incident response plan | Brak `docs/security/incident-response.md` | Poważny |
| Vulnerability management | Brak `composer audit` w CI | Poważny |
| Supply chain security | Własny parser GEDCOM (775 LOC) zamiast MIT library | Średni |

---

## Quick wins (< 30 minut każde)

| # | Zadanie | Plik | Effort |
|---|---------|------|--------|
| QW1 | AuthMiddleware + $userRepo (1 linia) | `public/index.php:111` | 5 min |
| QW2 | Rate limit na `gedcom_import` (3 linie) | `GedcomController.php:50` | 15 min |
| QW3 | Rate limit na `/invite/{token}` (3 linie) | `InvitationController.php` | 15 min |
| QW4 | Audit log GEDCOM import (1 linia) | `GedcomController.php` | 10 min |
| QW5 | Exception catch — oddzielić user-facing od log | 15 plików | 20 min |
| QW6 | Uuid::generate() ekstrakcja | `src/Core/Uuid.php` | 20 min |
| QW7 | BCRYPT_COST jako stała | `src/Core/Security.php` | 10 min |
| QW8 | source_audit_log cleanup cron | `bin/cleanup-audit-log.php` | 20 min |

**Łączny czas quick wins:** ~2h 15 min. Adresuje 2 krytyczne issues bezpieczeństwa (K1, P1 partial) i compliance (RODO retention).

---

## Ulepszenia długoterminowe

| Obszar | Opis | Effort |
|--------|------|--------|
| Async matching | EventDispatcher async dla person.created (job queue) | XL |
| Redis sessions | Zastąpienie sesji plikowych Redis session handler | L |
| Redis rate limiter | Atomic INCR zamiast DB SELECT+INSERT per request | L |
| PasswordResetService | Pełna implementacja z EmailService | L |
| AccountDeletionService | RODO Art. 17 — anonimizacja + hard delete | L |
| GedcomService split | Parser / Importer / Exporter | M |
| DI container | PHP-DI lub własny — zamiast ręcznej instancjacji w index.php | L |
| CI/CD | GitHub Actions: composer audit, phpunit, phpstan | M |
| fisharebest/gedcom | Zastąpić własny parser MIT library | M |
| Data export endpoint | RODO Art. 20 — `/settings/export-data` ZIP | M |

---

## Podsumowanie

**Krytyczne (2):** AuthMiddleware bez userRepo + prawo do usunięcia — naprawić przed kolejnym release.
**Poważne (5):** Exception leak + password reset + CSP + GEDCOM rate limit + GEDCOM export filter — naprawić w ciągu 2 tygodni.
**Drobne (5):** Standardowe tech debt — naprawić przy okazji refactorów.
**RODO obligatoryjne:** Art. 17 (K2) + Art. 20 (data export) + source_audit_log retention.

**Szacowany czas naprawy krytycznych + poważnych:** ~20-25h roboczych.
