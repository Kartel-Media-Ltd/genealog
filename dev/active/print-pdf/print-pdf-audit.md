# Audyt bezpieczeństwa: Feature Druk/PDF — Genealog

Data audytu: 2026-04-07
Audytor: Claude Code (architect review)
Wersja planu: 1.0

---

## Zakres

Feature dodaje dwie nowe trasy HTTP (GET) oraz jeden plik JS po stronie klienta:

- `GET /trees/{id}/print` — strona druku SVG drzewa
- `GET /trees/{id}/persons/print` — strona druku listy osób
- `public/js/print-helper.js` — eksport SVG → PNG (client-side)

---

## 1. Kontrola dostępu (IDOR / Broken Access Control)

### Wektory ryzyka

**IDOR na parametrze `{id}`**: użytkownik zmienia ID drzewa w URL i uzyskuje dostęp do cudzego drzewa lub listy osób.

### Mitygacja

- Obie trasy przechodzą przez `TreeAccessMiddleware` — ten sam mechanizm co `/trees/{id}` i `/trees/{id}/persons`
- Middleware sprawdza: `tree_members.user_id = SESSION.user_id` LUB `trees.owner_id = SESSION.user_id`
- W przypadku braku dostępu: redirect do `/trees` (nie ujawniamy istnienia drzewa — 302, nie 403)

```php
// Weryfikacja w kontrolerze (dodatkowa warstwa po middleware):
$tree = $this->treeRepository->findByIdForUser($id, $this->session->getUserId());
if (!$tree) {
    $this->response->redirect('/trees');
    return;
}
```

**Ocena**: NISKIE RYZYKO — kontrola dostępu wielowarstwowa (middleware + kontroler).

---

## 2. Autentykacja

- Obie trasy wymagają zalogowanej sesji — brak sesji → redirect do `/login`
- Mechanizm identyczny jak w pozostałych chronionych trasach projektu
- Brak tras publicznych (drzewa `is_public = 1` nie są uwzględnione w tej fazie — do rozważenia w przyszłości)

**Ocena**: NISKIE RYZYKO.

---

## 3. XSS (Cross-Site Scripting)

### Wektory ryzyka

Dane z bazy (imię, nazwisko, miejsce urodzenia, nazwa drzewa) renderowane w HTML bez escapowania.

### Mitygacja

- Wszystkie wartości z DB owijane `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` przed renderingiem
- `$treeId` jest rzutowany na `int` przez sygnaturę metody kontrolera (`int $id`) — żadne znaki specjalne niemożliwe
- Brak `echo $_GET[...]` ani `echo $_POST[...]` w widokach druku

**Pola wymagające escapowania w persons-print.php:**
| Pole | Typ | Escapowanie |
|------|-----|-------------|
| `first_name` | string | `htmlspecialchars()` |
| `last_name` | string | `htmlspecialchars()` |
| `maiden_name` | string\|null | `htmlspecialchars()` + fallback `—` |
| `birth_date` | date\|null | `htmlspecialchars()` + fallback `—` |
| `birth_place` | string\|null | `htmlspecialchars()` + fallback `—` |
| `death_date` | date\|null | `htmlspecialchars()` + fallback `—` |
| `death_place` | string\|null | `htmlspecialchars()` + fallback `—` |
| `tree->name` | string | `htmlspecialchars()` |

**Ocena**: NISKIE RYZYKO — przy zachowaniu konwencji `htmlspecialchars` na wszystkich danych.

---

## 4. SQL Injection

### Wektory ryzyka

Nowa metoda `PersonRepository::findByTreeSortedByName(int $treeId)` buduje zapytanie SQL.

### Mitygacja

- Użycie PDO prepared statements z named placeholder `:tree_id`
- Parametr `$treeId` ma typ `int` (PHP strict_types) — żaden ciąg znaków nie może być przekazany
- Brak dynamicznego budowania ORDER BY (stała wartość `last_name, first_name`)

```php
$stmt = $this->db->prepare(
    'SELECT ... FROM persons WHERE tree_id = :tree_id ORDER BY last_name, first_name'
);
$stmt->execute(['tree_id' => $treeId]);
```

**Ocena**: BRAK RYZYKA — prepared statements + typowanie.

---

## 5. Content-Type i nagłówki HTTP

