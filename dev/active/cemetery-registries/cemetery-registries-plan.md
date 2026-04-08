# Rejestry Cmentarne — Plan Architektoniczny

## Cel

Umożliwienie przeszukiwania rejestrów cmentarnych (Grobonet, eCmentarze) bezpośrednio z aplikacji Genealog — w panelu osoby oraz w globalnym wyszukiwaniu Discovery.

---

## Priorytet rejestrów

| Rejestr | Faza | Metoda | Priorytet |
|---------|------|--------|-----------|
| **Grobonet** | 1 | Python scraper | 🔴 MVP |
| **BillyonGraves** | 0 | Via FamilySearch API (już planowane) | — |
| **eCmentarze** | 2 | Python scraper | 🟡 |
| **FindAGrave** | zawsze | URL field tylko (ToS zakazuje scraping) | 🔵 |
| **Mogily.pl** | 3 | Python scraper | 🟢 opcjonalnie |

---

## Architektura — przepływ danych

```
Użytkownik wpisuje dane osoby w panelu Discovery
         ↓
PHP DiscoveryController::search()
  → GrobonetMatchSource::search() (implementuje MatchSourceInterface)
  → INSERT search_jobs (status='pending', registry='grobonet')
  → natychmiastowa odpowiedź z job_id
         ↓
Python scraper (polling search_jobs co 5s)
  → BRPOP lub SELECT WHERE status='pending'
  → httpx GET grobonet.com/szukaj?imie=X&nazwisko=Y
  → BeautifulSoup parse HTML tabeli wyników
  → UPDATE search_jobs SET status='done', results=JSON
         ↓
Frontend Alpine.js polling /api/search-jobs/{id} co 2s
  → wyświetla wyniki gdy status='done'
```

---

## Nowe pliki

### PHP — backend

#### `src/Services/Discovery/Sources/GrobonetMatchSource.php`

Implementuje `MatchSourceInterface`. Wbudowana w panel Discovery — pojawia się jako sekcja "Rejestry cmentarne" obok local/crossTree/external.

```php
final class GrobonetMatchSource implements MatchSourceInterface
{
    public const NAME = 'grobonet';

    public function getName(): string { return self::NAME; }

    public function isAvailable(): bool
    {
        // dostępny gdy scraper Python jest skonfigurowany (GROBONET_ENABLED=1)
        return (bool)(defined('GROBONET_ENABLED') && GROBONET_ENABLED);
    }

    public function getTimeoutSeconds(): int { return 15; }

    public function search(SearchCriteria $criteria, SearchContext $context): array
    {
        // INSERT do search_jobs → zwróć pusty wynik (async)
        // lub jeśli cache istnieje → zwróć z registry_cache (synchronicznie)
        // Format zwracany: array<MatchResult> z sourceType='external'
    }
}
```

#### `src/Services/Registries/GrobonetService.php`

Bezpośredni klient dla strony wyszukiwania `/search` (synchroniczne przez search_jobs).

```php
final class GrobonetService implements RegistryInterface
{
    public function search(array $params): array
    {
        // 1. Sprawdź registry_cache (TTL 30 dni)
        // 2. Jeśli brak cache → INSERT search_jobs (async) lub rzuć wyjątek
        // 3. Zwróć ['job_id' => X, 'cached' => false] lub wyniki z cache
    }
}
```

### Python — scraper

#### `scraper/scrapers/grobonet.py`

```python
from .base import BaseScraper
from bs4 import BeautifulSoup
import httpx

class GrobonetScraper(BaseScraper):
    BASE_URL = 'https://grobonet.com/index.php'
    RATE_LIMIT = 2.0  # 2 sekundy między requestami (ostrożniej niż 1s)

    async def _do_search(self, last_name, first_name, birth_year, region):
        params = {
            'op': 'se',
            'cmentarz': '',   # wszystkie cmentarze
            'imie': first_name or '',
            'nazwisko': last_name,
        }
        async with httpx.AsyncClient(headers=self.HEADERS) as client:
            r = await client.get(self.BASE_URL, params=params, timeout=10)
        soup = BeautifulSoup(r.text, 'html.parser')
        return self._parse_results(soup)

    def _parse_results(self, soup):
        results = []
        # Parsowanie tabeli wyników — wymaga inspekcji DOM Grobonet
        # Każdy wiersz: imię, nazwisko, data_ur, data_sm, cmentarz, lokalizacja_grobu
        for row in soup.select('table.results tr[data-id]'):
            cols = row.select('td')
            if len(cols) < 5:
                continue
            results.append({
                'source':       'Grobonet',
                'first_name':   cols[0].get_text(strip=True),
                'last_name':    cols[1].get_text(strip=True),
                'birth_year':   self._extract_year(cols[2].get_text()),
                'death_year':   self._extract_year(cols[3].get_text()),
                'cemetery':     cols[4].get_text(strip=True),
                'grave_location': cols[5].get_text(strip=True) if len(cols) > 5 else None,
                'url':          f"https://grobonet.com/?id={row.get('data-id', '')}",
            })
        return results
```

**UWAGA:** Selektory CSS wymagają inspekcji aktualnej strony Grobonet przed implementacją — struktura HTML może się różnić od powyższego schematu.

