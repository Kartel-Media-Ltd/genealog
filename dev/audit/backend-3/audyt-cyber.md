# Audyt bezpieczeństwa — Genealog (backend) — Re-audit #3

**Data:** 2026-04-08
**Audytor:** claude-opus-4-6 + 3 subagenty (security-auditor, general-purpose, Plan)
**Metoda:** OWASP Top 10 2021 + RODO/GDPR + NIS2 + WCAG 2.1 AA + STRIDE
**Werdykt:** **PASS WITH CONDITIONS** — 3 krytyczne i 14 poważnych przed deploy

---

## Kontekst

To trzeci audyt backendu po dwóch cyklach napraw:
- **Audit #1** (`dev/audit/backend/`) — 73 zadań, ~60 zaimplementowanych
- **Audit #2** (`dev/audit/backend-2/`) — 21 zadań (Faza 3 architektura świadomie odroczona)
- **Audit #3** (ten) — weryfikacja poprawek + nowe znaleziska

Metryki:
- 15 controllers (2697 LOC)
- 17 services (4056 LOC)
- 8 repositories (1062 LOC)
- 13 Core classes
- 11 migracji
- 45 widoków

---

## KRYTYCZNE [K] — blokują deploy

### K1 — Email header injection w EmailService
**Plik:** `src/Services/EmailService.php:42,90`
**Kategoria:** OWASP A03 — Injection

**Dowód:**
```php
'From: ' . $fromName . ' <' . $from . '>'
```
`$fromName` pochodzi z `users.name` (baza danych), `$inviterName` w `sendInvitation` z `users.name` zapraszającego. Brak filtracji `\r\n`.

**Ryzyko:** Atakujący rejestruje konto z nazwą:
```
Jan Kowalski\r\nBcc: victim@evil.com\r\nSubject: Malicious payload
```
→ SMTP header injection → spam/phishing z zaufanego adresu.

**Naprawa:** Sanityzacja nagłówków:
```php
$safeName = str_replace(["\r", "\n", "\0"], '', $fromName);
```
Lub migracja do PHPMailer/Symfony Mailer (sanityzują same).

**Effort:** XS (10 min)

---

### K2 — `findSoleOwnedTreeIds()` usuwa drzewa współdzielone z innymi ownerami
**Plik:** `src/Services/AccountDeletionService.php:90-97`
**Kategoria:** OWASP A01 — Broken Access Control + RODO Art. 32 (integralność)

**Dowód:**
```php
private function findSoleOwnedTreeIds(string $userId): array
{
    $rows = $this->db->fetchAll(
        'SELECT id FROM trees WHERE owner_id = ?',
        [$userId]
    );
    return array_map(static fn(array $r): string => (string)$r['id'], $rows);
}
```

Nazwa metody obiecuje "drzewa gdzie user jest **jedynym** ownerem", ale query zwraca **WSZYSTKIE** drzewa z `owner_id = ?` — nawet te z co-ownerami w `tree_members`.

**Ryzyko:** Gdy user usuwa konto, `deleteTreeCascade()` hard-deletuje drzewa użytkownika **włącznie z drzewami które inni współpracownicy aktywnie edytują**. Utrata danych innych użytkowników.

**Naprawa:** Rozszerzenie query o sprawdzenie `tree_members`:
```sql
SELECT t.id FROM trees t
WHERE t.owner_id = ?
  AND NOT EXISTS (
      SELECT 1 FROM tree_members tm
      WHERE tm.tree_id = t.id
        AND tm.user_id != ?
        AND tm.role IN ('owner','editor')
  )
```

Drzewo z edytorem/współwłaścicielem → **transfer ownership** do pierwszego innego ownera (lub editora), NIE delete.

**Effort:** S (30 min + test)

---

### K3 — Brak Privacy Policy i Consent Flow
**Pliki:** brak `src/views/pages/privacy.php`, `src/views/pages/terms.php`; `src/views/pages/auth/register.php` bez checkboxa zgody
**Kategoria:** RODO Art. 13-14 + Art. 6/7

