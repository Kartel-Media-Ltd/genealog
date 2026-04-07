# Audit: Global Admin Panel

## KRYTYCZNE

### [K1] Eskalacja sesji przez impersonację — brak regeneracji sesji
**Problem:** Bez `session_regenerate_id()` po zmianie kontekstu sesji, atakujący z dostępem do cookie może przejąć sesję admina lub impersonowanego usera.
**Naprawa:** `Session::regenerate(true)` ZARÓWNO przy starcie impersonacji JAK I przy jej zakończeniu.

### [K2] Admin może zablokować samego siebie
**Problem:** Brak sprawdzenia `$targetId !== $adminId` w `AdminController::block()` — admin może zablokować własne konto i stracić dostęp do panelu.
**Naprawa:** W `AdminController::block()` rzuć wyjątek jeśli `$targetUserId === Session::get('user_id')`.

## POWAŻNE

### [P1] `is_admin` odczytywane tylko z sesji — ryzyko przy aktywnej sesji po degradacji
**Problem:** Jeśli admin zostanie zdegradowany (`demote`) przez innego admina, jego aktywna sesja nadal ma `is_admin=true` do wylogowania.
**Naprawa:** Sprawdzaj `is_admin` z bazy raz na request (np. w `AdminMiddleware`) lub invaliduj sesje po demote (trudne bez Redis). **Akceptowalne ryzyko MVP** — loguj demote do admin_logs.

### [P2] Admin może impersonować admina (privilege bypass)
**Problem:** Gdyby impersonacja admina była możliwa, impersonujący uzyska `is_admin=false` w sesji — ale target mógłby mieć `_admin_user_id` nadpisane.
**Naprawa:** W `AdminService::impersonate()` sprawdź `if ($target->isAdmin) throw ...` PRZED zmianą sesji.

### [P3] Brak CSRF na GET endpointach — nie dotyczy
GET `/admin/*` nie modyfikuje stanu — brak problemu. Potwierdzono że wszystkie POST mają `verifyCsrf()`.

### [P4] Paginacja `/admin/logs` — SQL injection przez sortowanie
**Problem:** Jeśli parametr `?sort=created_at` będzie przekazywany do zapytania bez whitelist.
**Naprawa:** `$allowed = ['created_at', 'action', 'admin_id']; $sort = in_array($_GET['sort'], $allowed) ? $_GET['sort'] : 'created_at';`

## DROBNE

### [D1] Banner impersonacji może być pominięty przez użytkownika (CSS)
Ryzyko minimalne — tylko admin może uruchomić impersonację i wie co robi.

### [D2] `admin_logs.meta` — JSON w kolumnie MySQL
Wymaga MySQL 5.7+ lub MariaDB 10.2+ z obsługą JSON. Projekt używa MariaDB (Docker) — OK.

### [D3] Brak timeout impersonacji
Impersonacja trwa do ręcznego zakończenia lub wygaśnięcia sesji (8h). Akceptowalne dla MVP.

## POZYTYWNE
- Session::regenerate() jest już zaimplementowane w Session::regenerate(true)
- CSRF tokeny są rotowane po każdym verify (fix K1 z audytu #1)
- Rate limiting na /login chroni przed brute force na konta adminów
- ON DELETE RESTRICT w FK admin_logs.admin_id — logi nie kasują się przy usunięciu konta

## PODSUMOWANIE
**Ocena: PASS WITH CONDITIONS**
- K1 i K2 muszą być naprawione przed deployem
- P1 akceptowalne dla MVP (dokumentować jako known limitation)
- P2 naprawić w AdminService przed implementacją
