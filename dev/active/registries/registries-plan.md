# Rejestry Zewnętrzne — Plan Architektoniczny

## Przegląd

Feature umożliwia użytkownikom przeszukiwanie zewnętrznych rejestrów genealogicznych (Geneteka, FamilySearch, Szukaj w Archiwach) bezpośrednio z aplikacji Genealog. Wyniki są prezentowane asynchronicznie przez Alpine.js polling.

---

## Nowe endpointy

| Metoda | Ścieżka | Akcja | Opis |
|--------|---------|-------|------|
| GET | `/search` | `SearchController::showSearch()` | Strona formularza wyszukiwania |
| POST | `/search` | `SearchController::processSearch()` | Tworzy search_job, zwraca redirect lub wyniki |
| GET | `/api/search-jobs/{id}` | `SearchController::jobStatus()` | Status zadania — polling Alpine.js co 2s |

### Routing (src/Core/Router.php)

```php
$router->get('/search', [SearchController::class, 'showSearch']);
$router->post('/search', [SearchController::class, 'processSearch']);
$router->get('/api/search-jobs/{id}', [SearchController::class, 'jobStatus']);
```

---

## Nowe pliki

### src/Controllers/SearchController.php

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SearchService;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class SearchController
{
    public function __construct(
        private SearchService $searchService,
        private Session $session
    ) {}

    /**
     * GET /search — strona wyszukiwania
     */
    public function showSearch(Request $request): void
    {
        $jobId = (int) ($request->query('job') ?? 0);
        $job = $jobId ? $this->searchService->getJobStatus($jobId) : null;

        include __DIR__ . '/../Views/pages/search/index.php';
    }

    /**
     * POST /search — dispatch zapytania
     */
    public function processSearch(Request $request): void
    {
        $request->verifyCsrf();

        $userId = $this->session->get('user_id');
        $registry = $request->post('registry');  // 'geneteka' | 'familysearch' | 'archives'
        $params = [
            'last_name'  => trim($request->post('last_name') ?? ''),
            'first_name' => trim($request->post('first_name') ?? ''),
            'birth_year' => (int) ($request->post('birth_year') ?? 0) ?: null,
            'region'     => trim($request->post('region') ?? '') ?: null,
        ];

        $result = $this->searchService->dispatch($userId, $registry, $params);

        // Synchroniczne API (Geneteka, Archives, FamilySearch dla MVP): wyniki od razu
        if ($result['status'] === 'done') {
            Response::redirect('/search?job=' . $result['job_id']);
            return;
        }

        // Async (przyszłość): przekieruj do pollingu
        Response::redirect('/search?job=' . $result['job_id']);
    }

    /**
     * GET /api/search-jobs/{id} — polling JSON
     */
    public function jobStatus(Request $request, int $id): void
    {
        $userId = $this->session->get('user_id');
        $job = $this->searchService->getJobStatus($id, $userId);

        if (!$job) {
            Response::json(['error' => 'Job not found'], 404);
            return;
        }

        Response::json([
            'job_id'  => $job['id'],
            'status'  => $job['status'],
            'results' => $job['status'] === 'done' ? json_decode($job['results'], true) : null,
            'error'   => $job['error'],
        ]);
    }
}
```

---

### src/Services/SearchService.php

Główna usługa orkiestrująca wyszukiwanie.

```php
<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SearchRepository;
use App\Services\Registries\GenetykaService;
use App\Services\Registries\FamilySearchService;
use App\Services\Registries\ArchivesService;

class SearchService
{
    private array $registries;

    public function __construct(
        private SearchRepository $repo,
        GenetykaService $geneteka,
        FamilySearchService $familySearch,
        ArchivesService $archives,
    ) {
        $this->registries = [
            'geneteka'     => $geneteka,
            'familysearch' => $familySearch,
            'archives'     => $archives,
        ];
    }