**Dowód:** `src/views/templates/AppLayout.php:381` linkuje do `/privacy` i `/terms`, ale router (`public/index.php`) nie ma tych tras → 404. Formularz rejestracji (`register.php:8-102`) nie zawiera żadnego checkboxa akceptacji regulaminu ani linka do polityki prywatności.

**Ryzyko:** Naruszenie Art. 13 (informacja o celu przetwarzania przed zebraniem danych) i Art. 6(1) (brak podstawy prawnej — zgody lub umowy). UODO egzekwuje z urzędu; kary do 4% obrotu.

**Naprawa:**
1. Utworzyć `src/views/pages/privacy.php` i `src/views/pages/terms.php` (treść merytoryczna)
2. Dodać trasy GET `/privacy` i `/terms` w `public/index.php`
3. Dodać wymagany checkbox w `register.php` pod przyciskiem: *"Akceptuję [Regulamin] i zapoznałem/am się z [Polityką prywatności]"*
4. Walidacja w `AuthController::processRegister()` — odrzucenie bez zgody

**Effort:** M (3-4h + research prawny)

---

## POWAŻNE [P] — wymagane przed deploy

### P1 — Stored XSS przez `addslashes()` w Alpine.js
**Plik:** `src/views/pages/trees/persons/index.php:219`
**Kategoria:** OWASP A03 — XSS

**Dowód:**
```html
x-show="'<?= addslashes(strtolower($person->fullName())) ?>'.includes(search.toLowerCase())"
```

`addslashes` escapuje tylko `'`, `"`, `\` — NIE escapuje `<`, `>`, `&`. Osoba z imieniem `</script><script>alert(1)</script>` → XSS.

**Ryzyko:** Editor drzewa wprowadza crafted imię → każdy viewer drzewa trafiony XSS-em → kradzież CSRF tokenu, session hijack.

**Naprawa:** Przekazać osoby jako JSON do Alpine.js:
```html
<div x-data="{ persons: <?= htmlspecialchars(json_encode($persons), ENT_QUOTES) ?>, search: '' }">
  <template x-for="p in persons" :key="p.id">
    <div x-show="search === '' || p.name.toLowerCase().includes(search.toLowerCase())">
      <span x-text="p.name"></span>
    </div>
  </template>
