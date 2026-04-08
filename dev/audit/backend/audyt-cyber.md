# Audyt bezpieczeństwa backendu — Genealog
**Data:** 2026-04-07 | **Scope:** OWASP Top 10 + Tenant Isolation + Auth + STRIDE
**Werdykt ogólny:** PASS WITH CONDITIONS — 0 Krytycznych, 5 Poważnych, 6 Drobnych

---

## 1. OWASP Top 10 — przegląd

### A01 Broken Access Control — PASS
Tenant isolation zaimplementowany konsekwentnie. Każdy query w PersonRepository, RelationshipRepository i TreeRepository filtruje po `tree_id` powiązanym z zalogowanym użytkownikiem. Nie wykryto IDOR. TreeAccessMiddleware weryfikuje `tree_members` lub `owner_id` przed każdym kontrolerem drzewa.

### A02 Cryptographic Failures — PASS
- `password_hash()` z `PASSWORD_BCRYPT`, cost=12 — poprawne.
- Tokeny jednorazowe: `bin2hex(random_bytes(32))` — kryptograficznie bezpieczne.
- CSRF: `hash_equals()` przy porównaniu tokenów — bezpieczne porównanie stałoczasowe.
- Brak hardkodowanych sekretów w repozytorium.

### A03 Injection — PASS
Zero konkatenacji SQL. PDO prepared statements wszędzie. Dynamiczny ORDER BY z whitelistą: `['last_name', 'first_name', 'birth_date', 'created_at']`. Brak `exec()`, `system()`, `eval()`, `unserialize()` na danych użytkownika.

### A04 Insecure Design — POWAŻNE (P2)
`POST /forgot-password` (`public/index.php:155-156`) zwraca fałszywy sukces bez generowania tokenu ani wysyłki e-maila. Użytkownik myśli że reset działa — to naruszenie bezpieczeństwa UX i faktyczna luka funkcjonalna.

### A05 Security Misconfiguration — POWAŻNE (P3)
CSP w `public/index.php:20` zawiera `unsafe-inline` i `unsafe-eval`. Nie chroni przed XSS opartym na wstrzykiwaniu skryptów inline. Tailwind CDN wymaga nonce-based CSP lub osobnego pliku konfiguracji.

### A06 Vulnerable & Outdated Components — DROBNE
Brak `composer audit` w CI/CD. GedcomService (775 LOC) napisany od zera zamiast użycia `fisharebest/gedcom` (MIT) — większa powierzchnia ataku przy parsowaniu złośliwych plików .ged.

### A07 Identification & Authentication Failures — POWAŻNE (P1, P4)
**P4 — AuthMiddleware bez userRepo:** `public/index.php:111` tworzy `new AuthMiddleware($response)` bez repozytorium użytkowników. Logika `session_version` w `AuthMiddleware.php:28-38` jest martwym kodem — zablokowany użytkownik zachowuje aktywną sesję przez do 8 godzin.

Pozytywne: `session_regenerate_id()` po logowaniu, absolutny timeout 8h, `httponly`, `SameSite=Strict`.

### A08 Software & Data Integrity — DROBNE
Brak SRI (Subresource Integrity) dla lokalnych vendor files. Niski priorytet dla aplikacji samohostedowanej, ale warto dodać.

### A09 Security Logging & Monitoring — DROBNE (D6)
AdminMiddleware sprawdza tylko `Session::get('is_admin')` bez weryfikacji w DB. Zainfekowana sesja admina nie jest wykrywana przez DB check. GedcomController nie loguje importów do `source_audit_log`.

### A10 Server-Side Request Forgery — PASS
Brak endpointów przyjmujących URL od użytkownika i wykonujących zewnętrzne requesty po stronie serwera.

---

## 2. Szczegółowe findings — POWAŻNE (P)

### P1 — Wyciek wiadomości wyjątków (Exception message leak)
**Pliki:** AdminController:249, AuthController:56+96, InvitationController:61, TreeController:69+133, RelationshipController:82, PersonController:158+230+282 (łącznie 15 miejsc).

`$e->getMessage()` przekazywane bezpośrednio do flash messages. `PDOException` może ujawnić schemat bazy danych, ścieżki serwera, dane konfiguracyjne.

