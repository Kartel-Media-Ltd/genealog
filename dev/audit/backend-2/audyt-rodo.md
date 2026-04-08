# Audyt RODO/NIS2 — re-audyt backendu Genealog
**Data:** 2026-04-07
**Werdykt:** PASS WITH CONDITIONS
**Poprzedni audyt:** `dev/audit/backend/`
**Zakres:** walidacja zgodności z RODO Art. 17, 20, 25, 30 i NIS2 Art. 21

---

## 1. Walidacja napraw z poprzedniego audytu

| Artykuł | Status | Komentarz |
|---------|--------|-----------|
| Art. 17 — prawo do usunięcia (erasure) | ⚠️ PARTIAL | Logika anonimizacji poprawna, ale 2 luki — patrz F-01, F-02 |
| Art. 17 — anonymize() | ⚠️ BUG | shortId collision — patrz F-02 |
| Migracja 010 FK SET NULL | ✅ FIXED | Klucze obce poprawnie skonfigurowane |
| Art. 20 — eksport danych | ⚠️ UWAGI | 3 zastrzeżenia — patrz sekcja 3 |
| Art. 25 — GEDCOM filter living | ✅ FIXED | `buildIndi()` filtruje żyjące osoby |
| Art. 30 — audit log retention | ⚠️ UWAGA | Cron nie skonfigurowany — patrz F-05 |
| Art. 30 — GEDCOM audit log | ✅ FIXED | Operacje GEDCOM logowane |
| Art. 5(1)(a) — anti-enumeration password reset | ✅ FIXED | Jednakowa odpowiedź dla istniejącego i nieistniejącego e-mail |
| NIS2 — incident-response.md | ✅ FIXED | Dokument kompletny |
| NIS2 CI composer audit | ⚠️ UWAGA | `|| true` nie blokuje pipeline — patrz F-06 |

---

## 2. Krytyczne findings

### F-01 — Aktywne tokeny resetu hasła po anonimizacji konta (RODO Art. 17)
**Priorytet:** KRYTYCZNY (K)
**Plik:** `src/Services/AccountDeletionService.php:37-78`

`deleteAccount()` anonimizuje użytkownika (UPDATE na tabeli `users`) zamiast go usuwać. Tabela `password_resets` ma `ON DELETE CASCADE` dla FK na `users.id` — co działa przy DELETE, ale nie przy UPDATE (anonimizacja). W efekcie tokeny resetu hasła z tabeli `password_resets` nadal istnieją dla zanonimizowanego konta.

**Scenariusz ataku:** atakujący, który wcześniej zainicjował reset hasła ofiary, może po anonimizacji konta użyć wygenerowanego tokenu, ustawić nowe hasło i zalogować się na „usunięte" konto. Naruszenie Art. 17 RODO — dane osobowe (sesja, historia) dostępne przez backdoor.

**Fix (1 linia):**
```php
// AccountDeletionService.php — przed wywołaniem anonymize()
$this->db->execute('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
```

---

### F-02 — Kolizja shortId w anonymize() — drugi użytkownik nie może być usunięty (RODO Art. 17)
**Priorytet:** KRYTYCZNY (K)
**Plik:** `src/Repositories/UserRepository.php:125`

```php
$shortId = substr($id, 0, 8);
// email = "deleted-{shortId}@deleted.local"
```

UUID ma format `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`. Pierwsze 8 znaków to tylko pierwsza grupa szesnastkowa. Dwa UUID `550e8400-e29b-...` i `550e8401-e29b-...` mają identyczny `substr(0, 8)` = `550e8400` i `550e8401` — **nie**, ale np. `550e8400` i `550e8400` wygenerowane przez UUID v4 mają kolizję z prawdopodobieństwem ~1/2^32. Przy bazie > 100k użytkowników statystycznie nieuniknione.

Skutek: drugi użytkownik z kolizją shortId nie może wykonać prawa do usunięcia — `anonymize()` rzuca `PDOException` z naruszeniem UNIQUE na kolumnie `email`. Art. 17 RODO wymaga bezwarunkowej realizacji żądania usunięcia.

**Fix (1 linia):**
```php
// Używaj pełnego UUID zamiast substr — mieści się w VARCHAR(254)
$anonymousEmail = "deleted-{$id}@deleted.local";
```

---

## 3. Poważne findings

### F-03 — Rate limit eksportu danych per IP zamiast per user (Art. 20)
**Priorytet:** POWAŻNY (P)
**Plik:** `src/Controllers/SettingsController.php:109`

Rate limit eksportu danych (Art. 20 RODO — prawo do przenoszenia) jest obliczany per **adres IP**, a nie per **użytkownik**. Użytkownicy za NAT, VPN lub shared IP mogą być zablokowania przez siebie nawzajem. Osoby korzystające z sieci korporacyjnej lub mobilnej (dziesiątki osób za jednym IP) nie będą mogły wyeksportować danych przez 24h po tym, jak jeden z nich to zrobi.

**Fix:**
```php
// Zamiast klucza IP:
'data_export_' . $this->session->get('user_id')
```

---

### F-04 — Wyciek plików tymczasowych ZIP przy wyjątku (Art. 5(1)(f))
**Priorytet:** POWAŻNY (P)
**Plik:** `src/Services/DataExportService.php:45-128`

Katalog tymczasowy `$tmpDir = sys_get_temp_dir() . '/genealog-export-' . uniqid()` jest tworzony bez bloku `try/finally`. Gdy w pętli drzew wystąpi wyjątek (np. błąd DB, przekroczony memory limit przy 50k osób), katalog i wszystkie zapisane pliki pozostają w `/tmp/`.

Plik eksportu może zawierać imiona, daty urodzenia, miejsca — dane osobowe w sensorownym miejscu (Art. 5(1)(f) — integralność i poufność). Na serwerze współdzielonym (shared hosting) inne procesy mogą odczytać `/tmp/`.