</div>
```

**Effort:** S (30 min)

---

### P2 — MIME bypass: `application/octet-stream` akceptowane w GEDCOM upload
**Plik:** `src/Controllers/GedcomController.php:22`
**Kategoria:** OWASP A08 — Integrity Failures

**Dowód:**
```php
private const ALLOWED_MIME = ['text/plain', 'text/x-gedcom', 'application/octet-stream'];
```

`application/octet-stream` to generyczny "binary blob" — zwracany przez `finfo` dla niemal każdego nierozpoznanego pliku. Walidacja staje się fikcyjna.

**Ryzyko:** Upload dowolnego pliku jako "GEDCOM" — parser tekstowy nie wywoła RCE, ale narusza zasadę "waliduj co dozwolone, odrzuć resztę". Przy błędzie parsera plik może zostać w `/tmp`.

**Naprawa:**
1. Usunąć `application/octet-stream` z `ALLOWED_MIME`
2. Dodać walidację zawartości: `str_starts_with(trim($content), '0 HEAD')`

**Effort:** XS (10 min)

---

### P3 — Brak weryfikacji emailu przy akceptacji zaproszenia
**Plik:** `src/Services/InvitationService.php:82-101`
**Kategoria:** OWASP A01 — Broken Access Control

**Dowód:** Metoda `accept(string $token, string $userId)` nie porównuje emaila zalogowanego usera z `invitations.invited_email`. Każdy zalogowany użytkownik z tokenem dołącza do drzewa.

**Ryzyko:** Wyciek tokenu (z URL history, loga serwera, logów proxy) → napastnik loguje się na własne konto i przyjmuje cudze zaproszenie → uzyskuje rolę `editor`/`viewer` w drzewie.

**Naprawa:**
```php
public function accept(string $token, string $userId): Tree
{
    $inv = $this->invRepo->findValidByToken($token);
    if ($inv === null) {
        throw new \RuntimeException('Zaproszenie wygasło lub nie istnieje.');
    }
    $user = $this->userRepo->findById($userId);
    if ($user === null || strtolower($user->email) !== strtolower($inv['invited_email'])) {
        throw new \RuntimeException('To zaproszenie nie jest przeznaczone dla tego konta.');
    }
    // ... reszta
}
```

**Effort:** S (20 min + test)

---

### P4 — Brak rate limitingu na `/register`
**Plik:** `src/Services/AuthService.php:25-58`
**Kategoria:** OWASP A07 — Authentication Failures

**Dowód:** `AuthService::register(..., ?string $ip = null)` przyjmuje `$ip` ale używa go tylko po **sukcesie** (`recordAttempt`). Brak `isRateLimited` na początku.

**Ryzyko:** Bot tworzy tysiące kont → spam zaproszeń → reputacja SMTP serwera.

**Naprawa:** Dodać na początku metody (po rate limit sprawdzeniu):
```php
if ($ip !== null && $this->isRateLimited($ip, 'register')) {
    throw new \RuntimeException('Zbyt wiele prób rejestracji. Spróbuj za 15 minut.');
}
```

**Effort:** XS (5 min)

---

### P5 — CSP `unsafe-inline` + `unsafe-eval`
**Plik:** `public/index.php:20`
**Kategoria:** OWASP A05 — Security Misconfiguration

**Dowód:**
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; ...
```

Oba tokeny obecne → CSP nie blokuje żadnego XSS. P1 (addslashes XSS) w pełni exploitable.

**Naprawa:** Nonce-based CSP. W `public/index.php:20`:
```php
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}';");
```
Każdy `<script>` i `<style>` w layoutach dostaje `nonce="<?= $_SESSION['csp_nonce'] ?>"`. Alpine.js inline handlers (`@click`) wymagają `'unsafe-hashes'` z konkretnymi hashami, lub refactor na `x-data`/`$dispatch`.

**Effort:** L (6-8h — refactor wszystkich layoutów)

---

### P6 — GEDCOM `$tmpPath` wyciek przy exception
**Plik:** `src/Controllers/GedcomController.php:114,165`
**Kategoria:** OWASP A05 + code quality

**Dowód:** `move_uploaded_file($_FILES['gedcom']['tmp_name'], $tmpPath)` — plik 50MB w `/tmp`. Blok `catch (\Throwable $e)` nie wywołuje `@unlink($tmpPath)`. Przy częstych błędach /tmp się zapycha.

**Naprawa:**
```php
try {
    // parsing
} catch (\Throwable $e) {
    error_log(...);
    // ...
} finally {
    if (isset($tmpPath) && is_file($tmpPath)) {
        @unlink($tmpPath);
    }
}
```

**Effort:** XS (5 min)

---

### P7 — `ProfileController::changePassword` — lokalna inkrementacja `session_version` może się rozjechać z DB
**Plik:** `src/Controllers/ProfileController.php:85-87`
**Kategoria:** OWASP A04 — Insecure Design (regresja z backend-2 ZAD-1.5)

**Dowód:**
```php
$this->userRepo->incrementSessionVersion($userId);
Session::set('session_version', (int)Session::get('session_version', 0) + 1);
```

`Session::get('session_version')` może być nieaktualne (np. po impersonacji lub concurrent request innej sesji). Rzeczywista wartość w DB może być wyższa. Lokalny `+1` nie dogoni DB → przy następnym request `AuthMiddleware` porównuje `dbVersion !== sessionVersion` → **user wyrzucony**.

**Naprawa:**
```php
$this->userRepo->incrementSessionVersion($userId);
$newVersion = $this->userRepo->getSessionVersion($userId) ?? 0;
Session::set('session_version', $newVersion);
```

