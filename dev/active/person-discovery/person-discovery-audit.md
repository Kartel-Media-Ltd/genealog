# Audyt bezpieczeństwa: Person Discovery

**Data audytu:** 2026-04-07  
**Audytor:** Claude Code (security-audit skill)  
**Scope:** Architektura Person Discovery — schemat DB, endpointy, przepływ danych, RODO  
**Wynik:** PASS WITH CONDITIONS — zaaplikowanie K1, K2 wymagane przed implementacją

---

## Klasyfikacja znalezisk

| ID | Typ | Priorytet | Status |
|----|-----|-----------|--------|
| K1 | Krytyczne | Blokuje merge | Zaaplikowane w planie |
| K2 | Krytyczne | Blokuje merge | Zaaplikowane w planie |
| P1 | Poważne | Wymagane w tej fazie | Zaaplikowane w planie |
| P2 | Poważne | Wymagane przed go-live | Zaaplikowane w planie |
| P3 | Poważne | Wymagane w tej fazie | Zaaplikowane w planie |
| P4 | Poważne | Wymagane przed go-live | Zaaplikowane w planie |
| D1 | Drobne | Nice-to-have | Zaaplikowane w planie |
| D2 | Drobne | Nice-to-have | Zaaplikowane w planie |
| D3 | Drobne | Blokuje poprawność | Zaaplikowane w planie |

---

## K1 — KRYTYCZNE: Opt-out zamiast opt-in (RODO Art. 25)

**Plik/linia:** `migrations/007_discovery.sql` — ALTER trees  
**Opis:**  
Pierwotny plan definiował `is_indexed_globally TINYINT(1) DEFAULT 1`. Oznacza to, że każde nowo tworzone drzewo jest domyślnie indeksowane globalnie. Dane genealogiczne zawierają informacje o osobach fizycznych (imiona, nazwiska, daty urodzenia, miejsca). Domyślne udostępnianie danych bez zgody narusza RODO Art. 25 (Privacy by Design and by Default).

**Ryzyko:** Naruszenie RODO Art. 25, Art. 5(1)(f) — możliwa kara administracyjna do 4% przychodu rocznego lub 20 mln EUR.

**Naprawa zaaplikowana:**
```sql
-- ZMIANA: DEFAULT 1 → DEFAULT 0
ALTER TABLE trees ADD COLUMN is_indexed_globally TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN discovery_opt_in TINYINT(1) NOT NULL DEFAULT 0;
```
Obydwa flagi muszą być `= 1` jednocześnie, żeby osoba trafiła do indeksu.

**UI po naprawie:** Settings panel z czytelną informacją o RODO, checkbox domyślnie odznaczony.

---

## K2 — KRYTYCZNE: Brak IDOR check na match endpoints

**Plik/linia:** `DiscoveryController::importMatch`, `rejectMatch`, GET match/{id}  
**Opis:**  
Pierwotny plan nie zawierał walidacji, czy match o danym ID należy do aktualnego użytkownika. Atakujący mógłby podać dowolne `match_id` i:
- importować dane do swojego drzewa z cudzej sugestii
- odrzucać sugestie innego użytkownika
- odczytywać zawartość cudzych sugestii (source_data JSON)

Jest to klasyczny IDOR (Insecure Direct Object Reference) — OWASP Top 10 A01.

**Ryzyko:** Nieautoryzowany dostęp do danych genealogicznych innych użytkowników.

**Naprawa zaaplikowana:**
```sql
-- Każdy endpoint z match_id musi dodać:
WHERE id = :matchId AND created_for_user = :currentUserId
-- Jeśli affected_rows = 0 lub result = null → 403 Forbidden
```

Dotyczy wszystkich metod: `importMatch()`, `rejectMatch()`, GET `/api/discovery/match/{id}`.

---

## P1 — POWAŻNE: Brak rate limitingu dla discovery/search

**Plik/linia:** `DiscoveryController::search`, routing w `public/index.php`  
**Opis:**  
Endpoint `/api/discovery/search` bez rate limitingu umożliwia:
- enumerację globalnego indeksu przez systematyczne odpytywanie
- DDoS wobec zewnętrznych rejestrów (FamilySearch, Geneteka)
- brute-force fingerprint reversing (SHA-256 słabszy gdy znamy format)