**Naprawa:**
```php
// Przed:
} catch (\Throwable $e) {
    Session::flash('error', $e->getMessage());
}

// Po:
} catch (\InvalidArgumentException $e) {
    Session::flash('error', $e->getMessage()); // safe, user-facing
} catch (\Throwable $e) {
    error_log('[ERROR] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    Session::flash('error', 'Wystąpił błąd wewnętrzny. Spróbuj ponownie.');
}
```

### P2 — Password reset — stub bez implementacji
**Plik:** `public/index.php:155-156`

Endpoint `POST /forgot-password` nie robi nic poza zwróceniem `ok`. Naruszenie bezpieczeństwa funkcjonalnego — użytkownicy nie mogą odzyskać kont, a system sugeruje że mogą.

**Naprawa:** Zaimplementować `PasswordResetService` + tabela `password_resets (token, user_id, expires_at, used_at)` + `EmailService`.

### P3 — CSP unsafe-inline + unsafe-eval
**Plik:** `public/index.php:20`

Obecna polityka Content-Security-Policy wyłącza ochronę przed XSS przez dozwolenie inline scripts. Tailwind CDN wymaga `unsafe-eval` dla JIT — kompromis do zaakceptowania tymczasowo, ale należy przejść na nonce-based CSP.

**Naprawa:** Per-request nonce generowany przez `bin2hex(random_bytes(16))`, przekazywany do widoków i do nagłówka CSP.

### P4 — AuthMiddleware bez $userRepo (martwy kod session_version)
**Plik:** `public/index.php:111`

```php
// Aktualnie:
new AuthMiddleware($response)

// Powinno być:
new AuthMiddleware($response, $userRepo)
```
Jeden parametr. Bez tej zmiany zablokowanie konta nie ma efektu przez do 8 godzin.

### P5 — GedcomController::import bez rate limit
**Plik:** `GedcomController.php:50-116`

Import pliku GEDCOM (do 50 MB) bez ograniczenia liczby prób ani limitu rekordów. Złośliwy editor może wielokrotnie importować ogromne pliki, zalewając bazę danych i blokując serwer.

**Naprawa:**
```php
if ($rateLimiter->isLimited($ip, 'gedcom_import', 5, 3600)) {
    $this->response->redirect('/tree/' . $treeId . '/gedcom?error=rate_limit');
    return;
}
```

---

## 3. Findings drobne (D)

| ID | Opis | Plik | Ryzyko |
|----|------|------|--------|
| D1 | Hasło min. 8 znaków — brak sprawdzenia entropii | AuthService.php:35 | Niskie |
| D2 | flash() przed session_destroy w exitImpersonate — wzorzec zły | AdminController:248-250 | Niskie |
| D3 | `<?= $content ?>` bez escape — trusted content (udokumentować) | AppLayout:366 | Niskie |
| D4 | Rate limit per-IP only — credential stuffing przez wiele IP | RateLimiter.php | Niskie |
| D5 | AdminMiddleware bez DB check session_version | AdminMiddleware.php | Niskie |
| D6 | GedcomController::import bez logowania do source_audit_log | GedcomController.php | Niskie |

---

## 4. Ocena Tenant Isolation

**Wynik: BEZPIECZNE**

Weryfikacja w trzech kluczowych repozytoriach:
- `PersonRepository` — każdy query zawiera `WHERE p.tree_id = :treeId` lub JOIN przez `trees` z filtrem `owner_id`.
- `RelationshipRepository` — `WHERE tree_id = :treeId` w każdym SELECT/INSERT/UPDATE.
- `TreeRepository` — dostęp przez `owner_id` lub `tree_members.user_id`.

`TreeAccessMiddleware` działa przed kontrolerami drzewa. Nie ma możliwości odczytania danych z cudzego drzewa przez manipulację parametrami URL.

---

## 5. Ocena autentykacji