Analogicznie w `changeEmail()` (linia ~127).

**Effort:** XS (5 min)

---

### P8 — Brak indeksu na `persons(tree_id, gedcom_xref)`
**Plik:** `migrations/002_trees.sql`
**Kategoria:** Performance / DoS potential

**Dowód:** `GedcomService::importIndividuals()` wywołuje `PersonRepository::findByXref($treeId, $xref)` **w pętli** — jeden lookup per osobę w pliku GEDCOM. Dla 1000 osób = 1000 full-table scans.

**Naprawa:** Nowa migracja `012_persons_indexes.sql`:
```sql
ALTER TABLE persons ADD INDEX idx_persons_xref (tree_id, gedcom_xref);
```

**Effort:** XS (5 min + migration)

---

### P9 — `buildPersonHierarchy()` rekurencja bez depth limit
**Plik:** `src/Controllers/PersonController.php:63-122`
**Kategoria:** DoS

**Dowód:** DFS bez limitu głębokości po wszystkich osobach drzewa. Dla 1000+ osób z głębokimi łańcuchami: stack overflow lub zużycie pamięci. Funkcja wywoływana w `index()` i `printList()`.

**Naprawa:** Cap głębokości (max 20 pokoleń):
```php
private function buildPersonHierarchy(array $persons, int $depth = 0, int $maxDepth = 20): array
{
    if ($depth >= $maxDepth) return [];
    // ...
}
```

**Effort:** S (20 min)

---

### P10 — DataExportService nie eksportuje wszystkich danych użytkownika
**Plik:** `src/Services/DataExportService.php:56-114`
**Kategoria:** RODO Art. 15 — Right to access

**Dowód:** Eksport zawiera: `account.json`, `notifications.json`, `audit-log.json`, GEDCOM drzewa sole-owned. **Brakuje:**
- `invitations.json` — zaproszenia wysłane przez usera
- `media-metadata.json` — metadata plików wgranych
- Drzewa współdzielone (gdzie user jest editor/viewer) — całkowicie pominięte
- Discovery settings, opt-outy
- `person_match_suggestions` które user utworzył/odrzucił

**Ryzyko:** Naruszenie Art. 15 — prawo dostępu obejmuje wszystkie dane. User jako editor drzewa ma osoby które stworzył (`created_by = user_id`); tych nie dostaje.

**Naprawa:** Rozbudowa eksportu o 4-5 nowych sekcji JSON. Dla shared trees — eksport tylko osób z `created_by = userId`.

**Effort:** M (3-4h)

---

### P11 — Brak implementacji RODO Art. 18 (right to restriction)
**Plik:** brak
**Kategoria:** RODO Art. 18

**Dowód:** `SettingsController` oferuje: export (Art. 20), delete (Art. 17), discovery opt-out (Art. 21). Brak opcji "zawieś konto tymczasowo" bez pełnej erasure. Brak kolumny `is_restricted` w `users`.

**Naprawa:** Nowa migracja `013_user_restriction.sql` + endpoint `POST /settings/restrict`:
- Kolumna `users.is_restricted TINYINT(1) DEFAULT 0`
- Middleware blokuje logowanie gdy `is_restricted=1`
- Osobny endpoint `POST /settings/unrestrict` (wymaga email confirm)

**Effort:** M (4-5h)

---

### P12 — Brak DPIA (Data Protection Impact Assessment)
**Plik:** brak `docs/compliance/dpia.md`
**Kategoria:** RODO Art. 35

**Dowód:** System przetwarza dane osobowe osób trzecich (w tym historyczne PII + dane żyjących), zawiera fingerprint matching (profilowanie) i globalny indeks. To kwalifikuje się do obowiązkowej DPIA.

**Naprawa:** Dokument DPIA: cel, zakres, ocena konieczności, identyfikacja ryzyk (K1-K3 + P1-P14), środki mitygacji, konsultacje z DPO.

**Effort:** L (6-8h + review prawny)

