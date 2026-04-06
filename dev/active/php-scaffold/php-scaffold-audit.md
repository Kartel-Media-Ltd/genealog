# Raport audytu bezpieczeństwa — Inicjalizacja PHP (Genealog)

**Data:** 2026-04-07 | **Typ:** STANDARD | **Wynik: PASS WITH CONDITIONS**
**Audytor:** Self-audit Sonnet | Gemini: niedostępny

---

## KRYTYCZNE

### [K1] Wyciek credentials SMTP w .env.local
**Problem:** Plik `.env.local` zawiera aktywne dane dostępowe SMTP z projektu `festiwal` (`ELTGTTfYLnECvXbTsWSS`, `festiwal@support-24.co.uk`). Plik `.history/` zawiera historyczne snapshoty. Jeśli trafią do git — skompromitowane na stałe.
**Skutek:** Nieautoryzowane wysyłanie maili, naruszenie RODO Art. 32.
**Naprawa:**
1. Natychmiast zrotować hasło SMTP
2. `grep -r "ELTGTTfYLnECvXbTsWSS" .git/` — weryfikacja czy nie trafiło do historii
3. Dodać `.history/` do `.gitignore`
4. `.env.local` musi być w `.gitignore` — weryfikacja przed każdym `git add`

### [K2] Brak nagłówków HTTP bezpieczeństwa
**Problem:** Szablony AppLayout i AuthLayout nie wysyłają CSP, X-Frame-Options, X-Content-Type-Options.
**Skutek:** XSS (CDN bez SRI), clickjacking, MIME sniffing.
**Naprawa — w `public/index.php` (przed dispatch):**
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; frame-ancestors 'none'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
```

---

## POWAŻNE

### [P1] CDN bez Subresource Integrity (SRI)
Tailwind CDN i Alpine.js CDN bez `integrity` + `crossorigin`. Supply chain attack.
**Naprawa:** Dodaj `integrity="sha384-[hash]" crossorigin="anonymous"` lub zbundluj lokalnie przez Vite/esbuild.

### [P2] SESSION_SECRET słaby/przewidywalny
`.env.local` zawiera `dev_local_salt_32chars_minimum_ok` — literalny string, nie kryptograficzny sekret.
**Naprawa:** `php -r "echo bin2hex(random_bytes(32));"` → wkleić do `.env.local`.

### [P3] Brak endpointu /forgot-password
Formularz login.php zawiera link do `/forgot-password` ale router go nie definiuje.
**Naprawa:** Dodać do planu Fazy 4 lub oznaczyć jako placeholder z 501 Not Implemented.

### [P4] Brak absolute session timeout
Brak limitu bezwzględnego sesji (max 8h), tylko idle timeout.
**Naprawa — Session::start():**
```php
if (isset($_SESSION['_created_at']) && time() - $_SESSION['_created_at'] > 28800) {
    session_destroy();
    header('Location: /login');
    exit;
}
$_SESSION['_created_at'] ??= time();
```

---

## DROBNE

### [D1] Inicjały użytkownika w aria-label (RODO minimalizacja)
`site-header.php` — pełne imię w atrybucie aria. Rozważ `aria-label="Twoje konto"`.

### [D2] Password strength tylko client-side
Alpine.js miernik siły hasła — AuthService musi egzekwować tę samą politykę server-side (min 8 znaków, cyfra lub wielka litera).

### [D3] APP_ENV=development ryzyko w deploy
Zmiana na produkcji musi być świadoma — inaczej stack traces publicznie widoczne.

### [D4] Brak autocomplete policy
Rozważyć `autocomplete="off"` lub explicit `autocomplete="current-password"` wg kontekstu.

---

## POZYTYWNE
- ✅ `htmlspecialchars()` konsekwentnie we wszystkich widokach
- ✅ CSRF: `hash_equals()` (timing-safe) we wszystkich formularzach POST
- ✅ `password_hash(BCRYPT, cost=12)` — powyżej domyślnego
- ✅ `Session::regenerate(true)` przed `user_id` — ochrona session fixation
- ✅ Rate limiting w DB (odporne na restart PHP-FPM)
- ✅ UUID generowany po stronie PHP — ochrona przed ID enumeration
- ✅ Password nie zwracane do `<input value>` dla type=password
- ✅ `src/` i `storage/` poza `public/` — prawidłowa separacja webroot
- ✅ AuthMiddleware na chronionych trasach

---

## Warunki przejścia do implementacji
1. ✅ Zrotować credentials SMTP w .env.local [K1]
2. ✅ Dodać HTTP security headers w index.php [K2]
3. ✅ Wygenerować losowy SESSION_SECRET [P2]
4. ✅ Dodać `.history/` do .gitignore
