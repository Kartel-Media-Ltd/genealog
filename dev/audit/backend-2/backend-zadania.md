# Backend — zadania naprawcze (re-audyt)
**Data:** 2026-04-07
**Format:** atomowe zadania 2-5 min, posortowane fazami
**Plan oparty na:** wynikach agentów OWASP/RODO/Architektura

---

## Faza 1 — BLOCKING (przed każdym deploy)
**Cel:** wyeliminować krytyczne luki RODO Art. 17 i niespójności polityki haseł.
**Szacowany czas: ~45 minut.**

---

### ZAD-1.1 — F-01: Usuń password_resets przed anonimizacją
**Plik:** `src/Services/AccountDeletionService.php`
**Linia:** ~37 (przed wywołaniem `$this->userRepository->anonymize($userId)`)
**Czas:** 5 min

Dodaj bezpośrednio przed anonymize():
```php
$this->db->execute('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
```

Weryfikacja: test manualny — utwórz reset tokenu, usuń konto, sprawdź że token nie istnieje w `password_resets`.

---

### ZAD-1.2 — F-02: Napraw shortId collision w anonymize()
**Plik:** `src/Repositories/UserRepository.php`
**Linia:** 125
**Czas:** 5 min

Zmień:
```php
// PRZED:
$shortId = substr($id, 0, 8);
$anonymousEmail = "deleted-{$shortId}@deleted.local";

// PO:
$anonymousEmail = "deleted-{$id}@deleted.local";
```

Usuń nieużywaną zmienną `$shortId`. Weryfikacja: sprawdź że `strlen("deleted-{uuid}@deleted.local") < 254` (tak, UUID ma 36 znaków → ~58 znaków łącznie).

---

### ZAD-1.3 — ProfileController: zmień próg hasła z 8 na 12
**Plik:** `src/Controllers/ProfileController.php`
**Linia:** 68
**Czas:** 5 min

Zmień:
```php
// PRZED:
if (mb_strlen($newPassword) < 8) {

// PO:
if (!$this->authService->validatePasswordStrength($newPassword)) {
```

Jeśli `validatePasswordStrength` zwraca bool z komunikatem, dostosuj obsługę błędu do wzorca z `AuthController::register()`.

---

### ZAD-1.4 — ProfileController: zaktualizuj widok (minlength 8→12)
**Plik:** `src/views/pages/profile.php`
**Linie:** 172, 197
**Czas:** 5 min

Zmień:
```html
<!-- PRZED: -->
minlength="8"
<!-- komunikat: "co najmniej 8 znaków" -->

<!-- PO: -->
minlength="12"
<!-- komunikat: "co najmniej 12 znaków, duże i małe litery, cyfra" -->
```

Weryfikacja: sprawdź że oba pola (nowe hasło + potwierdzenie) mają zaktualizowane `minlength`.

---

### ZAD-1.5 — ProfileController: incrementSessionVersion po zmianie hasła
**Plik:** `src/Controllers/ProfileController.php`
**Linia:** 78 (po pomyślnym zapisie nowego hasła)
**Czas:** 5 min

Dodaj po zapisie:
```php
$this->sessionVersionService->incrementSessionVersion($userId);
```

Wzorzec: patrz `src/Services/PasswordResetService.php::complete()`.

---

### ZAD-1.6 — AdminController: batch fix 5× catch z getMessage()
**Plik:** `src/Controllers/AdminController.php`
**Linie:** 109, 124, 139, 154, 197
**Czas:** 20 min

Dla każdego z 5 bloków catch zamień:
```php
// PRZED:
} catch (\Exception $e) {
    $this->flash->error($e->getMessage());
}

// PO:
} catch (\Exception $e) {
    error_log('AdminController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $this->flash->error('Wystąpił błąd serwera. Spróbuj ponownie.');
}
```

Weryfikacja: każdy blok obsługuje błąd bez ujawniania szczegółów SQL/systemowych.

---

## Faza 2 — IMPORTANT (ten sam sprint)
**Cel:** naprawić CSRF issue, compliance RODO, NIS2 CI, pozostałe session_version.
**Szacowany czas: ~2h 45 min.**

---