---

### P13 — NIS2: brak udokumentowanego planu backup/recovery
**Plik:** `docs/security/incident-response.md:77` — `TODO`
**Kategoria:** NIS2 Art. 21.2(c)

**Dowód:** Linia 77: `"Przywróć z najnowszego backup'u (procedura w docs/operations/backup.md — TODO)"`. Plik nie istnieje.

**Naprawa:** `docs/operations/backup.md`:
- Strategia 3-2-1 (3 kopie, 2 media, 1 off-site)
- RTO/RPO (np. RTO 4h, RPO 24h)
- Harmonogram: full weekly + incremental daily
- Test procedury recovery (kwartalnie)
- Odpowiedzialności per rola

**Effort:** S (2h)

---

### P14 — NIS2: brak MFA dla adminów
**Plik:** brak (grep `MFA|2FA|TOTP|webauthn` w `src/` — 0 wyników)
**Kategoria:** NIS2 Art. 21.2(j)

**Dowód:** Panel admin dostępny tylko przez hasło. Żaden mechanizm drugiego czynnika.

**Naprawa:** TOTP (Google Authenticator):
- Biblioteka: `pragmarx/google2fa` (Composer)
- Nowa tabela `user_mfa (user_id, secret, enabled_at, backup_codes JSON)`
- Wymuszenie dla `is_admin=1` po pierwszym logowaniu
- Endpoint `POST /admin/mfa/verify` w AuthController

**Effort:** L (8-12h)

---

## DROBNE [D]

### OWASP
- **D1** — `LIMIT {(int)$lim}` interpolacja bez komentarzy w `TreeRepository:183`, `AdminRepository:42,72,98`. Bezpieczne (int cast), ale pattern mylący. Dodać komentarz `// safe: int cast above`.
- **D2** — `pending_invitation` token w sesji bez walidacji formatu (`InvitationController:106,128`). Dodać: `if (strlen($token) === 64 && ctype_xdigit($token))`.
- **D3** — MIME boundary w `EmailService:40,89` używa `md5(uniqid())`. Zamień na `bin2hex(random_bytes(16))` dla spójności.
- **D4** — `sortBy` walidowany tylko w `PersonRepository:16-17`, nie w kontrolerze. Whitelist w controller dla defense-in-depth.

### Architektura
- **D5** — `tree_members` brak kolumny `invited_by` w migracji `002_trees.sql`, ale `InvitationRepository::insertMember()` ją wstawia. Dodać do migracji lub osobną.
- **D6** — `src/views/molecules/flash-messages.php:28-54` — bezpośredni dostęp `$_SESSION['flash']` zamiast `Session::get()`.
- **D7** — `DiscoveryController` instantiowany 2× w `public/index.php:219,272`. Lazy przez closure.
- **D8** — Brak infrastruktury deployment: `.env.example`, `Dockerfile`, `docker-compose.yml`, health-check endpoint (`GET /health`).
- **D9** — `session_version` random check 1% w `Session::start()` pozwala 99% pierwszych requestów po block() przejść bez weryfikacji. Rozważyć sprawdzenie per każdy request lub cache w sesji z TTL 30s.

### RODO / WCAG
- **D10** — WCAG 2.4.1: brak skip link w `AppLayout.php` przed `<header>`. Dodać `<a href="#main-content" class="sr-only focus:not-sr-only">`.
- **D11** — WCAG 4.1.3: dropdown notyfikacji (`AppLayout.php:148-182`) bez `aria-live="polite"`. Ekran-reader nie ogłasza nowych powiadomień.
- **D12** — RODO Art. 21: marketing opt-out nie ma toggle w UI (`settings.php`) mimo że kolumna `users.email_notifications` istnieje i jest edytowalna przez `UserRepository::updateEmailNotifications()`.
- **D13** — RODO Art. 30: brak Rejestru Czynności Przetwarzania w `docs/compliance/rcp.md`.

---

## POZYTYWNE [+]

