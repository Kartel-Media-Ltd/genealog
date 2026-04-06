# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projekt

**Genealog** — system do budowania drzew genealogicznych rodziny. Każdy użytkownik tworzy własne drzewo, może zapraszać inne osoby do współpracy. Globalny widok wszystkich dodanych osób umożliwia łączenie rodzin (z zachowaniem prywatności). Eksport do druku jako kluczowa funkcja wyjściowa.

## Stack technologiczny

- **Backend**: PHP 8.2+ (PDO, natywne sesje, własny router — bez frameworka)
- **Baza danych**: MySQL 8.0+ / MariaDB
- **Frontend**: HTML5 + Tailwind CSS v4 (CDN) + Alpine.js + Vanilla JS
- **Design system**: shadcn/ui — tokeny kolorów, typografia, komponenty (portowane do PHP/HTML)
- **Wizualizacja drzewa**: **D3.js v7** — `d3.tree()` + SVG (rekomendowane, darmowe, pełna kontrola)
- **Eksport/druk**: CSS `@media print` + opcjonalnie Puppeteer (Node.js sidecar) do PDF

> **Dlaczego D3.js a nie GoJS?** GoJS jest płatny (~$600/dev). D3.js daje pełną kontrolę nad SVG, działa dobrze z GEDCOM-style hierarchiami, duża społeczność przykładów genealogicznych. Alternatywa: [FamilyTreeJS](https://github.com/nickaknudson/family-tree) (open source, specjalizowany).

> **Dlaczego Alpine.js?** shadcn/ui opiera się na Radix UI dla interaktywności (dropdowny, dialogi, taby). Alpine.js pełni tę samą rolę w stack PHP — deklaratywne `x-data`, `x-show`, `x-on:click` bez potrzeby budowania całego SPA.

## Architektura systemu

### Wzorzec

MVC bez frameworka: `public/index.php` → Router → Controller → Service → Repository → MySQL.

```
genealog/
├── public/              # Document root (tylko index.php + assets)
│   ├── index.php        # Front controller
│   ├── css/             # Skompilowany CSS (Tailwind output)
│   └── js/              # d3.min.js, alpine.min.js, app.js
├── src/
│   ├── Core/            # Router, Request, Response, Session, DB, CSRF
│   ├── Controllers/     # AuthController, TreeController, PersonController, SearchController
│   ├── Services/        # TreeService, PersonService, GedcomService, MatchingService
│   ├── Repositories/    # PersonRepository, TreeRepository, RelationshipRepository
│   ├── Models/          # Person, Tree, Relationship, GlobalPersonIndex
│   ├── Registry/        # Adaptery rejestrów (RegistryInterface + implementacje)
│   └── Views/
│       ├── atoms/       # Button, Input, Badge, Avatar, Label...
│       ├── molecules/   # PersonCard, SearchField, FormGroup, Alert...
│       ├── organisms/   # TreeNav, PersonForm, RelationshipPanel, Header...
│       ├── templates/   # AppLayout, PrintLayout, AuthLayout
│       └── pages/       # dashboard.php, tree-view.php, person-detail.php...
├── migrations/          # SQL pliki migracji (001_init.sql, 002_...)
├── config/              # config.php (czyta .env przez getenv())
└── tests/               # PHPUnit
```

### Kluczowe domeny

**Person** — centralna encja. Każda osoba ma `visibility` (private/public/anonymous). Dla żyjących domyślnie `private` (RODO). Dane publiczne tylko dla historycznych (patrz: zasada 100 lat).

**Tree** — drzewo powiązane z właścicielem. Jeden użytkownik może mieć wiele drzew.

**Relationship** — relacja między osobami (`parent`, `child`, `spouse`, `sibling`). Przechowywana jako graf skierowany, nie jako drzewo binarne — pozwala na ponowne małżeństwa, adopcje, nieznanych rodziców.

**GlobalPersonIndex** — anonimowy fingerprint do matchingu między drzewami bez ujawniania PII.

### Multi-tenancy i prywatność

- Każde drzewo: `owner_id` + tabela `tree_members` (role: owner/editor/viewer)
- `Person.visibility`: `private` (tylko właściciel/zaproszeni), `public` (wszyscy), `anonymous` (globalny index bez PII)
- Żyjące osoby: **nigdy** nie trafiają do globalnego widoku z pełnymi danymi
- **Zasada 100 lat**: `born_at < NOW() - INTERVAL 100 YEAR` → osoba historyczna, może być `public`
- Kolumna `is_living TINYINT(1)` — ręczna flaga, nadrzędna wobec zasady 100 lat

## Baza danych — schemat

```sql
users          (id, email, password_hash, name, locale, created_at)
trees          (id, owner_id FK users, name, description, is_public, created_at)
tree_members   (tree_id FK, user_id FK, role ENUM('owner','editor','viewer'), invited_at)
persons        (id, tree_id FK, first_name, last_name, maiden_name,
                birth_date, birth_place, death_date, death_place,
                gender ENUM('male','female','unknown'),
                is_living TINYINT(1) DEFAULT 1,
                visibility ENUM('private','public','anonymous') DEFAULT 'private',
                notes TEXT, photo_path, gedcom_xref VARCHAR(20),
                created_by FK users, created_at, updated_at)
relationships  (id, person_a_id FK persons, person_b_id FK persons,
                type ENUM('parent','child','spouse','sibling','partner'),
                tree_id FK, start_date, end_date, notes, created_at)
global_index   (id, fingerprint_hash CHAR(64) UNIQUE, region VARCHAR(100),
                earliest_birth_year SMALLINT, source_tree_count INT DEFAULT 1)
invitations    (id, tree_id FK, invited_email, token CHAR(64), role, expires_at, used_at)
media          (id, person_id FK persons, tree_id FK, uploaded_by FK users,
                type ENUM('photo','document'),
                file_path VARCHAR(255), original_name VARCHAR(255),
                mime_type VARCHAR(50), file_size INT,
                caption VARCHAR(500), year SMALLINT,
                is_primary TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
```

**Fingerprint**: `SHA2(LOWER(TRIM(first_name)) || LOWER(TRIM(last_name)) || YEAR(birth_date) || birth_region, 256)` — generowany triggerem przy INSERT/UPDATE na `persons`.

**Indeksy krytyczne**: `persons(tree_id, last_name)`, `persons(fingerprint_hash)`, `relationships(person_a_id, type)`, `relationships(person_b_id, type)`.

## Atomic Design + shadcn/ui

### Zasada ogólna

Każdy komponent UI to plik PHP w `src/Views/{poziom}/`. Wywołanie przez `include` lub helper `component('atoms/button', ['label' => 'Zapisz'])`. Komponenty nie zawierają logiki biznesowej — tylko HTML + Tailwind + Alpine.js.

### Poziomy Atomic Design

| Poziom | Katalog | Opis | Przykłady |
|--------|---------|------|-----------|
| **Atoms** | `Views/atoms/` | Pojedynczy element, bez zależności | Button, Input, Badge, Avatar, Label, Separator, Spinner |
| **Molecules** | `Views/molecules/` | 2-3 atomy z logiką prezentacji | PersonCard, SearchField, FormGroup, Alert, Breadcrumb |
| **Organisms** | `Views/organisms/` | Samodzielna sekcja UI | TreeNavigator, PersonForm, RelationshipPanel, SiteHeader, InviteModal |
| **Templates** | `Views/templates/` | Szkielet strony bez danych | AppLayout, PrintLayout, AuthLayout |
| **Pages** | `Views/pages/` | Template + dane z kontrolera | dashboard.php, tree-view.php, person-detail.php |

### shadcn/ui jako system designu

Projekt używa shadcn/ui jako **wzorca wizualnego**, nie jako biblioteki npm. Komponenty są reimplementowane jako PHP/HTML z Tailwind CSS — zachowując te same tokeny kolorów, spacing i wygląd.

**CSS variables (w `public/css/globals.css`)** — kopiowane z shadcn:
```css
:root {
  --background: 0 0% 100%;
  --foreground: 240 10% 3.9%;
  --card: 0 0% 100%;
  --primary: 240 5.9% 10%;
  --primary-foreground: 0 0% 98%;
  --muted: 240 4.8% 95.9%;
  --muted-foreground: 240 3.8% 46.1%;
  --border: 240 5.9% 90%;
  --radius: 0.5rem;
}
```

**Referencja komponentów shadcn → PHP**:
| shadcn komponent | Plik PHP | Uwagi |
|-----------------|----------|-------|
| `<Button>` | `atoms/button.php` | `$variant`: default/outline/ghost/destructive |
| `<Input>` | `atoms/input.php` | + `<Label>` jako osobny atom |
| `<Badge>` | `atoms/badge.php` | `$variant`: default/secondary/destructive/outline |
| `<Card>` | `molecules/card.php` | Card + CardHeader + CardContent |
| `<Dialog>` | `organisms/dialog.php` | Alpine.js `x-data="{open: false}"` |
| `<Select>` | `molecules/select.php` | Alpine.js dla interaktywności |
| `<Table>` | `organisms/table.php` | Responsywny wrapper |
| `<Alert>` | `molecules/alert.php` | `$type`: info/success/warning/error |
| `<Avatar>` | `atoms/avatar.php` | Inicjały fallback gdy brak zdjęcia |

### Konwencja pliku komponentu

```php
<?php // src/Views/atoms/button.php
// Parametry: $label, $variant = 'default', $type = 'button', $href = null, $extra = ''
$classes = match($variant ?? 'default') {
    'outline'     => 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
    'ghost'       => 'hover:bg-accent hover:text-accent-foreground',
    'destructive' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
    default       => 'bg-primary text-primary-foreground hover:bg-primary/90',
};
?>
<button type="<?= $type ?>"
        class="inline-flex items-center justify-center rounded-md text-sm font-medium h-9 px-4 py-2 transition-colors <?= $classes ?> <?= $extra ?>">
    <?= htmlspecialchars($label) ?>
</button>
```

### Alpine.js dla interaktywności

Zamiast pisania czystego JS dla UI patterns, używaj Alpine.js deklaratywnie:

```html
<!-- Dialog / Modal -->
<div x-data="{ open: false }">
  <button @click="open = true">Dodaj osobę</button>
  <div x-show="open" x-transition class="fixed inset-0 bg-black/50">
    <?php include 'organisms/person-form.php' ?>
  </div>
</div>

<!-- Tabs -->
<div x-data="{ tab: 'info' }">
  <button :class="tab === 'info' && 'border-b-2 border-primary'" @click="tab = 'info'">Dane</button>
  <div x-show="tab === 'info'">...</div>
</div>
```

## Wizualizacja drzewa — rekomendacje

### Widoki do zaimplementowania (priorytet)

1. **Widok ancestorów** (pedigree chart) — klasyczne drzewo w górę: osoba → rodzice → dziadkowie. Najczęściej drukowany format.
2. **Widok potomków** (descendant chart) — w dół od przodka.
3. **Widok rodziny** (family group) — para + ich dzieci, jeden "kafelek". Najlepszy do druku A4.
4. **Fan chart** (opcjonalnie) — koło z pokoleniami jako wycinkami. Efektowny wizualnie.

### Implementacja D3.js

```js
// Hierarchia dla widoku ancestorów
const root = d3.stratify()
  .id(d => d.id)
  .parentId(d => d.father_id ?? d.mother_id)(persons);

const treeLayout = d3.tree().nodeSize([120, 200]);
```

Węzeł = karta osoby (SVG `foreignObject` z HTML lub `rect` + `text`). Kliknięcie → panel boczny z detalami. Zoom/pan przez `d3.zoom()`.

### Druk — rekomendacje

- **SVG do PDF**: najlepsza jakość — browser `window.print()` z `@media print` ukrywającym UI i pokazującym pełne SVG
- **Rozmiar strony**: A3 landscape dla drzew, A4 portrait dla kart rodziny
- Osobna strona `/tree/{id}/print` renderująca tylko drzewo bez nawigacji
- Opcja "eksportuj jako PNG" przez `svg.toDataURL()` w JS (canvas trick)
- Opcja pobierania GEDCOM — priorytet, bo użytkownicy chcą przenosić dane do Ancestry/MyHeritage

## GEDCOM — standard danych

GEDCOM 5.5.1 jest obowiązkowym formatem importu/eksportu. Większość użytkowników przyjdzie z plikiem `.ged` z Ancestry, MyHeritage lub Gramps.

```
src/Services/GedcomService.php   # import + eksport
```

Parser GEDCOM: linia = `LEVEL TAG [VALUE]`. Hierarchia przez poziomy 0-N. Kluczowe tagi: `INDI` (osoba), `FAM` (rodzina), `BIRT/DEAT/MARR` (zdarzenia), `NOTE`, `SOUR` (źródło).

**Gotowa biblioteka PHP**: [`fisharebest/webtrees`](https://github.com/fisharebest/webtrees) ma świetny parser GEDCOM jako osobną paczkę (MIT). Nie używaj całego frameworka, tylko `fisharebest/gedcom` przez Composer.

## Integracje z rejestrami publicznymi

### Przegląd rejestrów — dostęp i koszty

| Rejestr | Koszt | Metoda dostępu | Priorytet |
|---------|-------|---------------|-----------|
| **FamilySearch** | Darmowy | REST API + OAuth2 (oficjalny) | 🔴 MVP |
| **Szukaj w Archiwach** | Darmowy | REST API (oficjalny, klucz bezpłatny) | 🔴 MVP |
| **Wikidata** | Darmowy | SPARQL endpoint (publiczny) | 🟡 Faza 2 |
| **Geneteka** | Darmowy | CSV dump (legalne) + scraper (ryzyko) | 🔴 MVP |
| **Grobonet** | Darmowy dla użytkowników | Scraper (brak API publicznego) | 🟡 Faza 2 |
| **PRADZIAD** | Darmowy | Scraper formularza HTML | 🟡 Faza 2 |
| **Nekropole** | Darmowy | Scraper | 🟢 Faza 3 |
| **MyHeritage/Ancestry** | Płatny | API partnerskie (drogie) | ❌ Pomiń |

**Geneteka — najlepsze podejście**: PTG udostępnia **dumpy CSV** całej bazy do pobrania. Import lokalnie do MySQL → przeszukiwanie bez scrapingu. Legalnie, szybko, bez ryzyka blokady IP.

### Architektura — scraper jako osobny serwis

Scraping wydzielony jako **niezależny mikroserwis** — oddzielony od głównej aplikacji PHP. PHP backend komunikuje się z nim przez HTTP (REST) lub kolejkę zadań w MySQL.

**Rekomendacja: Python** — biblioteki BeautifulSoup4, httpx, Playwright (dla stron z JS) są znacznie dojrzalsze niż PHP odpowiedniki. PHP może scrapeować proste strony statyczne, ale Grobonet czy PRADZIAD mogą wymagać obsługi JS lub sesji cookies.

```
genealog/
├── app/               # Główna aplikacja PHP
└── scraper/           # Mikroserwis Python
    ├── main.py        # FastAPI — REST endpoint dla PHP
    ├── scrapers/
    │   ├── base.py           # BaseScraper (retry, rate limit, cache)
    │   ├── geneteka.py       # GenetekaScraper
    │   ├── grobonet.py       # GrobonetScraper
    │   ├── pradziad.py       # PradziaDScraper
    │   └── familysearch.py   # FamilySearchClient (REST API, nie scraper)
    ├── requirements.txt
    └── Dockerfile
```

### Przepływ wyszukiwania

```
Użytkownik → PHP SearchController
  → INSERT INTO search_jobs (registry, params, status='pending')
  → odpowiedź natychmiastowa: "Szukam..."

Python scraper (polling lub webhook):
  → pobiera job ze search_jobs WHERE status='pending'
  → wykonuje scraping/API call
  → UPDATE search_jobs SET status='done', results=JSON

PHP (Alpine.js polling co 2s):
  → GET /api/search-jobs/{id}
  → gdy status='done' → wyświetl wyniki użytkownikowi
```

### Tabele kolejki i cache

```sql
search_jobs (
  id, user_id FK, registry VARCHAR(50),
  params JSON,                    -- {lastName, firstName, birthYear, region}
  status ENUM('pending','running','done','failed') DEFAULT 'pending',
  results JSON,
  error TEXT,
  created_at TIMESTAMP, finished_at TIMESTAMP
)

registry_cache (
  id, registry VARCHAR(50), query_hash CHAR(64),
  results JSON, cached_at TIMESTAMP,
  UNIQUE KEY (registry, query_hash)
)
-- TTL: 30 dni. Geneteka CSV = 0 dni (lokalny DB, zawsze aktualne)
```

### Python scraper — szczegóły

```python
# scraper/scrapers/base.py
import httpx, asyncio
from abc import ABC, abstractmethod

class BaseScraper(ABC):
    RATE_LIMIT = 1.0  # sekundy między requestami
    HEADERS = {'User-Agent': 'Genealog.pl Research Tool/1.0 (kontakt@genealog.pl)'}

    async def search(self, last_name: str, first_name: str,
                     birth_year: int | None, region: str | None) -> list[dict]:
        await asyncio.sleep(self.RATE_LIMIT)
        return await self._do_search(last_name, first_name, birth_year, region)

    @abstractmethod
    async def _do_search(self, ...) -> list[dict]: ...
```

```python
# scraper/scrapers/geneteka.py
from bs4 import BeautifulSoup
import httpx

class GenetekaScraper(BaseScraper):
    BASE_URL = 'https://geneteka.genealodzy.pl/index.php'

    async def _do_search(self, last_name, first_name, birth_year, region):
        async with httpx.AsyncClient(headers=self.HEADERS) as client:
            r = await client.get(self.BASE_URL, params={
                'op': 'se', 'lang': 'pol',
                'search_lastname': last_name,
                'search_name': first_name or '',
                'from_date': birth_year - 5 if birth_year else '',
                'to_date': birth_year + 5 if birth_year else '',
            })
        soup = BeautifulSoup(r.text, 'html.parser')
        # parsowanie tabeli wyników...
        return self._parse_results(soup)
```

```python
# scraper/main.py — FastAPI endpoint wywoływany przez PHP
from fastapi import FastAPI
app = FastAPI()

@app.post('/search')
async def search(job_id: int, registry: str, params: dict):
    scraper = get_scraper(registry)  # factory
    results = await scraper.search(**params)
    # UPDATE search_jobs w MySQL
    return {'job_id': job_id, 'results': results}
```

### PHP → Python komunikacja

```php
// src/Services/RegistrySearch/SearchDispatcher.php
class SearchDispatcher {
    public function dispatch(int $jobId, string $registry, array $params): void {
        // Opcja A: HTTP call do Python FastAPI (synchronicznie jeśli szybkie)
        $client = new \CurlHttpClient();
        $client->post('http://scraper:8001/search', [
            'job_id' => $jobId, 'registry' => $registry, 'params' => $params
        ]);

        // Opcja B: tylko INSERT do search_jobs, Python sam polluje (prostsze)
        // Python co 5s: SELECT * FROM search_jobs WHERE status='pending' LIMIT 5
    }
}
```

**Dla MVP**: opcja B (Python polluje tabelę) — prostsze, bez konfiguracji sieci między kontenerami.

### Rate limiting i etykieta scrapingu

- Nagłówek `User-Agent` z nazwą projektu i e-mailem kontaktowym — standardowa etykieta
- Min. 1 sekunda między requestami do tego samego hosta
- Respektowanie `robots.txt` — `robotparser` w Pythonie
- Cache 30 dni — nie odpytuj dwa razy o to samo
- Logowanie wszystkich requestów do scrapowanych serwisów

## Upload zdjęć

Każda osoba w drzewie może mieć wiele zdjęć (tabela `media`). Jedno zdjęcie oznaczone `is_primary = 1` służy jako awatar w kartach i drzewie.

### Przepływ uploadu

```
POST /tree/{id}/person/{pid}/media
  → MediaController::upload()
  → MediaService::validateAndStore()
  → Storage: storage/media/{tree_id}/{uuid}.webp
  → Serwowanie: GET /media/{uuid} → public/media.php (z auth check)
```

### Struktura storage

```
storage/                  # POZA public/ — niedostępne bezpośrednio
└── media/
    └── {tree_id}/
        ├── {uuid}.webp   # Oryginał przekonwertowany do WebP
        └── {uuid}_thumb.webp  # Miniatura 200x200
```

### Zasady przetwarzania

- **Format wyjściowy**: zawsze WebP (mniejszy rozmiar, lepsza jakość) — konwersja przez GD (`imagewebp()`) lub ImageMagick
- **Miniatura**: 200×200px crop do środka (`imagecopyresampled`) — używana w kartach drzewa i listach
- **Max upload**: 10 MB (`upload_max_filesize = 10M` w php.ini)
- **Akceptowane MIME**: `image/jpeg`, `image/png`, `image/webp`, `image/gif` — walidacja przez `finfo_file()`, nie tylko rozszerzenie
- **Nazwa pliku**: `bin2hex(random_bytes(16))` — nigdy oryginalna nazwa z `$_FILES`

### Serwowanie z kontrolą dostępu

`public/media.php` — jedyny punkt serwowania plików:
```php
// Weryfikuje: czy zalogowany użytkownik ma dostęp do tree_id danego pliku
// Następnie: readfile() + odpowiednie Content-Type
header('Content-Type: image/webp');
header('Cache-Control: private, max-age=86400');
readfile(STORAGE_PATH . '/media/' . $treeId . '/' . $uuid . '.webp');
```

### Komponent uploadu (UI)

`src/Views/molecules/media-upload.php` — dropzone z podglądem:
- Drag & drop przez Alpine.js + `<input type="file" multiple accept="image/*">`
- Podgląd przed wysłaniem (`FileReader` API)
- Progress bar przez `XMLHttpRequest.upload.onprogress`
- Galeria zdjęć osoby z możliwością oznaczenia jako główne (`is_primary`)

## Bezpieczeństwo

- PDO prepared statements wszędzie — zero string concatenation w SQL
- CSRF token: generowany w `Session::start()`, weryfikowany w `Core\Request::verifyCsrf()`
- Rate limiting auth: `rate_limits(ip, endpoint, attempts, window_start)` — max 5 prób/15 min
- `password_hash(PASSWORD_BCRYPT, ['cost' => 12])`
- Weryfikacja dostępu do drzewa: `TreeAccessMiddleware` sprawdza `tree_members` lub `owner_id` **przed każdym** controllerem drzewa
- Upload zdjęć: walidacja MIME (`finfo_file` — tylko image/jpeg, image/png, image/webp), max 10MB, zmiana nazwy na UUID, storage poza `public/`, serwowanie przez `public/media.php?id=X` z weryfikacją dostępu
- Zaproszenia: token jednorazowy `bin2hex(random_bytes(32))`, TTL 7 dni

## System powiadomień

### Scenariusze wyzwalające powiadomienie

| Zdarzenie | Odbiorca | Treść |
|-----------|----------|-------|
| Globalne dopasowanie osoby (fingerprint match) | Właściciel drzewa | „Znaleziono potencjalne powiązanie z drzewem [X] — [Imię Nazwisko, rok ur.]" |
| Wynik z zewnętrznego rejestru pasuje do osoby | Twórca zapytania | „Geneteka znalazła dopasowanie dla [Imię Nazwisko]" |
| Użytkownik zaproszony do drzewa | Zaproszony | „[Imię] zaprasza Cię do współpracy przy drzewie [Nazwa]" |
| Ktoś edytuje osobę w współdzielonym drzewie | Pozostali edytorzy | „[Imię] zaktualizował dane osoby [Imię Nazwisko]" |

### Architektura

Powiadomienia są **asynchroniczne** — wyzwalane przez `NotificationService::dispatch()`, przechowywane w DB, odczytywane przy każdym request przez zalogowanego użytkownika.

```sql
notifications (
  id, user_id FK users, type VARCHAR(50),
  title VARCHAR(255), body TEXT,
  link VARCHAR(500),          -- URL do kliknięcia (np. /tree/5/person/12)
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

### Przepływ dopasowania osoby

```
MatchingService::checkFingerprint(Person $person)
  → szuka fingerprint_hash w global_index innych drzew
  → jeśli source_tree_count > 0 → NotificationService::dispatch('person_match', ...)
  → INSERT INTO notifications (user_id = właściciel drzewa, type = 'person_match', link = ...)
```

Dopasowanie **nie ujawnia** danych z obcego drzewa — tylko sygnalizuje że może istnieć powiązanie. Użytkownik klika link i widzi formularz z prośbą o kontakt do właściciela drugiego drzewa.

### Wyświetlanie w UI

- **Bell icon** w nagłówku (`organisms/header.php`) z licznikiem nieprzeczytanych — Alpine.js polling co 30s (`fetch('/api/notifications/count')`)
- **Dropdown lista** ostatnich 10 powiadomień — kliknięcie oznacza jako przeczytane + redirect do `link`
- **Atom**: `atoms/notification-dot.php` — czerwona kropka z liczbą

### Brak kolejki (MVP)

Na etapie MVP powiadomienia są tworzone synchronicznie w `NotificationService::dispatch()` — bezpośredni INSERT do DB. Kolejka (Redis/cron) dopiero gdy pojawi się wysyłka e-mail.

### E-mail (opcjonalnie, po MVP)

`php mail()` lub `PHPMailer` — wysyłka digest raz dziennie (cron `0 8 * * *`). Użytkownik może wyłączyć w ustawieniach (`users.email_notifications TINYINT(1) DEFAULT 1`).

## Fazy implementacji (rekomendowana kolejność)

1. **Core**: Router, DB wrapper (PDO), Session, CSRF, Auth (rejestracja/logowanie)
2. **Person CRUD**: dodawanie osób, relacji, zdjęć — bez wizualizacji
3. **Wizualizacja D3.js**: widok ancestorów + widok potomków
4. **Tree sharing**: zaproszenia, role, widok współdzielony
5. **GEDCOM import/eksport**: `fisharebest/gedcom`
6. **Druk/PDF**: strona print, CSS print stylesheet
7. **Globalne wyszukiwanie**: fingerprint matching między drzewami
8. **Rejestry zewnętrzne**: Geneteka → FamilySearch → Grobonet
9. **Fan chart + eksport PNG**

## Baza danych — Docker

Baza działa na Dockerze. Nazwa kontenera: **`mariadb_docker`**.  
Dane dostępowe są w **`.env.local`** — nigdy nie hardkoduj ich w kodzie.

> **Uwaga:** `DATABASE_NAME` w `.env.local` jest puste — uzupełnij przed pierwszym uruchomieniem (np. `genealog`). `DATABASE_USER` i `DATABASE_PASSWORD` są już ustawione.

```bash
# Uruchomienie kontenera (jeśli nie działa)
docker start mariadb_docker

# Połączenie z bazą
source .env.local && docker exec -it mariadb_docker mariadb \
  -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME

# Jednorazowe utworzenie bazy i użytkownika
source .env.local && docker exec -it mariadb_docker mariadb -u root -p -e "
  CREATE DATABASE IF NOT EXISTS \`${DATABASE_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '${DATABASE_USER}'@'%' IDENTIFIED BY '${DATABASE_PASSWORD}';
  GRANT ALL PRIVILEGES ON \`${DATABASE_NAME}\`.* TO '${DATABASE_USER}'@'%';
  FLUSH PRIVILEGES;
"

# Uruchomienie migracji
source .env.local && docker exec -i mariadb_docker mariadb \
  -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
  < migrations/001_init.sql
```

## Komendy deweloperskie

```bash
# Uruchomienie lokalne
php -S localhost:8080 -t public/

# Testy
./vendor/bin/phpunit tests/

# Composer
composer require fisharebest/gedcom
composer require --dev phpunit/phpunit
```

## Konwencje kodu

- PHP: PSR-12, `declare(strict_types=1)` w każdym pliku
- Tabele: snake_case, liczba mnoga
- Relacje w DB: zawsze FK z `ON DELETE RESTRICT` (nie CASCADE) — dane genealogiczne nigdy nie kasują się w kaskadzie
- Widoki: `src/Views/{module}/{action}.php`, layout przez `include`
- Migracje: numerowane `001_`, `002_` — **nigdy** modyfikowane po deploy

## Język

- Kod (zmienne, funkcje, klasy): **angielski**
- UI i komunikaty dla użytkownika: **polski**
- Dokumentacja (CLAUDE.md, komentarze architektoniczne): **polski**