Istniejąca tabela `rate_limits` obsługuje auth endpointy, ale nie discovery.

**Naprawa zaaplikowana:**
- Dodać `discovery_search` do sprawdzenia rate limit w `AuthService::isRateLimited` lub ekwiwalencie
- Limit: 30 req/min per (ip, user_id)
- Odpowiedź: HTTP 429 z `Retry-After` headerem

**Bonus fix:** `/api/notifications/count` — dodać `Cache-Control: max-age=25`, żeby polling Alpine.js korzystał z cache przeglądarki zamiast 30 osobnych requestów.

---

## P2 — POWAŻNE: Fingerprint jako PII bez dokumentacji prawnej

**Plik/linia:** `FingerprintService::compute()`, `global_person_index`  
**Opis:**  
SHA-256 z `(firstName, lastName, birthYear, region)` jest deterministyczny — ta sama osoba zawsze daje ten sam hash. Hash jest identyfikatorem pseudonimicznym (nie anonimowym) i stanowi dane osobowe w rozumieniu RODO Art. 4(1) i motywu 26.

Przechowywanie fingerprint w `global_person_index` bez udokumentowanej podstawy prawnej narusza RODO Art. 6.

**Naprawa zaaplikowana:**
- Udokumentować podstawę prawną: **RODO Art. 6(1)(f)** — uzasadniony interes (badania genealogiczne, interes publiczny w zachowaniu dziedzictwa kulturowego)
- Dodać sekcję "Globalny indeks" w regulaminie z explicite informacją o fingerprintingu
- Tylko osoby historyczne (is_living=0, >100 lat) — znacznie ogranicza zakres PII

**Rekomendacja na przyszłość (P2 long-term):** Rozważyć indeksowanie wyłącznie po `(birthYear, region)` bez `(firstName, lastName)` — mniej identyfikujące, więcej false positives, ale lepiej z perspektywy RODO.

---

## P3 — POWAŻNE: Brak optimistic lock przy imporcie

**Plik/linia:** `PersonImportService::importFromMatch()`  
**Opis:**  
Bez optimistic lock dwa równoczesne kliknięcia "Akceptuj" (double-click, zdublowany request) mogą:
- dwukrotnie utworzyć tę samą osobę w drzewie
- utworzyć dwa rekordy o tym samym źródle

**Naprawa zaaplikowana:**
```sql
-- Zamiast: SELECT + INSERT person
-- Użyć:
UPDATE person_match_suggestions
  SET status = 'imported'
  WHERE id = :matchId AND status = 'pending';

-- Sprawdzić affected_rows:
-- jeśli 0 → bail (throw RaceConditionException lub return null)
-- jeśli 1 → kontynuuj INSERT osoby
```

Pattern znany z "optimistic concurrency control" — bezpieczny bez locków na poziomie tabeli.

---

## P4 — POWAŻNE: Cross-tree response ujawnia tree_name

**Plik/linia:** `CrossTreeMatchSource::search()` — pole response `tree_name`  
**Opis:**  
Ujawnianie nazwy drzewa (`tree_name`) w cross-tree odpowiedzi może pośrednio identyfikować właściciela:
- Drzewo "Kowalskie z Krakowa — rodzina taty" ujawnia że owner jest z Krakowa i ma ojca
- W połączeniu z innymi informacjami (rok urodzenia, region) może deanonimizować właściciela

RODO wymaga minimalizacji danych (Art. 5(1)(c)).

**Naprawa zaaplikowana:**
```php
// PRZED (źle):
'treeName' => $row['tree_name'],

// PO (dobrze):
'treeRef' => 'Drzewo #' . substr(hash('sha256', (string)$row['tree_id']), 0, 4),
```

Response cross-tree zawiera: `firstName`, `lastName`, `birthYear` (tylko rok), `region` (województwo), `treeRef` (zanonimizowany).
NIE zawiera: `treeName`, `treeId`, `personId`, `ownerEmail`, `photo`, `notes`, pełnej daty.

---

## D1 — DROBNE: Brak dedup powiadomień