### Utrzymane z poprzednich audytów
- **Tenant isolation perfekcyjny** — wszystkie queries do `persons/relationships/trees` filtrują po `tree_id` + sprawdzenie `tree_members`.
- **Zero SQL injection** — PDO prepared, whitelist dla dynamic ORDER BY, int cast dla LIMIT.
- **CSRF full coverage** — `verifyCsrf()` na każdym POST (30+ endpoints).
- **Session security** — httponly, SameSite=Strict, `use_strict_mode=1`, regeneration after login, absolute timeout 8h.
- **Upload security** — `is_uploaded_file()`, finfo MIME, UUID filenames, storage poza `public/`, konwersja do WebP.
- **No eval/exec** — żadnych `eval()`, `exec()`, `shell_exec()`, `system()`, `passthru()`.
- **Rate limiting** — centralny `RateLimiter` klasa, używany przez AuthService, DiscoveryController, GedcomController, InvitationController, SettingsController.
- **Dekoratorska architektura** — czysta MVC bez frameworka, separacja Controller → Service → Repository → DB.
- **Tenant isolation w fingerprint matching** — `GlobalIndexService` 5 warunków, privacy by design.

### Nowe w tym cyklu (po backend-2)
- **F-01 fix** — `AccountDeletionService` czyści `password_resets` przed anonimizacją.
- **F-02 fix** — pełny UUID w anonimizacji (brak collision dla userów o tym samym pierwszym oktecie).
- **Timing attack mitigation** — `PasswordResetService::initiate` stały jitter 50-150ms na każdej ścieżce return.
- **Catch block exhaustion** — `AdminController` 5× split na `InvalidArgumentException|RuntimeException` vs `Throwable` + `error_log`.
- **CSRF na cost-bearing endpoints** — `/settings/export-data` z GET → POST.
- **Physical file cleanup** — `AccountDeletionService` usuwa `storage/media/{tree_id}/` po cascade delete.
- **Try/finally tmpDir** — `DataExportService::generateExport` cleanup na każdej ścieżce.
- **CI hardening** — `composer audit` blokujący (usunięte `|| true`).
- **Strict password policy** — 12 znaków + cyfra/znak specjalny, egzekwowane spójnie w `ProfileController` przez `AuthService::validatePasswordStrength`.

---

## STRIDE Threat Model

Aktorzy:
- **Anonymous** — niezalogowany (rejestracja, login, forgot-password)
- **User** — zwykły użytkownik (owner/editor/viewer własnych drzew)
- **Admin** — admin panelu `/admin/*`
- **Impersonator** — admin impersonujący innego usera

Komponenty: endpoints, DB, sessions, filesystem (`storage/media/`, `/tmp`), SMTP.

