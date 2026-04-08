# Audyt architektury — re-audyt backendu Genealog
**Data:** 2026-04-07
**Werdykt:** PASS WITH CONDITIONS
**Poprzedni audyt:** `dev/audit/backend/`
**Zakres:** coupling, DI, schema integrity, wydajność

---

## 1. Top 5 sprzężonych klas

### 1. DiscoveryController — 9 zależności (God Class)
**Plik:** `src/Controllers/DiscoveryController.php`

Kontroler orkiestruje wyszukiwanie globalne, fingerprint matching, rejestr zewnętrzny i notyfikacje. 9 wstrzykniętych serwisów bez zmiany od pierwszego audytu. Tworzony **2×** w `public/index.php` (linie 219 i 272) — każde tworzenie wiąże wszystkie 9 deps.

Symptomy God Class: metody obsługują discovery, matching i job polling w jednej klasie. Brak separacji odpowiedzialności.

---

### 2. DataExportService — 5 zależności + niejawna GedcomService
**Plik:** `src/Services/DataExportService.php`

`GedcomService` nie jest wstrzykiwany przez DI — tworzony przez `new GedcomService(...)` wewnątrz serwisu. Naruszenie zasady Dependency Inversion. Niemożliwe do testowania (nie można podmienić mockiem). `getPdo()` wywołanie na obiekcie DB (`$this->db->getPdo()`) eksponuje niskopoziomowy interfejs PDO powyżej warstwy repozytorium.

Dodatkowo: brak `try/finally` przy tworzeniu `$tmpDir` — wyciek plików przy wyjątku (patrz F-04 w audycie RODO).

---

### 3. AccountDeletionService — 3 zależności, dobra struktura, 2 luki
**Plik:** `src/Services/AccountDeletionService.php`

Projekt serwisu jest dobry (transakcja, anonimizacja). Dwie otwarte luki:
- Nie czyści `password_resets WHERE user_id = ?` (F-01 RODO — krytyczne),
- Nie czyści plików fizycznych ze storage (`storage/media/{tree_id}/`) — media pozostają na dysku po „usunięciu" konta.

Przy dużych kontach (10k osób) brak `set_time_limit(0)` może skończyć się HTTP 504.

---

### 4. AuthService — nullable RateLimiter + duplikacja SQL
**Plik:** `src/Services/AuthService.php`

`RateLimiter` jest nullable dependency. Gdy nie wstrzyknięty, `AuthService` zawiera fallback z własną logiką SQL (duplikat logiki `RateLimiter`). Rozbieżność między dwoma implementacjami tworzy lukę regresji. Brak jawnej wymaganości narusza zasadę explicite dependencies.

---

### 5. AuthController — 8 metod, nullable PasswordResetService
**Plik:** `src/Controllers/AuthController.php`

`PasswordResetService` jest nullable. Jeśli nie zostanie wstrzyknięty (błąd w composycji w `index.php`), metody reset-flow nie zwrócą błędu przy starcie — zamiast tego crashną przy runtime z `NullPointerException`-equivalent w PHP (`Call to member function on null`). Ukryty bug kompozycji.

---

## 2. Jakość Dependency Injection

### 56 wywołań `new` w public/index.php
**Plik:** `public/index.php`

Plik zawiera 56 wywołań `new ClassName(...)`. Całe DI jest ręczne, bez kontenera. Efekty:

- `InvitationController` tworzony **3×** (linie 168, 177, 218) — 3 osobne instancje z tymi samymi zależnościami,
- `DiscoveryController` tworzony **2×** (linie 219, 272),
- `AdminController` tworzony **2×** (linie 291, 297).

Powielanie instancji nie jest błędem funkcjonalnym, ale oznacza:
1. Niepotrzebne alokacje pamięci (każde `new` = nowy obiekt + konstruktor),
2. Niemożność współdzielenia stanu (np. cache w obiekcie),
3. Trudność w testowaniu konfiguracji `index.php`.

### Container.php istnieje ale nie jest używany
**Plik:** `src/Core/Container.php`

Kontener DI jest napisany i gotowy. Nie jest zintegrowany z `public/index.php`. Jest to dead code — gotowa infrastruktura bez użytkownika. Migracja do kontenera eliminuje duplikaty i upraszcza `index.php`.

### GedcomService nie wire'owany przez DI
**Plik:** `src/Services/GedcomService.php`

Tworzony przez `new` w 2 miejscach: bezpośrednio w `TreeController` i wewnątrz `DataExportService`. Jeśli konstruktor `GedcomService` kiedykolwiek otrzyma nową zależność, oba miejsca muszą być zaktualizowane ręcznie — klasyczny shotgun surgery.

---

## 3. Schema integrity

### Migracja 010 — OK
FK SET NULL poprawnie zdefiniowane dla kluczowych tabel. Relacje nie kaskadują kasowań (zgodnie z zasadą `ON DELETE RESTRICT` dla danych genealogicznych).