**Pozytywne:**
- `password_hash(PASSWORD_BCRYPT, ['cost' => 12])` — prawidłowy koszt.
- `session_regenerate_id(true)` po udanym logowaniu — ochrona przed session fixation.
- Absolutny timeout sesji 8 godzin.
- `SameSite=Strict` + `httponly` dla ciasteczka sesji.
- Rate limiting logowania: max 5 prób / 15 minut per IP.
- Admin impersonation: audit log, rate limit 10/h, notyfikacja dla przejętego użytkownika.

**Problemy:**
- AuthMiddleware bez userRepo — zablokowane konta nie są wykrywane (P4).
- Password reset — nie działa (P2).
- Brak rate limiting per-email — ataki credential stuffing przez różne IP.

---

## 6. Model zagrożeń STRIDE

| Zagrożenie | Komponent | Status | Mitygacja |
|-----------|-----------|--------|-----------|
| **Spoofing** — credential stuffing | AuthController | PARTIAL | Rate limit per-IP, brak per-email |
| **Spoofing** — kradzież sesji | Session | MITIGATED | httponly, SameSite=Strict, regenerate_id |
| **Spoofing** — token zaproszenia | InvitationController | PARTIAL | Brak rate limit na `/invite/{token}` |
| **Tampering** — SQL injection | Wszystkie repo | MITIGATED | PDO prepared statements, whitelist ORDER BY |
| **Tampering** — CSRF | Formularze | MITIGATED | hash_equals, rotation po verify |
| **Tampering** — złośliwy GEDCOM | GedcomService | PARTIAL | Brak sanitizacji pola NOTES |
| **Repudiation** — import GEDCOM | GedcomController | PARTIAL | Brak logowania do source_audit_log |
| **Repudiation** — edycja osoby | PersonController | MITIGATED | source_audit_log zapisuje zmiany |
| **Information Disclosure** — DB schema | catch blocks | OPEN | getMessage() do flash (P1) |
| **Information Disclosure** — zdjęcia | media.php | MITIGATED | Auth check przed readfile() |
| **Information Disclosure** — GEDCOM export | GedcomController | PARTIAL | Eksportuje living persons (is_living=1) |
| **Information Disclosure** — global index | GlobalIndexService | MITIGATED | 5 warunków RODO, tylko historyczne osoby |
| **Denial of Service** — GEDCOM import | GedcomController | PARTIAL | Brak rate limit, brak limitu rekordów (P5) |
| **Denial of Service** — reindexTree | GlobalIndexService | OPEN | Synchroniczny w request, może timeout'ować |
| **Denial of Service** — matching | MatchingService | PARTIAL | Synchroniczny w person.created |
| **Denial of Service** — upload zdjęć | MediaService | PARTIAL | Brak rate limit per-user |
| **Elevation of Privilege** — zablokowane konto | AuthMiddleware | OPEN | session_version martwy kod (P4) |
| **Elevation of Privilege** — IDOR tree | TreeAccessMiddleware | MITIGATED | Sprawdza tree_members przed kontrolerem |
| **Elevation of Privilege** — admin session | AdminMiddleware | PARTIAL | Brak DB check session_version |
| **Elevation of Privilege** — XSS → session | CSP | OPEN | unsafe-inline (P3) |
| **Elevation of Privilege** — path traversal | MediaService | MITIGATED | realpath() check, UUID filename |
| **Elevation of Privilege** — mass assignment | PersonController | MITIGATED | Jawna lista akceptowanych pól |

---

## 7. Obszary pozytywne

- **Zero SQL injection** — PDO prepared statements wszędzie bez wyjątku.
- **XSS** — `htmlspecialchars()` konsekwentnie w widokach, Alpine używa `x-text` (nie `x-html`).
- **Security headers** — X-Frame-Options: DENY, X-Content-Type-Options: nosniff, Referrer-Policy: strict-origin-when-cross-origin, Permissions-Policy, HSTS gdy HTTPS.
- **Upload mediów** — walidacja przez `finfo_file()` (nie rozszerzenie), UUID filename, storage poza `public/`, konwersja do WebP, realpath() path traversal check.
- **Privacy by Design w GlobalIndexService** — 5 niezależnych warunków RODO przed indeksowaniem osoby.
- **Admin impersonation** — rate limit 10/h, audit log, notyfikacja dla przejętego użytkownika — solidna implementacja.