**Fix:**
```php
try {
    mkdir($tmpDir, 0700, true);
    // ... logika eksportu
} finally {
    if (is_dir($tmpDir)) {
        array_map('unlink', glob("$tmpDir/*"));
        rmdir($tmpDir);
    }
}
```

---

### F-05 — Brak konfiguracji cron dla cleanup audit logu (Art. 5(1)(e))
**Priorytet:** POWAŻNY (P)
**Plik:** `bin/cleanup-audit-log.php` (istnieje), brak `infrastructure/cron.example`, `Makefile`, `systemd` unit

Skrypt czyszczenia audit logu istnieje, ale nie ma żadnej konfiguracji jego uruchamiania w repozytorium. Bez aktywnego crona audit log rośnie w nieskończoność — naruszenie zasady minimalizacji przechowywania danych (Art. 5(1)(e) RODO). Audytor zewnętrzny nie może zweryfikować, że retention policy jest egzekwowana.

**Fix:** dodać `infrastructure/cron.example`:
```
# Genealog — cron jobs (kopiuj do /etc/cron.d/genealog)
# Cleanup audit log — retencja 90 dni
0 3 * * * www-data /usr/bin/php /var/www/genealog/bin/cleanup-audit-log.php >> /var/log/genealog/cron.log 2>&1
```

---

### F-06 — `composer audit || true` nie blokuje pipeline (NIS2 Art. 21)
**Priorytet:** POWAŻNY (P)
**Plik:** `.github/workflows/ci.yml:34`

```yaml
- run: composer audit --no-dev || true
```

Komentarz w pliku informuje, że `|| true` ma być usunięte „po aktualizacji deps" — ale pozostaje. W efekcie znane CVE w zależnościach PHP nie blokują wdrożenia. NIS2 Art. 21 wymaga zarządzania podatnościami. W projekcie przechowującym dane genealogiczne (RODO, dane wrażliwe) jest to naruszenie podstawowe.

**Fix (2 warianty):**
- Usuń `|| true` (twarde blokowanie — zalecane),
- Lub osobny job z `continue-on-error: true` i wyraźnym oznaczeniem w raporcie (miękkie — tylko gdy zależność ma brak patcha).

---

## 4. Informacyjne (Code Quality)

### CQ-01 — AuthService z opcjonalnym RateLimiter — duplikacja SQL
**Plik:** `src/Services/AuthService.php`

`RateLimiter` jest nullable dependency w `AuthService`. Gdy nie jest wstrzyknięty, serwis duplikuje logikę rate limitingu bezpośrednio w SQL. Rozbieżność między dwoma implementacjami tworzy ryzyko regresji przy przyszłych zmianach (np. zmiana okna czasowego zostanie zrobiona tylko w jednym miejscu).

**Rekomendacja:** uczynić `RateLimiter` wymaganą zależnością lub usunąć fallback SQL.

---

### CQ-02 — `invitations.invited_by` po anonimizacji wskazuje na zanonimizowane konto
**Plik:** Migracja 010, tabela `invitations`

`invited_by` FK ma `ON DELETE RESTRICT` — zaproszenia zostają po anonimizacji, z `invited_by` wskazującym na zanonimizowanego użytkownika. Technicznie legalne (UPDATE, nie DELETE), ale w kontekście Art. 17 warto rozważyć, czy `invited_by` powinno być nullowalne.

---

### CQ-03 — Timing attack w `PasswordResetService::initiate()`
**Plik:** `src/Services/PasswordResetService.php::initiate()`

Gdy e-mail nie istnieje w bazie, metoda wraca natychmiast (brak INSERT, brak SMTP). Gdy e-mail istnieje — INSERT + wysyłka e-mail = dłuższy czas odpowiedzi. Różnica czasowa może umożliwić enumerację kont przez pomiar czasu (choć nie przez treść odpowiedzi — anti-enumeration jest poprawne).

**Fix (prosta mitigacja):**
```php
usleep(random_int(50000, 150000)); // 50-150ms stały delay niezależnie od wyniku
```

---

## 5. Pozytywne obserwacje

- **Art. 17 — logika anonimizacji** jest dobrze zaprojektowana (UPDATE zamiast DELETE, FK SET NULL w migracji 010) — F-01 i F-02 to uzupełnienia, nie przebudowa.
- **Art. 25 — GEDCOM** — żyjące osoby są poprawnie filtrowane przy eksporcie, co chroni przed niezamierzonym ujawnieniem PII przez import/eksport.
- **Art. 5(1)(a) — password reset** — anti-enumeration poprawnie zaimplementowane.
- **NIS2 — incident-response.md** — dokument jest kompletny i zawiera procedury zgłoszenia w ciągu 72h.
- **GEDCOM audit log** — operacje importu/eksportu logowane, powiązane z user_id i tree_id.

---

## 6. Werdykt

**PASS WITH CONDITIONS**

Dwa krytyczne findings (F-01, F-02) muszą zostać naprawione przed wdrożeniem produkcyjnym — oba łamią Art. 17 RODO w sposób bezpośredni. F-01 umożliwia dostęp do „usuniętego" konta przez stary token resetu. F-02 blokuje prawo do usunięcia dla użytkowników z kolizją shortId.

F-03 do F-06 powinny być naprawione w tym samym sprincie — dotyczą prawa do przenoszenia danych (Art. 20), poufności danych tymczasowych (Art. 5(1)(f)), retencji logów (Art. 5(1)(e)) i zarządzania podatnościami (NIS2).

Po naprawieniu F-01 i F-02 projekt może być wdrożony na środowisko testowe. Środowisko produkcyjne wymaga pełnej listy F-01..F-06.