### ZAD-2.1 — CSRF: zmień GET /settings/export-data na POST
**Pliki:** `public/index.php:203`, `src/Controllers/SettingsController.php:103`, widok settings
**Czas:** 60 min

Kroki:
1. W `public/index.php:203`: zmień `$router->get('/settings/export-data', ...)` na `$router->post(...)`.
2. W `SettingsController::exportData()`: dodaj weryfikację tokenu CSRF (`$request->verifyCsrf()`).
3. W widoku ustawień: zastąp `<a href="/settings/export-data">` formularzem POST z tokenem CSRF:
```html
<form method="POST" action="/settings/export-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
    <button type="submit">Eksportuj dane</button>
</form>
```

Weryfikacja: żądanie GET do `/settings/export-data` powinno zwrócić 405 lub redirect.

---

### ZAD-2.2 — F-03: Rate limit eksportu per user_id
**Plik:** `src/Controllers/SettingsController.php:109`
**Czas:** 10 min

Zmień klucz rate limitera:
```php
// PRZED:
$rateLimitKey = 'data_export_' . $request->getIp();

// PO:
$rateLimitKey = 'data_export_' . $this->session->get('user_id');
```

---

### ZAD-2.3 — F-04: try/finally przy tmpDir w DataExportService
**Plik:** `src/Services/DataExportService.php:45-128`
**Czas:** 30 min

Opakuj całą logikę eksportu w try/finally:
```php
$tmpDir = sys_get_temp_dir() . '/genealog-export-' . uniqid();
mkdir($tmpDir, 0700, true);
try {
    // ... istniejąca logika eksportu ...
    return $zipPath; // zwróć path do ZIP (poza tmpDir)
} finally {
    // Cleanup plików tymczasowych — zawsze wykonywany
    if (is_dir($tmpDir)) {
        array_map('unlink', glob("$tmpDir/*") ?: []);
        rmdir($tmpDir);
    }
}
```

Uwaga: ZIP musi być zapisany poza `$tmpDir` lub pobrany przed finally.

---

### ZAD-2.4 — F-05: Stwórz infrastructure/cron.example
**Nowy plik:** `infrastructure/cron.example`
**Czas:** 20 min

Zawartość:
```bash
# Genealog — przykładowa konfiguracja cron
# Kopiuj do: /etc/cron.d/genealog
# Dostosuj ścieżki do środowiska docelowego

# Cleanup audit logu (retencja 90 dni) — Art. 5(1)(e) RODO
0 3 * * * www-data /usr/bin/php /var/www/genealog/bin/cleanup-audit-log.php >> /var/log/genealog/cron.log 2>&1

# Cleanup wygasłych tokenów resetu hasła (opcjonalnie — bezpieczeństwo)
0 4 * * * www-data /usr/bin/php /var/www/genealog/bin/cleanup-password-resets.php >> /var/log/genealog/cron.log 2>&1
```

Stwórz też `bin/cleanup-password-resets.php`:
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
// DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY
```

---

### ZAD-2.5 — F-06: Usuń `|| true` z composer audit w CI
**Plik:** `.github/workflows/ci.yml:34`
**Czas:** 5 min

Zmień:
```yaml
# PRZED:
- run: composer audit --no-dev || true

# PO:
- run: composer audit --no-dev
```

Jeśli jest zależność z CVE bez patcha — utwórz `composer-audit-ignore.json` i dodaj do polecenia `--ignore-platform-req` lub dokumentuj wyjątek w PR.

---

### ZAD-2.6 — ProfileController: incrementSessionVersion po zmianie e-mail
**Plik:** `src/Controllers/ProfileController.php::changeEmail():107`
**Czas:** 5 min

Analogicznie jak ZAD-1.5, po pomyślnej zmianie e-mail dodaj:
```php
$this->sessionVersionService->incrementSessionVersion($userId);
```

---

### ZAD-2.7 — MediaService: zamień bin2hex na Uuid::generate()
**Plik:** `src/Services/MediaService.php:54`
**Czas:** 5 min

Zmień:
```php
// PRZED:
$filename = bin2hex(random_bytes(16));