    /**
     * Dispatch zapytania do rejestru.
     * Dla MVP — wszystkie rejestry synchroniczne (wyniki w tej samej sesji).
     */
    public function dispatch(int $userId, string $registry, array $params): array
    {
        if (!isset($this->registries[$registry])) {
            throw new \InvalidArgumentException("Unknown registry: {$registry}");
        }

        // Sprawdź cache
        $queryHash = $this->buildQueryHash($registry, $params);
        $cached = $this->repo->findCache($registry, $queryHash);
        if ($cached) {
            $jobId = $this->repo->createJob($userId, $registry, $params, 'done', $cached['results']);
            return ['job_id' => $jobId, 'status' => 'done'];
        }

        // Utwórz job
        $jobId = $this->repo->createJob($userId, $registry, $params);

        try {
            $this->repo->updateJobStatus($jobId, 'running');
            $results = $this->registries[$registry]->search($params);
            $resultsJson = json_encode($results);

            $this->repo->updateJobDone($jobId, $resultsJson);
            $this->repo->saveCache($registry, $queryHash, $resultsJson);
        } catch (\Throwable $e) {
            $this->repo->updateJobFailed($jobId, $e->getMessage());
        }

        return ['job_id' => $jobId, 'status' => 'done'];
    }

    public function getJobStatus(int $jobId, ?int $userId = null): ?array
    {
        return $this->repo->findJob($jobId, $userId);
    }

    private function buildQueryHash(string $registry, array $params): string
    {
        ksort($params);
        return hash('sha256', $registry . json_encode($params));
    }
}
```

---

### src/Repositories/SearchRepository.php

```php
<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

class SearchRepository
{
    public function __construct(private DB $db) {}

    public function createJob(
        int $userId,
        string $registry,
        array $params,
        string $status = 'pending',
        ?string $results = null
    ): int {
        $this->db->execute(
            'INSERT INTO search_jobs (user_id, registry, params, status, results, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$userId, $registry, json_encode($params), $status, $results]
        );
        return (int) $this->db->lastInsertId();
    }

    public function findJob(int $id, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM search_jobs WHERE id = ?';
        $binds = [$id];

        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $binds[] = $userId;
        }

        return $this->db->fetchOne($sql, $binds);
    }

    public function updateJobStatus(int $id, string $status): void
    {
        $this->db->execute(
            'UPDATE search_jobs SET status = ? WHERE id = ?',
            [$status, $id]
        );
    }

    public function updateJobDone(int $id, string $resultsJson): void
    {
        $this->db->execute(
            'UPDATE search_jobs SET status = "done", results = ?, finished_at = NOW() WHERE id = ?',
            [$resultsJson, $id]
        );
    }

    public function updateJobFailed(int $id, string $error): void
    {
        $this->db->execute(
            'UPDATE search_jobs SET status = "failed", error = ?, finished_at = NOW() WHERE id = ?',
            [$error, $id]
        );
    }

    public function findCache(string $registry, string $queryHash): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM registry_cache
             WHERE registry = ? AND query_hash = ?
               AND cached_at > NOW() - INTERVAL 30 DAY',
            [$registry, $queryHash]
        );
    }

    public function saveCache(string $registry, string $queryHash, string $resultsJson): void
    {
        $this->db->execute(
            'INSERT INTO registry_cache (registry, query_hash, results, cached_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE results = VALUES(results), cached_at = NOW()',
            [$registry, $queryHash, $resultsJson]
        );
    }
}
```

---

### src/Services/Registries/GenetykaService.php

Wyszukiwanie w lokalnej tabeli `geneteka_records` — import z CSV dump PTG.

```php
<?php
declare(strict_types=1);

namespace App\Services\Registries;

use App\Core\DB;

class GenetykaService implements RegistryInterface
{
    public function __construct(private DB $db) {}

    public function search(array $params): array
    {
        $conditions = [];
        $binds = [];

        if (!empty($params['last_name'])) {
            $conditions[] = 'surname LIKE ?';
            $binds[] = $params['last_name'] . '%';
        }
        if (!empty($params['first_name'])) {
            $conditions[] = 'given_name LIKE ?';
            $binds[] = $params['first_name'] . '%';
        }
        if (!empty($params['birth_year'])) {
            $year = (int) $params['birth_year'];
            $conditions[] = 'birth_year BETWEEN ? AND ?';
            $binds[] = $year - 5;
            $binds[] = $year + 5;
        }
        if (!empty($params['region'])) {
            $conditions[] = 'region LIKE ?';
            $binds[] = '%' . $params['region'] . '%';
        }

        if (empty($conditions)) {
            return [];
        }

        $where = implode(' AND ', $conditions);
        $rows = $this->db->fetchAll(
            "SELECT id, surname, given_name, birth_year, birth_place, parish,
                    region, document_type, year
             FROM geneteka_records
             WHERE {$where}
             LIMIT 100",
            $binds
        );

        return array_map(fn($r) => $this->format($r), $rows);
    }