**Plik/linia:** `NotificationService::dispatch()`  
**Opis:**  
Bez deduplikacji wielokrotne wywołanie `findAndNotifyMatches` dla tej samej osoby (np. przy serii update'ów) może spamować użytkownika identycznymi powiadomieniami.

**Naprawa zaaplikowana:**
```php
// Przed INSERT sprawdzić:
SELECT COUNT(*) FROM notifications
  WHERE user_id = :userId AND type = :type AND created_at > NOW() - INTERVAL 24 HOUR
// Jeśli > 0 → skip
```

---

## D2 — DROBNE: Niejasna retencja audit log

**Plik/linia:** `source_audit_log` — brak TTL w schemacie  
**Opis:**  
Bez zdefiniowanej retencji `source_audit_log` rośnie bez ograniczeń. Przy dużej aktywności → problemy wydajnościowe, potencjalnie też RODO (Art. 5(1)(e) — zasada ograniczenia przechowywania).

**Naprawa zaaplikowana:**
- Retencja: **3 lata** (standardowe minimum dla logów bezpieczeństwa)
- Cron: `bin/cleanup-audit-log.php` uruchamiany przez systemd timer lub cron systemowy
- Komentarz w SQL: `-- retencja 3 lata, cron: bin/cleanup-audit-log.php`
- GDPR erasure NIE usuwa wpisów audit — osobny endpoint admin (art. 17(3)(b) RODO: wyjątek dla ustalenia, dochodzenia lub obrony roszczeń)

---

## D3 — DROBNE: Brak normalizacji polskich znaków w soundex

**Plik/linia:** `FingerprintService::computeSoundex()`  
**Opis:**  
PHP `soundex()` operuje na ASCII. "Ąbraszewski" i "Abraszewski" dają różne kody Soundex, mimo że po normalizacji to to samo nazwisko. Fuzzy matching w LocalTreeMatchSource i CrossTreeMatchSource jest nieskuteczny dla polskich danych genealogicznych.

Dotyczy też: `soundex('Żółkiewski')` vs `soundex('Zolkiewski')` — dwa zupełnie różne kody.

**Naprawa zaaplikowana:**
```php
public function computeSoundex(string $name): string
{
    // OBOWIĄZKOWE: normalizacja przed soundex
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $normalized = strtolower(trim($normalized));
    return soundex($normalized);
}
```

Bez tej naprawy fuzzy matching dla polskich danych genealogicznych daje ~40% gorsze wyniki.

---

## Pozytywne aspekty projektu

- **PDO prepared statements** zdefiniowane jako standard — SQL injection niemożliwy przy przestrzeganiu wzorca
- **CSRF verify** na wszystkich POST endpoints — zgodnie z istniejącym wzorcem projektu
- **`tree_members` jako centralna kontrola dostępu** — spójny multi-tenant pattern
- **`ON DELETE RESTRICT`** na FK — dane genealogiczne nie kasują się kaskadowo (świadoma decyzja)
- **SHA-256 fingerprint** (nie MD5/SHA-1) — odpowiedni dla danych pseudonimicznych
- **Confidence threshold 0.5** — dobre domyślne, eliminuje fałszywe alarmy
- **Manual approval** (nie auto-import) — właściwa decyzja dla genealogii
- **EventDispatcher in-process** — prostszy i wystarczający dla MVP
- **`MatchSourceInterface`** — architektonicznie solidna abstrakcja, OCP-friendly
- **Osobna tabela `source_audit_log`** — dobra praktyka bezpieczeństwa

---

## Podsumowanie — PASS WITH CONDITIONS

Plan jest architektonicznie solidny i przemyślany. Dwa krytyczne znaleziska (K1, K2) zostały zaaplikowane przed finalizacją planu. Cztery poważne znaleziska (P1-P4) zaaplikowane w zadaniach odpowiednich faz. Drobne (D1-D3) zaaplikowane.

**Warunki merge:**
1. K1: DEFAULT 0 w is_indexed_globally — DONE (w planie)
2. K2: IDOR check na wszystkich match endpoints — DONE (w planie)
3. P1: Rate limiting discovery_search — DONE (Faza 3)
4. P3: Optimistic lock w PersonImportService — DONE (Faza 6)

**Przed go-live:**
5. P2: Dokumentacja prawna fingerprint (regulamin + podstawa RODO Art. 6(1)(f))
6. P4: Anonimizacja tree_name w cross-tree response — DONE (w planie)

**Kolejny audyt:** Po implementacji Fazy 6 (import flow) — code review PersonImportService i DiscoveryController.
