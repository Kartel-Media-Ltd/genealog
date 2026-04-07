# Rejestry Zewnętrzne — Audyt Bezpieczeństwa

## Podsumowanie ryzyk

| Kategoria | Ryzyko | Poziom | Mitygacja |
|-----------|--------|--------|-----------|
| Klucze API | Wyciek do kodu/git | KRYTYCZNY | `.env.local` + `.gitignore` |
| SSRF | Dowolny URL w requescie | WYSOKI | Hardkodowane URL w serwisach |
| OAuth2 token | Wyciek tokenu | WYSOKI | Sesja PHP, nie localStorage |
| Rate limiting | Abuse endpointu /search | ŚREDNI | Max 10 req/min per IP |
| SQL Injection | Wstrzyknięcie w params | WYSOKI | PDO prepared statements |
| Scraping | Blokada IP | ŚREDNI | User-Agent, delays, robots.txt |
| CSV Import | Duże pliki, DoS | NISKI | CLI tylko, nie HTTP upload |
| Cache | Serwowanie cudzych danych | NISKI | Cache po `query_hash`, nie per user |

---

## 1. Zarządzanie kluczami API

### Zasada

Żaden klucz API, token OAuth2 ani secret nie może znaleźć się w kodzie źródłowym ani historii git.

### Implementacja

```php
// config/config.php — odczyt przez getenv(), NIE hardkodowanie
return [
    'familysearch' => [
        'client_id'     => getenv('FAMILYSEARCH_CLIENT_ID'),
        'client_secret' => getenv('FAMILYSEARCH_CLIENT_SECRET'),
        'env'           => getenv('FAMILYSEARCH_ENV') ?: 'sandbox',
    ],
    'archives' => [
        'api_key' => getenv('ARCHIVES_API_KEY'),
    ],
];
```

### Wymagania dla `.env.local`

```
# .env.local — NIE commitować do git
FAMILYSEARCH_CLIENT_ID=
FAMILYSEARCH_CLIENT_SECRET=
FAMILYSEARCH_ENV=sandbox
ARCHIVES_API_KEY=
```

### Weryfikacja

- [ ] `.env.local` jest w `.gitignore`
- [ ] `git grep FAMILYSEARCH_CLIENT_SECRET src/` → brak wyników
- [ ] Wdrożenie: klucze przez zmienne środowiskowe serwera lub Vault (nie przez git)

---

## 2. Rate Limiting na `/search`

### Cel

Ochrona przed nadużyciem (bruteforce nazwisk, flood API zewnętrznych).

### Implementacja

```php
// src/Core/RateLimiter.php — istniejący wzorzec z auth
class RateLimiter
{
    private const SEARCH_LIMIT  = 10;   // max requestów
    private const SEARCH_WINDOW = 60;   // sekund

    public function checkSearch(string $ip): bool
    {
        return $this->check($ip, 'search', self::SEARCH_LIMIT, self::SEARCH_WINDOW);
    }
}
```

```php
// SearchController::processSearch() — pierwsza linia
if (!$this->rateLimiter->checkSearch($request->ip())) {
    http_response_code(429);
    include __DIR__ . '/../Views/errors/429.php';
    exit;
}
```

### Konfiguracja

- Tabela `rate_limits(ip, endpoint, attempts, window_start)` — już w projekcie (auth)
- `/search` jako osobny endpoint w tabeli
- Limit: **10 żądań / 60 sekund per IP**
- Odpowiedź: HTTP 429 z nagłówkiem `Retry-After: 60`

---

## 3. OAuth2 Token Storage — FamilySearch

### Zagrożenie

Token OAuth2 umieszczony w localStorage lub w URL jest podatny na XSS i wyciek przez Referer.

### Zasada

Token przechowywany **wyłącznie w sesji PHP** (server-side), nigdy nie trafia do frontendu.

```php
// FamilySearchService::getToken()
private function getToken(): string
{
    // Cache w sesji PHP (server-side) — bezpieczny
    $session = $_SESSION['fs_token'] ?? null;
    if ($session && $session['expires'] > time()) {
        return $session['token'];
    }

    $token = $this->fetchNewToken();

    $_SESSION['fs_token'] = [
        'token'   => $token['access_token'],
        'expires' => time() + ($token['expires_in'] ?? 3600) - 60,
    ];

    return $_SESSION['fs_token']['token'];
}
```

### Wymagania

- Sesja PHP: `session.cookie_httponly = 1`, `session.cookie_secure = 1` (HTTPS)
- Token NIE jest zwracany w JSON API do frontendu
- Token NIE jest logowany (żaden `error_log($token)`)

---

## 4. SSRF — Server-Side Request Forgery

### Zagrożenie

Gdyby URL rejestru był kontrolowany przez użytkownika, atakujący mógłby kierować requesty do wewnętrznej infrastruktury (metadata endpoint AWS, lokalnego Redis, itp.).

### Mitygacja

URL rejestrów są **hardkodowane jako stałe klasy** — użytkownik podaje tylko parametry wyszukiwania (nazwisko, imię, rok), nigdy URL.

```php
// BEZPIECZNE — URL jako const
class FamilySearchService {
    private const BASE_URL = 'https://api.familysearch.org';
    // URL nigdy nie pochodzi z $_POST, $_GET ani bazy danych
}

// NIEBEZPIECZNE — nigdy tak:
$url = $_POST['registry_url'] . '?q=' . $params['last_name'];
```

### Weryfikacja