    private function format(array $row): array
    {
        return [
            'source'        => 'Geneteka',
            'surname'       => $row['surname'],
            'given_name'    => $row['given_name'],
            'birth_year'    => $row['birth_year'],
            'birth_place'   => $row['birth_place'],
            'parish'        => $row['parish'],
            'region'        => $row['region'],
            'document_type' => $row['document_type'],
            'year'          => $row['year'],
        ];
    }
}
```

---

### src/Services/Registries/FamilySearchService.php

REST API + OAuth2 — oficjalne API FamilySearch.

```php
<?php
declare(strict_types=1);

namespace App\Services\Registries;

class FamilySearchService implements RegistryInterface
{
    private const BASE_URL    = 'https://api.familysearch.org';
    private const TOKEN_URL   = 'https://ident.familysearch.org/cis-web/oauth2/v3/token';
    private const USER_AGENT  = 'Genealog.pl Research Tool/1.0 (kontakt@genealog.pl)';

    private ?string $cachedToken = null;
    private int $tokenExpires = 0;

    public function __construct(
        private string $clientId,     // z .env.local: FAMILYSEARCH_CLIENT_ID
        private string $clientSecret  // z .env.local: FAMILYSEARCH_CLIENT_SECRET
    ) {}

    public function search(array $params): array
    {
        $token = $this->getToken();

        $query = implode(' ', array_filter([
            $params['last_name'] ?? '',
            $params['first_name'] ?? '',
        ]));

        $url = self::BASE_URL . '/platform/tree/persons?' . http_build_query([
            'q'     => $query,
            'count' => 50,
        ]);

        $response = $this->curlGet($url, $token);
        return $this->parseResponse($response);
    }

    private function getToken(): string
    {
        // Cache tokenu w pamięci (dla jednego requestu wystarczy)
        if ($this->cachedToken && time() < $this->tokenExpires) {
            return $this->cachedToken;
        }

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
            CURLOPT_HTTPHEADER     => ['User-Agent: ' . self::USER_AGENT],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($body, true);
        $this->cachedToken = $data['access_token'];
        $this->tokenExpires = time() + ($data['expires_in'] ?? 3600) - 60;

        return $this->cachedToken;
    }

    private function curlGet(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'User-Agent: ' . self::USER_AGENT,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body, true) ?? [];
    }

    private function parseResponse(array $data): array
    {
        $persons = $data['persons'] ?? [];
        return array_map(fn($p) => [
            'source'      => 'FamilySearch',
            'fs_id'       => $p['id'] ?? null,
            'given_name'  => $p['names'][0]['nameForms'][0]['parts'][0]['value'] ?? '',
            'surname'     => $p['names'][0]['nameForms'][0]['parts'][1]['value'] ?? '',
            'birth_year'  => $p['vitals']['birthDate']['normalized'][0]['value'] ?? null,
            'birth_place' => $p['vitals']['birthPlace']['original'] ?? null,
            'url'         => 'https://www.familysearch.org/tree/person/' . ($p['id'] ?? ''),
        ], $persons);
    }
}
```

---

### src/Services/Registries/ArchivesService.php

REST API Archiwów Państwowych (szukajwarchiwach.gov.pl).

```php
<?php
declare(strict_types=1);

namespace App\Services\Registries;

class ArchivesService implements RegistryInterface
{
    private const BASE_URL   = 'https://www.szukajwarchiwach.gov.pl/api';
    private const USER_AGENT = 'Genealog.pl Research Tool/1.0 (kontakt@genealog.pl)';

    public function __construct(
        private string $apiKey  // z .env.local: ARCHIVES_API_KEY
    ) {}