### `password_resets` — brak cleanup wygasłych tokenów
Tabela `password_resets` rośnie bez ograniczeń. Skrypt `bin/cleanup-audit-log.php` obsługuje audit log, ale nie ma analogicznego skryptu dla `password_resets`. Przy > 100k tokenów (aktywny serwis po 2 latach) zapytania do tabeli mogą być wolniejsze bez pomocy indeksu `expires_at`.

**Rekomendacja:** dodać `DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 7 DAY` do zadania cron.

### Brak indeksu na `password_resets(expires_at)`
Przy purge wygasłych tokenów full scan tabeli. Przy dużej liczbie użytkowników — wolno.

---

## 4. Performance findings

### AccountDeletionService — brak `set_time_limit`
**Plik:** `src/Services/AccountDeletionService.php`

Przy koncie z 10 000 osób i wszystkimi relacjami, operacja DELETE/UPDATE może przekroczyć domyślny `max_execution_time = 30s`. `GedcomService::import()` ma już `set_time_limit(300)` — analogicznie należy go dodać do `deleteAccount()`.

### DataExportService — pamięć przy 50k osób
**Plik:** `src/Services/DataExportService.php`

Eksport dużego drzewa (50k osób) generuje string GEDCOM/JSON w pamięci przed zapisem do ZIP. Przy 50k osób est. 20-50 MB stringów w RAM. Brak streamingu do pliku tymczasowego.

**Mitigation (krótkoterminowa):** `ini_set('memory_limit', '256M')` + `set_time_limit(120)` na początku metody.
**Mitigation (długoterminowa):** streaming do `$tmpFile = tmpfile()` zamiast budowania stringa.

---

## 5. Co architektonicznie nadal źle

1. **`Container.php` ignorowany** — gotowy kontener DI, ale `index.php` zawiera 56 ręcznych `new`. Dead code gotowy do użycia.
2. **`EventDispatcher` — static state** — globalne static state utrudnia testowanie jednostkowe. Nie można podmienić w teście bez refleksji lub resetu statycznego stanu.
3. **`GedcomService` — 775 LOC, tworzony przez `new` w 2 miejscach** — monolityczny serwis bez DI, łączy parsing, walidację i budowanie hierarchii.
4. **`DiscoveryController` — 9 deps, bez zmian** — God Class nie podzielona na mniejsze kontrolery mimo poprzednich rekomendacji.
5. **Session storage = pliki** — brak Redis oznacza brak horyzontalnego skalowania. Przy 2+ instancjach aplikacji sesje nie są współdzielone.
6. **`bin/process-async-jobs.php` nie istnieje** — tabela `search_jobs` jest zdefiniowana w schemacie, Python scraper ją polluje, ale nie ma żadnego PHP skryptu procesującego jobs. Brakujące ogniwo w pipeline scrapera.
7. **`AccountDeletionService` nie czyści plików fizycznych** — po anonimizacji konta, pliki w `storage/media/{tree_id}/` pozostają na dysku. Naruszenie Art. 17 RODO (right to erasure obejmuje kopie fizyczne).

---

## 6. Top 5 rekomendacji architektonicznych

### Rek. 1 — Aktywuj Container.php (S, est. 4h)
Zastąpić ręczną kompozycję w `public/index.php` wywołaniami `$container->get(ClassName::class)`. Eliminuje 56 `new`, 3× InvitationController, 2× DiscoveryController, 2× AdminController. Wymaga zdefiniowania bindings w kontenerze — struktura już istnieje.

### Rek. 2 — Wstrzyknij GedcomService przez DI (S, est. 2h)
Dodać `GedcomService` do kontenera i wstrzykiwać przez konstruktor w `TreeController` i `DataExportService`. Eliminuje shotgun surgery i umożliwia mockowanie w testach.

### Rek. 3 — Cleanup w AccountDeletionService (XS, est. 1h)
Dodać:
```php
DELETE FROM password_resets WHERE user_id = ?       // F-01 RODO
// + usunięcie plików fizycznych: storage/media/{tree_id}/
array_map('unlink', glob(STORAGE_PATH . "/media/{$treeId}/*"));
```

### Rek. 4 — EventDispatcher: static → instance (M, est. 8h)
Przekształcić z globalnego stanu statycznego na instancję wstrzykiwaną przez DI. Warunek wstępny: aktywacja kontenera (Rek. 1). Umożliwia testowanie event-driven logiki (matchowanie fingerprintów, powiadomienia).

### Rek. 5 — Session handler Redis (M, est. 6h + infra)
Zastąpić plikowy handler sesji PHP adapterem Redis (`session.save_handler = redis`). Warunek wstępny: Redis w infrastrukturze (Docker Compose). Umożliwia horizontal scaling i centralne unieważnianie sesji (kluczowe dla `session_version`).

---

## 7. Werdykt

**PASS WITH CONDITIONS**

Architektura jest czytelna i spójna w warstwie domen. Podstawowy problem to brak aktywacji istniejącego `Container.php` i duplikaty instancji w `index.php`. Żaden z problemów architektonicznych nie jest blokerem dla wdrożenia na staging, ale Rek. 1-3 powinny być wykonane w bieżącym sprincie — szczególnie Rek. 3 (cleanup w AccountDeletionService) ze względu na implikacje RODO.