| Threat | Aktor | Komponent | Ryzyko | Mitygacja | Status |
|--------|-------|-----------|--------|-----------|--------|
| **S**poofing | Anonymous | /login | Credential stuffing | Rate limit + bcrypt12 + session regeneration | ✅ OK |
| **S**poofing | User | /invite/accept | Przyjęcie cudzego zaproszenia | Brak email check | ❌ **P3** |
| **S**poofing | Anonymous | /register | Masowa rejestracja botów | Rate limit brak | ❌ **P4** |
| **S**poofing | User | `<email>@ .com` w `From:` | SMTP header injection | Brak sanitacji `\r\n` | ❌ **K1** |
| **T**ampering | User | `session_version` | Lokalna inkrementacja rozjeżdża się z DB | incrementSessionVersion + getSessionVersion | ❌ **P7** |
| **T**ampering | User | GEDCOM upload | MIME bypass `application/octet-stream` | finfo + content check | ❌ **P2** |
| **T**ampering | User | Request params | SQL injection | PDO prepared | ✅ OK |
| **T**ampering | User | Request params | XSS w widoku `trees/persons/index.php` | Tylko `addslashes` | ❌ **P1** |
| **R**epudiation | Admin | impersonation | Admin wykonał akcję bez śladu | `source_audit_log` + notification | ✅ OK |
| **R**epudiation | User | Account deletion | User zaprzecza że usunął | `deleted_at` + audit log | ✅ OK |
| **I**nformation Disclosure | Anonymous | /profile error | Leak `$e->getMessage()` | catch split | ✅ OK |
| **I**nformation Disclosure | User | /settings/export | Niepełny eksport wg Art. 15 | DataExportService | ❌ **P10** |
| **I**nformation Disclosure | Anonymous | CSP bypass | XSS wykonanie | CSP `unsafe-inline` | ❌ **P5** |
| **I**nformation Disclosure | User | sesja po accept invite | Cross-tenant data leak | Tree ownership check | ✅ OK |
| **D**enial of Service | User | GEDCOM import | /tmp zapchanie | Rate limit 5/h + tmpPath leak | ⚠️ **P6** |
| **D**enial of Service | User | Large tree view | `buildPersonHierarchy` bez cap | Brak limit | ❌ **P9** |
| **D**enial of Service | User | GEDCOM import (slow) | `findByXref` N+1 | Brak indeksu | ❌ **P8** |
| **D**enial of Service | Anonymous | /login brute force | Rate limit | RateLimiter | ✅ OK |
| **E**levation of Privilege | User | Account deletion | Usunięcie cudzych drzew | `findSoleOwnedTreeIds` wadliwe | ❌ **K2** |
| **E**levation of Privilege | User | Admin panel | RBAC bypass | AdminMiddleware + session_version | ✅ OK |
| **E**levation of Privilege | Admin | Demote self | Lock out | AdminService checks | ✅ OK |
| **E**levation of Privilege | Impersonator | Escape impersonation | `_admin_user_id` persistence | `exitImpersonate` endpoint | ✅ OK |
| **Legal** | Anonymous | Rejestracja | Brak consent flow | - | ❌ **K3** |
| **Legal** | User | Dostęp do danych | Art. 15 niekompletny | - | ❌ **P10** |
| **Legal** | User | Restriction | Art. 18 brak | - | ❌ **P11** |
| **Legal** | Org | NIS2 backup | Brak planu | - | ❌ **P13** |
| **Legal** | Org | NIS2 MFA | Brak | - | ❌ **P14** |

---

## Ocena końcowa

| Kategoria | K | P | D | + |
|-----------|---|---|---|---|
| **OWASP Top 10** | 1 | 7 | 4 | 9 |
| **RODO / GDPR** | 1 | 3 | 2 | 4 |
| **NIS2** | 0 | 2 | 0 | 2 |
| **WCAG 2.1 AA** | 0 | 0 | 2 | 1 |
| **Architektura** | 1 | 2 | 5 | 3 |
| **RAZEM** | **3** | **14** | **13** | **19** |

**Werdykt:** **PASS WITH CONDITIONS**

**Blokuje deploy:**
- K1 — Email header injection (SMTP abuse)
- K2 — `findSoleOwnedTreeIds` (utrata cudzych danych)
- K3 — Brak Privacy Policy + Consent flow (naruszenie RODO)

**Wymagane przed deploy produkcyjnym:**
- P1, P2, P3, P4, P6, P7, P8 (wszystkie XS/S effort)
- P10, P11, P13 (compliance — minimum wiable)

**Odroczone do post-MVP:**
- P5 (CSP refactor), P9 (buildPersonHierarchy), P12 (DPIA), P14 (MFA)

---

## Zalecenia dot. procesu

1. **Re-audit po naprawie K1-K3** przed jakimkolwiek deploy do środowiska innego niż dev.
2. **Pentest zewnętrzny** po zaimplementowaniu P1-P8 — walidacja niezależnym audytorem.
3. **Monitoring security events** — alerty na: failed login surge, impersonation, account deletion, gedcom import errors.
4. **Quarterly security review** — OWASP Top 10 + RODO compliance check co kwartał.
5. **Dependency updates** — `composer audit` raz w tygodniu + automatyczne PR przez Dependabot.