    public function search(array $params): array
    {
        $query = implode(' ', array_filter([
            $params['last_name'] ?? '',
            $params['first_name'] ?? '',
        ]));

        $url = self::BASE_URL . '/szukaj?' . http_build_query([
            'q'      => $query,
            'format' => 'json',
            'limit'  => 50,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'X-API-Key: ' . $this->apiKey,
                'User-Agent: ' . self::USER_AGENT,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($body, true) ?? [];
        return $this->parseResponse($data);
    }

    private function parseResponse(array $data): array
    {
        $items = $data['results'] ?? $data['items'] ?? [];
        return array_map(fn($item) => [
            'source'       => 'Szukaj w Archiwach',
            'title'        => $item['title'] ?? '',
            'archive'      => $item['archive'] ?? '',
            'date_range'   => $item['dateRange'] ?? '',
            'description'  => $item['description'] ?? '',
            'url'          => $item['url'] ?? '',
            'reference'    => $item['reference'] ?? '',
        ], $items);
    }
}
```

---

### src/Services/Registries/RegistryInterface.php

```php
<?php
declare(strict_types=1);

namespace App\Services\Registries;

interface RegistryInterface
{
    /**
     * @param array{last_name: string, first_name: string, birth_year: int|null, region: string|null} $params
     * @return list<array<string, mixed>>
     */
    public function search(array $params): array;
}
```

---

### src/Views/pages/search/index.php

Strona wyszukiwania — formularz + wyniki live przez Alpine.js polling.

```php
<?php
// Variables from controller: $job (array|null), $csrf
?>
<?php include __DIR__ . '/../../../templates/AppLayout.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-semibold text-foreground mb-6">Szukaj w rejestrach</h1>

    <!-- Formularz wyszukiwania -->
    <form method="POST" action="/search" class="mb-8 space-y-4 bg-card border border-border rounded-lg p-6">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <?php include __DIR__ . '/../../../atoms/input.php'; // nazwisko ?>
            <?php include __DIR__ . '/../../../atoms/input.php'; // imię ?>
            <?php include __DIR__ . '/../../../atoms/input.php'; // rok urodzenia ?>
            <?php include __DIR__ . '/../../../atoms/input.php'; // region ?>
        </div>

        <!-- Wybór rejestru -->
        <div class="flex flex-wrap gap-4">
            <?php foreach (['geneteka' => 'Geneteka', 'archives' => 'Szukaj w Archiwach', 'familysearch' => 'FamilySearch'] as $val => $label): ?>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="registry" value="<?= $val ?>"
                           class="accent-primary" <?= $val === 'geneteka' ? 'checked' : '' ?>>
                    <span class="text-sm text-foreground"><?= $label ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php include __DIR__ . '/../../../atoms/button.php'; // ['label' => 'Szukaj'] ?>
    </form>

    <!-- Wyniki (Alpine.js polling) -->
    <?php if ($job): ?>
    <div x-data="searchResults(<?= $job['id'] ?>, '<?= $job['status'] ?>')"
         x-init="init()">

        <!-- Status ładowania -->
        <div x-show="status === 'pending' || status === 'running'"
             class="flex items-center gap-2 text-muted-foreground">
            <?php include __DIR__ . '/../../../atoms/spinner.php'; ?>
            <span>Szukam w rejestrze...</span>
        </div>

        <!-- Błąd -->
        <div x-show="status === 'failed'" class="text-destructive text-sm">
            Wystąpił błąd podczas wyszukiwania. Spróbuj ponownie.
        </div>

        <!-- Wyniki -->
        <div x-show="status === 'done'">
            <p class="text-sm text-muted-foreground mb-4">
                Znaleziono <span x-text="results.length"></span> wyników.
            </p>
            <div class="space-y-3">
                <template x-for="r in results" :key="r.source + r.surname">
                    <?php include __DIR__ . '/../../../molecules/registry-result.php'; ?>
                </template>
            </div>
            <div x-show="results.length === 0" class="text-muted-foreground text-sm">
                Brak wyników dla podanych kryteriów.
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function searchResults(jobId, initialStatus) {
    return {
        status: initialStatus,
        results: <?= $job && $job['status'] === 'done' ? ($job['results'] ?? '[]') : '[]' ?>,
        pollInterval: null,

        init() {
            if (this.status === 'pending' || this.status === 'running') {
                this.startPolling();
            }
        },

        startPolling() {
            this.pollInterval = setInterval(() => this.checkStatus(), 2000);
        },

        async checkStatus() {
            const res = await fetch(`/api/search-jobs/${jobId}`);
            const data = await res.json();
            this.status = data.status;

            if (data.status === 'done') {
                this.results = data.results ?? [];
                clearInterval(this.pollInterval);
            } else if (data.status === 'failed') {
                clearInterval(this.pollInterval);
            }
        }
    };
}
</script>
```

---

### migrations/005_registries.sql

```sql
-- Migration 005: Rejestry zewnętrzne — tabela geneteka_records
-- Uruchomić: source .env.local && docker exec -i mariadb_docker mariadb \
--   -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/005_registries.sql

CREATE TABLE IF NOT EXISTS geneteka_records (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  surname        VARCHAR(100)  NOT NULL,
  given_name     VARCHAR(100)  DEFAULT NULL,
  birth_year     SMALLINT      DEFAULT NULL,
  birth_place    VARCHAR(200)  DEFAULT NULL,
  parish         VARCHAR(200)  DEFAULT NULL,
  region         VARCHAR(100)  DEFAULT NULL,
  document_type  VARCHAR(50)   DEFAULT NULL COMMENT 'metryka chrztu / slubu / zgonu',
  year           SMALLINT      DEFAULT NULL COMMENT 'rok dokumentu',
  raw_data       JSON          DEFAULT NULL,
  imported_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_surname    (surname),
  INDEX idx_birth_year (birth_year),
  INDEX idx_region     (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Przepływ wyszukiwania — szczegóły

```
[Użytkownik] → wypełnia formularz (nazwisko, imię, rok ur., region, rejestr)
     ↓
POST /search
     ↓
SearchController::processSearch()
     ↓
SearchService::dispatch(userId, registry, params)
     ├── sprawdź registry_cache (TTL 30 dni)
     │   └── jeśli HIT → createJob(status=done) → redirect /search?job={id}
     └── jeśli MISS
         ├── createJob(status=pending)
         ├── updateJobStatus(running)
         ├── RegistryService::search(params)  ← synchroniczne (MVP)
         ├── updateJobDone(results)
         ├── saveCache(registry, hash, results)
         └── redirect /search?job={id}
     ↓
GET /search?job={id}
     ↓
SearchController::showSearch() → renderuje stronę z job
     ↓
Alpine.js x-init: status='done' → wyświetl wyniki (bez pollingu)
     lub status='pending' → startPolling() co 2s → GET /api/search-jobs/{id}
```

### Dlaczego synchroniczne dla MVP?

FamilySearch API i Szukaj w Archiwach to proste HTTP GET z curl — czas odpowiedzi 1-5s. Wdrożenie kolejki (Redis, cron) byłoby nadmierną złożonością na tym etapie. Polling Alpine.js jest gotowy na przyszłą migrację do async bez zmiany frontendu.

---

## FamilySearch OAuth2 — szczegóły rejestracji

1. Rejestracja aplikacji: https://www.familysearch.org/developers/
2. Typ: "Server-side Web App" lub "Client Credentials" (dla serwera)
3. Sandbox URL: `https://api-integ.familysearch.org` (testy)
4. Produkcja: `https://api.familysearch.org`
5. Zmienne w `.env.local`:
   - `FAMILYSEARCH_CLIENT_ID=...`
   - `FAMILYSEARCH_CLIENT_SECRET=...`
   - `FAMILYSEARCH_ENV=sandbox` (zmień na `production` po zatwierdzeniu)

---

## Szukaj w Archiwach — szczegóły

- Portal API: https://www.szukajwarchiwach.gov.pl/api
- Klucz bezpłatny — rejestracja na portalu
- Zmienne w `.env.local`:
   - `ARCHIVES_API_KEY=...`
- Rate limit po stronie API: sprawdzić dokumentację (zwykle 1000 req/dzień dla darmowego klucza)

---

## Struktura plików — podsumowanie

```
src/
├── Controllers/
│   └── SearchController.php         ← NOWY
├── Services/
│   ├── SearchService.php            ← NOWY
│   └── Registries/
│       ├── RegistryInterface.php    ← NOWY
│       ├── GenetykaService.php      ← NOWY
│       ├── FamilySearchService.php  ← NOWY
│       └── ArchivesService.php      ← NOWY
├── Repositories/
│   └── SearchRepository.php        ← NOWY
└── Views/
    ├── pages/search/
    │   └── index.php                ← NOWY
    └── molecules/
        └── registry-result.php     ← NOWY (karta wyniku)

migrations/
└── 005_registries.sql               ← NOWY
```