// PO:
$filename = Uuid::generate();
```

Sprawdź import `use` dla `Uuid` (powinien już istnieć w projekcie po poprzedniej refaktoryzacji).

---

### ZAD-2.8 — HomeController: dodaj error_log w catch
**Plik:** `src/Controllers/HomeController.php:28,37`
**Czas:** 10 min

```php
// PRZED:
} catch (\Exception $e) {
}

// PO:
} catch (\Exception $e) {
    error_log('HomeController error: ' . $e->getMessage());
}
```

---

## Faza 3 — Architektura (następne 2-3 sprinty, deferred)

---

### ZAD-3.1 — Aktywuj Container.php
**Plik:** `src/Core/Container.php`, `public/index.php`
**Czas:** 4h

1. Zdefiniuj bindings dla wszystkich serwisów w kontenerze.
2. Zastąp 56 ręcznych `new` wywołaniami `$container->get()`.
3. Wyeliminuj duplikaty: 3× `InvitationController`, 2× `DiscoveryController`, 2× `AdminController`.

---

### ZAD-3.2 — Wstrzyknij GedcomService przez DI
**Plik:** `src/Services/GedcomService.php`, `src/Controllers/TreeController.php`, `src/Services/DataExportService.php`
**Czas:** 2h (po ZAD-3.1)

1. Zarejestruj `GedcomService` w kontenerze.
2. Wstrzyknij przez konstruktor w `TreeController`.
3. Usuń `new GedcomService(...)` wewnątrz `DataExportService` — wstrzyknij przez konstruktor.

---

### ZAD-3.3 — EventDispatcher: static state → instance
**Plik:** `src/Core/EventDispatcher.php` i wszystkie użycia
**Czas:** 8h (po ZAD-3.1)

1. Usuń statyczne właściwości i metody.
2. Dodaj do kontenera jako singleton.
3. Wstrzyknij we wszystkich miejscach użycia.

---

### ZAD-3.4 — Redis session handler
**Pliki:** `config/config.php`, `docker-compose.yml`
**Czas:** 6h + infrastruktura

1. Dodaj Redis do `docker-compose.yml`.
2. Zainstaluj `predis/predis` lub skonfiguruj `ext-redis`.
3. Ustaw `session.save_handler = redis` w `config.php`.

---

## Faza 4 — Cleanup (ten sam lub następny sprint)

---

### ZAD-4.1 — AccountDeletionService: usuń pliki fizyczne
**Plik:** `src/Services/AccountDeletionService.php`
**Czas:** 30 min

Po anonimizacji konta:
```php
// Pobierz wszystkie tree_id usera
$trees = $this->treeRepository->findByOwner($userId);
foreach ($trees as $tree) {
    $mediaDir = STORAGE_PATH . '/media/' . $tree->getId();
    if (is_dir($mediaDir)) {
        array_map('unlink', glob("$mediaDir/*") ?: []);
        rmdir($mediaDir);
    }
}
```

Dodaj też `set_time_limit(300)` na początku `deleteAccount()`.

---

### ZAD-4.2 — CQ-03: Timing attack mitigation w PasswordResetService
**Plik:** `src/Services/PasswordResetService.php::initiate()`
**Czas:** 10 min

```php
// Na końcu metody, przed return — niezależnie od wyniku:
usleep(random_int(50000, 150000)); // 50-150ms stały delay
```

---

### ZAD-4.3 — Cleanup wygasłych tokenów password_resets
**Nowy plik:** `bin/cleanup-password-resets.php`
**Czas:** 20 min

Skrypt (analogiczny do `cleanup-audit-log.php`):
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
// Usuń tokeny wygasłe ponad 24h temu
$db->execute('DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY');
```

Dodaj do `infrastructure/cron.example` (ZAD-2.4).

---

## Podsumowanie

| Faza | Zadań | Szacowany czas | Typ |
|------|-------|---------------|-----|
| Faza 1 BLOCKING | 6 | ~45 min | krytyczne |
| Faza 2 IMPORTANT | 8 | ~2h 45 min | compliance + security |
| Faza 3 Architektura | 4 | ~20h | długoterminowe |
| Faza 4 Cleanup | 3 | ~1h | jakość |

**Quick wins (< 30 min łącznie):** ZAD-1.2, ZAD-1.5, ZAD-2.2, ZAD-2.5, ZAD-2.6, ZAD-2.7 — 6 zadań, ~7 linii kodu.
