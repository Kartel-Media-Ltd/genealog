# Genealog

System do budowania drzew genealogicznych rodziny. Każdy użytkownik tworzy własne drzewo, może zapraszać inne osoby do współpracy. Globalny widok wszystkich dodanych osób umożliwia łączenie rodzin z zachowaniem prywatności.

## Wymagania

- PHP 8.2+
- MySQL 8.0+ / MariaDB
- Composer
- Opcjonalnie: Redis 6+ (dla asynchronicznego reindeksowania Discovery)

## Instalacja

```bash
git clone <repo>
cd genealog
composer install
cp .env.local.example .env.local   # uzupełnij dane bazy danych
```

Uruchom migracje:

```bash
source .env.local && docker exec -i mariadb_docker mariadb \
  -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
  < migrations/001_init.sql
# powtórz dla kolejnych plików migracji (002, 003, ...)
```

Uruchom serwer lokalny:

```bash
php -S localhost:8080 -t public/
```

## Konfiguracja `.env.local`

```dotenv
APP_ENV=production
APP_DEBUG=false
SITE_URL=https://twojadomena.pl

DATABASE_HOST=localhost
DATABASE_PORT=3306
DATABASE_NAME=genealog
DATABASE_USER=user
DATABASE_PASSWORD=haslo
```

## Redis — opcjonalny (Discovery I7)

Redis umożliwia asynchroniczne reindeksowanie globalnego indeksu osób. Bez Redis reindeksowanie działa synchronicznie (blokuje request HTTP) — akceptowalne dla drzew do ~1000 osób.

### Konfiguracja

Dodaj do `.env.local` jedno z poniższych:

```dotenv
# TCP
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# lub Unix socket
REDIS_SOCKET=/var/run/redis/redis.sock
```

Jeśli żadna ze zmiennych nie jest zdefiniowana → aplikacja działa bez Redis (fallback synchroniczny).

### Lokalny worker (dev / VPS z SSH)

```bash
php bin/reindex-worker.php
```

Worker nasłuchuje na kolejce Redis i przetwarza joby w tle. Zatrzymuje się przez `CTRL+C`.

Dla produkcji użyj supervisor lub systemd:

```ini
# /etc/supervisor/conf.d/genealog-reindex.conf
[program:genealog-reindex]
command=php /var/www/genealog/bin/reindex-worker.php
autostart=true
autorestart=true
stderr_logfile=/var/www/genealog/storage/logs/reindex-worker.log
```

### Hosting z Apache + cron (cPanel)

Użyj skryptu `scripts/reindex-cron.php` zamiast workera.

**1. Sprawdź absolutną ścieżkę do projektu** (SSH lub cPanel → File Manager):

```bash
pwd
# np. /home/TWOJ_USER/public_html/genealog
```

**2. Dodaj cron w cPanel → Cron Jobs** (co 5 minut):

```
*/5 * * * *   php /home/TWOJ_USER/public_html/genealog/scripts/reindex-cron.php >> /home/TWOJ_USER/public_html/genealog/storage/logs/reindex-cron.log 2>&1
```

Zamień `/home/TWOJ_USER/public_html/genealog/` na rzeczywistą ścieżkę.

**Jak to działa:**

```
Użytkownik włącza odkrywanie w ustawieniach drzewa
         ↓
updateSettings() → LPUSH do kolejki Redis (natychmiastowa odpowiedź)
         ↓
      (cron co 5 min)
         ↓
reindex-cron.php → rPop → reindexTree() → log
```

Skrypt posiada wbudowany lock file (`storage/reindex-cron.lock`) — jeśli poprzedni cron jeszcze działa, nowy kończy się natychmiast bez duplikowania pracy.

Logi crona: `storage/logs/reindex-cron.log`

## Testy

```bash
./vendor/bin/phpunit tests/
```

## Struktura katalogów

```
genealog/
├── bin/             # Skrypty CLI (worker, cleanup)
├── config/          # Konfiguracja aplikacji
├── migrations/      # Migracje SQL
├── public/          # Document root (index.php + assets)
├── scripts/         # Skrypty cron
├── src/             # Kod źródłowy (MVC)
│   ├── Controllers/
│   ├── Core/
│   ├── Models/
│   ├── Repositories/
│   ├── Services/
│   └── views/
├── storage/         # Pliki tymczasowe, logi, media (poza public/)
└── tests/           # Testy PHPUnit
```
