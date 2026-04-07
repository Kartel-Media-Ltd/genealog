# Kontekst: Person Discovery

**Feature:** Person Discovery  
**Data:** 2026-04-07

---

## Problem użytkownika

Użytkownik wpisuje imię i nazwisko nowej osoby w formularzu. System tworzy nowy rekord — nawet jeśli ta sama osoba (np. "Jan Kowalski ur. 1850") istnieje już w innym drzewie tego samego użytkownika albo w drzewie innego członka rodziny, który korzysta z systemu.

Brak mechanizmu:
- mapowania między duplikatami w obrębie jednego konta
- łączenia danych z zewnętrznych źródeł (FamilySearch, Geneteka)
- importu danych bez ręcznego przepisywania
- powiadamiania o potencjalnych powiązaniach między drzewami różnych użytkowników

Konsekwencja: każde drzewo jest izolowaną wyspą. Genealogia jako dyscyplina opiera się na budowaniu powiązań — brak discovery niszczy kluczową wartość systemu.

---

## Decyzje projektowe

### Hybrid matching (fingerprint + fuzzy)

Sam fingerprint SHA-256 `(firstName, lastName, birthYear, region)` nie łapie:
- literówek ("Kowalski" vs "Kowalska")
- wariantów imion ("Jan" vs "Johann" vs "Ivan")
- brakujących dat (fingerprint = null)

Dlatego: najpierw exact match (fingerprint), potem fuzzy (soundex + Levenshtein) gdy exactowych jest mniej niż 3. Confidence score pozwala sortować i filtrować wyniki (minimum 0.5).

### Manual approval — nie auto-import

Genealogia wymaga wysokiej pewności przed scaleniem danych. Auto-import mógłby:
- nadpisać dane poprawne danymi błędnymi
- scalić dwie różne osoby o tym samym imieniu i nazwisku
- wprowadzić dane z niezaufanego źródła

Decyzja: użytkownik zawsze zatwierdza import klikając "Akceptuj". Import uzupełnia brakujące pola — nie nadpisuje wypełnionych.

### MatchSourceInterface jako abstrakcja

Każde źródło danych (lokalne, cross-tree, FamilySearch, Geneteka, w przyszłości Grobonet) implementuje ten sam interfejs. Nowe źródło = nowy plik PHP + jedna linia rejestracji w `MatchSourceRegistry`. Bez modyfikacji istniejącego kodu.

### EventDispatcher in-process (nie message queue)

MVP nie wymaga kolejki. Hooki synchroniczne po `person.created/updated/deleted` są wystarczające dla:
- indeksowania w globalnym indeksie (szybkie — jeden INSERT/UPDATE)
- tworzenia sugestii i powiadomień (szybkie — kilka SELECT + INSERT)

W przyszłości: gdy wysyłka e-mail (async) lub duże drzewa (>10k osób), można przepiąć na Redis/cron bez zmiany interfejsu.

### DEFAULT opt-out (`is_indexed_globally = 0`) — RODO Art. 25 (K1)

Pierwotny plan miał `DEFAULT 1` (opt-out). Audyt bezpieczeństwa wskazał naruszenie RODO Art. 25 (Privacy by Design). Korekta: domyślnie indeksowanie wyłączone. Użytkownik musi aktywnie włączyć w ustawieniach drzewa. Oba warunki muszą być spełnione: `tree.is_indexed_globally = 1` ORAZ `tree.owner.discovery_opt_in = 1`.

### Cross-tree response anonimizowany (P4)

Odpowiedź cross-tree nie ujawnia:
- `tree_name` — zamiast tego `treeRef: "Drzewo #A4F8"` (hash z tree_id)
- `tree_id`, `person_id`, `owner_email`
- pełnej daty urodzenia — tylko rok
- `photo`, `notes`
- adresu — tylko województwo

Użytkownik widzi "Jan Kowalski, ur. 1850, mazowieckie — z Drzewo #A4F8". Musi kliknąć link kontaktowy, żeby dowiedzieć się więcej. Właściciel drugiego drzewa samodzielnie decyduje, czy udostępnić więcej informacji.

### Polish Soundex z `iconv` normalization (D3)

PHP `soundex()` nie rozumie polskich diakrytyków. "Ąbraszewski" i "Abraszewski" dają różne kody. Przed wywołaniem `soundex()` obowiązkowe: `iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name)` + `strtolower()` + `trim()`.

---

## Istniejące wzorce w kodzie

### IDOR-safe queries

```php
// src/Repositories/PersonRepository.php
public function findById(int $id, int $treeId): ?Person
// Pattern: zawsze waliduj dostęp przez tree_id w WHERE
```

Wszystkie endpointy Discovery muszą stosować ten sam pattern: nie pobierać rekordu po samym ID — zawsze dodawać `AND created_for_user = currentUserId` lub `AND tree_id IN (accessible_trees)`.

### Multi-tenant access

`tree_members(tree_id, user_id, role)` — centralna tabela kontroli dostępu. `SearchContext::accessibleTreeIds` jest budowany przez zapytanie do `tree_members` dla aktualnego użytkownika.

### CSRF + Session

`Csrf::verify()` i `Session::regenerate()` — obowiązkowe na wszystkich POST endpoints (import, reject, settings).

### RelationshipService::create jako wzorzec dla PersonImportService

`RelationshipService::create` waliduje dane, sprawdza duplikaty, tworzy inverse relationship — cały flow jako jedna transakcja DB. `PersonImportService::importFromMatch` powinien stosować ten sam wzorzec: walidacja, optimistic lock (UPDATE WHERE status='pending'), INSERT + ewentualny rollback.

---

## Co NIE jest w scope

| Temat | Powód wyłączenia |
|-------|-----------------|
| **Auto-merge dwóch profili** | Osobny feature: "Person Linking" — scalanie wymaga interfejsu konfliktu pól i historii wersji |
| **GraphQL API dla discovery** | REST wystarczy dla MVP; GraphQL dodałoby złożoność bez realnej korzyści |
| **WebSocket real-time notifications** | Polling co 30s jest wystarczający dla genealogii (niski czas krytyczny) |
| **Cross-tree DM/messaging** | Osobny feature — komunikacja między właścicielami drzew po wykryciu match |
| **Fan chart** | Osobny feature wizualizacji (plan print-pdf) |
| **Scraper Grobonet/PRADZIAD** | Plan `registries` — adaptery dla Person Discovery zostaną dodane w Fazie 7 po implementacji `registries` |
| **Automatyczna aktualizacja profilu** | Dane genealogiczne nie mogą być nadpisywane bez zgody — tylko uzupełnianie brakujących pól |

---

## Audyt — podsumowanie

Przeprowadzono pełny audyt bezpieczeństwa i RODO. Wyniki:

- **2 krytyczne (K1, K2)**: opt-out zamiast opt-in (K1 — RODO Art. 25), brak IDOR check na match endpoints (K2)
- **4 poważne (P1-P4)**: brak rate limit discovery (P1), fingerprint jako PII bez dokumentacji prawnej (P2), brak optimistic lock na import (P3), ujawnianie tree_name w cross-tree response (P4)
- **3 drobne (D1-D3)**: brak dedup powiadomień (D1), niejasna retencja audit log (D2), brak normalizacji polskich znaków w soundex (D3)

Wszystkie znaleziska zostały zaaplikowane do planu. Pełny raport: `person-discovery-audit.md`.