#### `scraper/scrapers/ecmentarze.py`

Analogiczna klasa po weryfikacji robots.txt i struktury HTML.

### SQL

#### `migrations/016_cemetery_registries.sql`

```sql
-- Cache wyników z rejestrów cmentarnych (TTL: 30 dni)
-- Używa istniejącej tabeli registry_cache (już w schema) — brak nowych tabel.
-- Tylko: pole `findagrave_url` w persons (opcjonalne linkowanie FindAGrave).

ALTER TABLE persons
  ADD COLUMN findagrave_url VARCHAR(500) NULL DEFAULT NULL
  COMMENT 'Opcjonalne ręczne linkowanie do FindAGrave (scraping zakazany przez ToS)'
  AFTER photo_path;
```

> **Uwaga:** `search_jobs` i `registry_cache` są już w istniejącym schemacie. Nowa migracja dodaje tylko `findagrave_url` do `persons`.

### Config

#### `config/config.php` — dodać:

```php
// Rejestry cmentarne (opcjonalne — wymagają Python scrapera)
define('GROBONET_ENABLED', (bool)($_ENV['GROBONET_ENABLED'] ?? false));
define('ECMENTARZE_ENABLED', (bool)($_ENV['ECMENTARZE_ENABLED'] ?? false));
```

#### `.env.local` — dodać gdy scraper jest gotowy:

```dotenv
GROBONET_ENABLED=false   # włącz gdy scraper/scrapers/grobonet.py jest gotowy
ECMENTARZE_ENABLED=false
```

---

## Integracja z Discovery (MatchSourceInterface)

`GrobonetMatchSource` rejestruje się w `MatchSourceRegistry` w `public/index.php`:

```php
$matchRegistry->register(new GrobonetMatchSource($db, $searchRepo));
```

Wyniki Grobonet pojawiają się w panelu "Możliwe powiązania" jako nowa sekcja `cemetery` (obok `local`, `crossTree`, `external`). Frontend renderuje je inaczej — z polem "cmentarz" i "lokalizacja grobu" zamiast "region".

---

## Integracja z `/search` (RegistryInterface)

Na stronie `/search` Grobonet pojawia się jako dodatkowa opcja:

```
[x] Geneteka — metryki chrzcin, ślubów, zgonów
[x] FamilySearch — globalne archiwum genealogiczne
[x] Grobonet — cmentarze komunalne i parafialne  ← NOWE
[ ] eCmentarze — ogólnopolska baza pochowanych   ← Faza 2
```

---

## FindAGrave — tylko pole URL w profilu osoby

Zamiast scraping (zakazanego przez ToS), formularz edycji osoby (`persons/edit.php`) dostaje nowe pole:

```html
<label>Link FindAGrave (opcjonalnie)</label>
<input type="url" name="findagrave_url"
       placeholder="https://www.findagrave.com/memorial/123456/..."
       value="<?= htmlspecialchars($person->findagraveUrl ?? '') ?>">
```

W widoku osoby (`persons/show.php`) — wyświetlany jako link zewnętrzny gdy `findagrave_url` jest ustawiony.

---

## Fazy implementacji

### Faza 0: Kontakt i przygotowanie (USER)
- Email do Grobonet z pytaniem o warunki integracji
- Inspekcja DOM Grobonet (DevTools → Network) dla ustalenia endpointów i struktury HTML
- Weryfikacja `grobonet.com/robots.txt` i `ecmentarze.pl/robots.txt`

### Faza 1: Grobonet scraper
- `scraper/scrapers/grobonet.py` — Python scraper z BaseScraper
- `src/Services/Discovery/Sources/GrobonetMatchSource.php`
- `src/Services/Registries/GrobonetService.php`
- Rejestracja w `MatchSourceRegistry` i `/search`
- `migrations/016_cemetery_registries.sql` (findagrave_url)
- `config/config.php` — GROBONET_ENABLED

### Faza 2: eCmentarze scraper
- `scraper/scrapers/ecmentarze.py` (po weryfikacji robots.txt)
- `src/Services/Registries/eCmentarzeService.php`
- Rejestracja w `/search`

### Faza 3: FindAGrave URL field
- Migracja: `findagrave_url` w `persons`
- Formularz edycji osoby
- Widok osoby — link zewnętrzny

### Faza 4: UX — widok wyników cmentarnych
- Komponent `molecules/cemetery-result.php`
- Wyświetlanie w panelu Discovery (sekcja "Cmentarze")
- Wyświetlanie na stronie `/search`

---

## Ryzyka

| Ryzyko | Prawdopodobieństwo | Mitygacja |
|--------|-------------------|-----------|
| Grobonet zmienia strukturę HTML | Wysokie | Testy E2E + alert gdy 0 wyników |
| Grobonet blokuje IP | Średnie | Rate limit 2s, User-Agent z kontaktem, rotacja przez proxy |
| eCmentarze robots.txt blokuje | Średnie | Sprawdzić PRZED implementacją |
| FindAGrave integracja niemożliwa | Pewne | Tylko URL field (już uwzględnione) |
| Python scraper nieaktywny | Niskie | `isAvailable()` = false → sekcja ukryta w UI |