- [ ] Grep: `curl_init($_` → brak wyników w `src/Services/Registries/`
- [ ] Grep: `file_get_contents($_` → brak wyników w serwisach rejestrów

---

## 5. Cache — Bezpieczeństwo Danych

### Zagrożenie

Wyniki z `registry_cache` mogą zawierać dane osobowe innych osób. Cache musi być neutralny — zapytanie o te same parametry przez różnych użytkowników zwraca te same publiczne wyniki.

### Zasada

Cache jest per **`(registry, query_hash)`**, nie per użytkownik. Przechowuje tylko wyniki z rejestrów publicznych — dane historyczne, nigdy PII z wewnętrznej bazy.

```php
// query_hash nie zawiera user_id — to celowe
private function buildQueryHash(string $registry, array $params): string
{
    ksort($params);
    return hash('sha256', $registry . json_encode($params));
}
```

### TTL i czyszczenie

- TTL: **30 dni** — wyniki historyczne zmieniają się rzadko
- Czyszczenie: cron `DELETE FROM registry_cache WHERE cached_at < NOW() - INTERVAL 30 DAY` (Faza 2)
- Geneteka: lokalny import CSV → cache niepotrzebny (odpytuje lokalną tabelę)

---

## 6. Import CSV Geneteki

### Zagrożenie

Import pliku CSV przez HTTP upload (np. formularz admina) stwarza ryzyko:
- Uploadowanie złośliwego pliku
- DoS przez plik 500MB
- Path traversal przy zapisie pliku

### Mitygacja

Import **wyłącznie przez CLI** — dedykowany skrypt `bin/import-geneteka.php` uruchamiany przez administratora:

```bash
# Tylko admin, przez SSH, z lokalnego pliku
php bin/import-geneteka.php --file=/tmp/geneteka_dump.csv --truncate
```

### Wymagania

- Brak endpointu HTTP do importu CSV (żaden `POST /admin/import`)
- Skrypt CLI waliduje MIME pliku (musi być `text/csv` lub `text/plain`)
- Limit pamięci: streaming `fgetcsv` (nie `file_get_contents`) — obsługa plików >100MB
- Batch INSERT 500 rekordów — nie jeden INSERT na linię

---

## 7. Etykieta Scrapingu

### User-Agent

Każdy request do zewnętrznego serwisu musi zawierać identyfikowalny nagłówek:

```
User-Agent: Genealog.pl Research Tool/1.0 (kontakt@genealog.pl)
```

Dotyczy: FamilySearchService, ArchivesService, oraz przyszłych scraperów Python.

### Opóźnienia

- Geneteka: brak delay (lokalna baza MySQL)
- FamilySearch API: brak wymaganego delay (oficjalne API z limitem w nagłówkach)
- Szukaj w Archiwach: min. 1s między requestami (sprawdzić dokumentację)
- Scraper Python (Faza 2): `asyncio.sleep(1.0)` w `BaseScraper`

### robots.txt

- FamilySearch: oficjalne API — robots.txt nie dotyczy
- Szukaj w Archiwach: oficjalne API — robots.txt nie dotyczy
- Grobonet/PRADZIAD (Faza 2): Python `robotparser` przed każdym scraperze

---

## 8. SQL Injection

Wszystkie zapytania w `SearchRepository` i `GenetykaService` używają **PDO prepared statements**.

```php
// BEZPIECZNE — zawsze tak
$stmt = $this->db->prepare('SELECT * FROM geneteka_records WHERE surname LIKE ?');
$stmt->execute([$params['last_name'] . '%']);

// NIEBEZPIECZNE — nigdy tak
$sql = "SELECT * FROM geneteka_records WHERE surname LIKE '{$params['last_name']}%'";
```

### Weryfikacja

- [ ] Grep: `"SELECT.*\$` w `src/Repositories/` i `src/Services/Registries/` → brak wyników (interpolacja SQL)
- [ ] PHPUnit: test z `'; DROP TABLE` jako last_name → brak błędów SQL

---

## 9. Walidacja Parametrów Wyszukiwania

```php
// SearchController::processSearch() — walidacja przed dispatch
$registry  = in_array($request->post('registry'), ['geneteka', 'familysearch', 'archives'], true)
    ? $request->post('registry')
    : throw new \InvalidArgumentException('Invalid registry');

$lastName  = substr(trim($request->post('last_name') ?? ''), 0, 100);   // max 100 znaków
$firstName = substr(trim($request->post('first_name') ?? ''), 0, 100);
$birthYear = filter_var($request->post('birth_year'), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1000, 'max_range' => 2025]
]) ?: null;

if (empty($lastName)) {
    // Pole wymagane — minimum jedno kryterium
    Response::redirect('/search?error=lastname_required');
    return;
}
```

---

## 10. Logowanie i Monitoring

### Co logować

```php
// Każde zapytanie do zewnętrznego API
error_log(sprintf(
    '[Registry] registry=%s user=%d query_hash=%s duration=%.2fs',
    $registry, $userId, $queryHash, $duration
));
```

### Czego NIE logować

- Pełne parametry wyszukiwania z danymi osobowymi w produkcyjnym logu
- Tokeny OAuth2 ani klucze API
- Odpowiedzi API w całości (mogą zawierać PII)

### Alerty (Faza 2)

- Status `failed` > 10 razy w ciągu 5 minut → alert (problem z API zewnętrznym)
- Rate limit 429 > 5 razy z jednego IP → ban czasowy 1h