- Obie strony druku zwracają `text/html` — brak zmian Content-Type
- Brak generowania plików PDF/SVG po stronie serwera (zero serwerowego przetwarzania binarnego)
- Eksport PNG: całkowicie client-side (`canvas.toDataURL()`) — serwer nie przetwarza żadnych plików

**Brak nowych wektorów związanych z Content-Type.**

---

## 6. Client-side security (print-helper.js)

### Wektory ryzyka

**SVG injection przez DOM**: `XMLSerializer.serializeToString(svgElement)` serializuje aktualny DOM SVG. Jeśli SVG zawiera wstrzyknięte elementy `<script>` (poprzez XSS w D3 data), zostaną zserializowane do Bloba.

### Mitygacja

- SVG generowany wyłącznie przez D3.js na podstawie danych z API
- API `/api/trees/{id}/persons` zwraca JSON z danymi escapowanymi po stronie PHP
- D3 wstawia tekst przez `.text()` (nie `.html()`) — brak interpretacji HTML
- PNG export jest jednostronny (serwer nic nie otrzymuje)
- `URL.revokeObjectURL()` wywołane po użyciu — brak wycieków pamięci

**Ocena**: NISKIE RYZYKO — atak możliwy tylko jeśli D3 data jest skompromitowana, co wymagałoby wcześniejszego XSS w API.

---

## 7. CSRF

- Obie nowe trasy to **GET** (tylko odczyt, brak side effects)
- CSRF tokeny nie są wymagane dla GET requests
- Brak formularzy POST w nowych widokach

**Brak ryzyka CSRF.**

---

## 8. Rate limiting

- Trasy `/print` są stronicami HTML — cięższe niż typowe API, ale lżejsze niż `/api/trees/{id}/persons`
- Brak dedykowanego rate limitingu dla stron druku (jak w całym projekcie — rate limiting tylko dla auth)
- Ryzyko: bot może generować dziesiątki żądań druku/sekundy

**Rekomendacja (opcjonalna)**: Dla dojrzalszej wersji dodać `Cache-Control: private, max-age=60` na stronach druku — przeglądarka nie odpyta ponownie przez minutę.

**Ocena**: AKCEPTOWALNE RYZYKO dla MVP.

---

## 9. Prywatność danych (RODO)

### Lista osób do druku

- Pobierana przez `PersonRepository::findByTreeSortedByName` — tylko osoby należące do danego drzewa
- Użytkownik drukuje swoje dane (lub dane drzewa do którego ma dostęp) — zgodne z RODO (prawo do przetwarzania własnych danych)
- Pola `visibility`, `is_living` nie mają wpływu na listę druku — właściciel/edytor widzi wszystkie osoby swojego drzewa (celowe zachowanie)

### Rekomendacja (post-MVP)

Rozważyć dodanie kolumny `visibility` do listy druku — ukrycie osób `private` dla roli `viewer`.

---

## 10. Otwarte karty / window.open

- Przyciski w `show.php` i `persons/index.php` używają `target="_blank"` (link `<a>`)
- Brak `rel="noopener noreferrer"` nie jest potrzebny dla linków do tej samej domeny
- Gdyby linki prowadziły do zewnętrznych domen — wymagane `rel="noopener noreferrer"`

**Brak ryzyka.**

---

## Podsumowanie

| Kategoria | Ryzyko | Status |
|-----------|--------|--------|
| IDOR / Kontrola dostępu | NISKIE | Mitygowane (middleware + kontroler) |
| Autentykacja | NISKIE | Mitygowane (sesja PHP) |
| XSS | NISKIE | Mitygowane (htmlspecialchars wszędzie) |
| SQL Injection | BRAK | Mitygowane (PDO prepared statements) |
| CSRF | BRAK | N/A (tylko GET) |
| Content-Type | BRAK | Tylko HTML, brak binarnych endpointów |
| Client-side (PNG export) | NISKIE | Akceptowalne (brak server-side processing) |
| Rate limiting | AKCEPTOWALNE | MVP — do rozważenia later |
| RODO | AKCEPTOWALNE | Właściciel drukuje własne dane |

**Decyzja**: Feature gotowy do implementacji bez blokerów bezpieczeństwa. Zalecenia post-MVP: rate limiting stron druku + filtrowanie `visibility` dla roli `viewer`.
