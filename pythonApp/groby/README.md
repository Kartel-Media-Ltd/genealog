# Groby — Cemetery Registry Scraper

Mikroserwis FastAPI do przeszukiwania polskich rejestrów cmentarnych.
Część projektu [Genealog](https://genealog.pl).

## Obsługiwane rejestry

| Rejestr | Status | Zasięg |
|---------|--------|--------|
| **Grobonet** | ✅ Gotowy | 600+ cmentarzy komunalnych/parafialnych |
| **eCmentarze** | ⚠️ Wymaga weryfikacji DOM | 2,36 mln rekordów |

## Szybki start

### Docker (zalecane)

```bash
cd pythonApp/groby
cp .env.example .env
# Edytuj .env — ustaw API_SECRET

docker compose up -d
```

Serwis dostępny pod `http://localhost:8010`.
Dokumentacja Swagger: `http://localhost:8010/docs`

### Lokalnie (bez Docker)

```bash
cd pythonApp/groby
python -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate
pip install -r requirements.txt

cp .env.example .env
# Edytuj .env

uvicorn main:app --reload --port 8010
```

## Endpointy

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/health` | Health check |
| GET | `/registries` | Lista włączonych rejestrów |
| POST | `/search/grobonet` | Wyszukaj w Grobonet |
| POST | `/search/ecmentarze` | Wyszukaj w eCmentarze |
| POST | `/search/all` | Wyszukaj we wszystkich równolegle |

### Przykład zapytania

```bash
curl -X POST http://localhost:8010/search/grobonet \
  -H "Content-Type: application/json" \
  -H "X-API-Secret: twoj_sekretny_klucz" \
  -d '{
    "last_name": "Kowalski",
    "first_name": "Jan",
    "birth_year": 1920
  }'
```

### Przykład odpowiedzi

```json
{
  "registry": "grobonet",
  "count": 3,
  "results": [
    {
      "source": "Grobonet",
      "first_name": "Jan",
      "last_name": "Kowalski",
      "birth_year": 1920,
      "death_year": 2001,
      "cemetery": "Cmentarz Komunalny Warszawa-Północ",
      "grave_location": "Kwatera A, Rząd 3, Miejsce 15",
      "url": "https://grobonet.com/?id=12345"
    }
  ]
}
```

## Konfiguracja (.env)

| Zmienna | Domyślnie | Opis |
|---------|-----------|------|
| `GROBONET_ENABLED` | `true` | Włącz scraper Grobonet |
| `ECMENTARZE_ENABLED` | `false` | Włącz po weryfikacji robots.txt |
| `API_SECRET` | `""` | Klucz API (nagłówek X-API-Secret) — zostaw puste dla lokalnego dev |
| `PORT` | `8010` | Port serwisu |
| `LOG_LEVEL` | `INFO` | Poziom logów: DEBUG/INFO/WARNING/ERROR |
| `SITE_URL` | `http://localhost:8002` | URL PHP — dla CORS |

## Integracja z PHP (Genealog)

PHP wywołuje serwis przez HTTP w `src/Services/Registries/GrobonetService.php`:

```php
$response = $httpClient->post('http://localhost:8010/search/grobonet', [
    'headers' => ['X-API-Secret' => GROBONET_API_SECRET],
    'json'    => ['last_name' => $lastName, 'first_name' => $firstName],
]);
$data = json_decode($response->getBody(), true);
```

## ⚠️ Ważne przed uruchomieniem produkcyjnym

### 1. Inspekcja DOM Grobonet

Selektory CSS w `scrapers/grobonet.py` są **szacunkowe** i wymagają weryfikacji:

1. Otwórz [grobonet.com](https://grobonet.com) w przeglądarce
2. Wyszukaj dowolną osobę (np. "Kowalski")
3. DevTools → Elements → znajdź tabelę wyników
4. Zaktualizuj selektory w `_parse_results()` i `_parse_card()`
5. Sprawdź parametry w DevTools → Network → znajdź GET/POST do backendu

### 2. robots.txt

Serwis automatycznie sprawdza `robots.txt` przed pierwszym zapytaniem.
Jeśli zablokowany → zwróci HTTP 451.

### 3. Kontakt z Grobonet

Przed uruchomieniem scrapera — wyślij email do `grobonet@polskie-cmentarze.com`
z pytaniem o możliwość integracji. Szczegóły w `dev/active/cemetery-registries/`.

### 4. Rate limiting

Wbudowany rate limit: **2 sekundy** między zapytaniami (podwójny margines
bezpieczeństwa). Nie zmieniaj na niższy bez zgody właściciela serwisu.
