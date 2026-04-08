# Audyt architektury — Genealog Backend
**Data:** 2026-04-07 | **Scope:** Component diagram, sequence diagrams, coupling/cohesion, scalability
**Narzędzia:** PHP 8.2 MVC bez frameworka, MariaDB, PDO, natywne sesje

---

## 1. Diagram komponentów

```mermaid
flowchart TB
    subgraph WebLayer["Warstwa Web"]
        REQ[HTTP Request] --> ROUTER[Router\npublic/index.php]
        ROUTER --> RES[HTTP Response]
    end

    subgraph CrossCutting["Cross-cutting Concerns"]
        CSRF[Csrf\nhash_equals, rotation]
        SESSION[Session\nhttponly, SameSite=Strict, 8h TTL]
        RATELIMITER[RateLimiter\nDB-backed, per-IP]
        EVTDISP[EventDispatcher\nstatic state]
        UUID[Uuid — BRAK\n8x bin2hex duplikat]
    end

    subgraph MW["Middleware"]
        AUTHMW[AuthMiddleware\nsession_version MARTWY KOD]
        ADMINMW[AdminMiddleware\nbez DB check]
    end

    subgraph Controllers["15 Kontrolerów"]
        AUTHCTRL[AuthController]
        PERSONCTRL[PersonController\n9 deps]
        DISCCTRL[DiscoveryController\n9 deps + bezpośredni SQL]
        GEDCOMCTRL[GedcomController\nbez rate limit]
        ADMINCTRL[AdminController]
        OTHERS[+10 innych]
    end

    subgraph Services["13+ Serwisów"]
        AUTHSVC[AuthService]
        PERSONSVC[PersonService]
        GEDCOMSVC[GedcomService\n775 LOC — god class]
        MEDIASVC[MediaService\nfinfo+UUID+WebP]
        NOTIFYSVC[NotificationService]
        subgraph Discovery["Discovery Pipeline"]
            GLOBALIDX[GlobalIndexService\n5 warunków RODO]
            MATCHSVC[MatchingService\nSYNCHRONICZNY]
            REGISTRY[MatchSourceRegistry]
            LOCAL[LocalTreeMatchSource]
            CROSS[CrossTreeMatchSource]
            FAM[FamilySearchMatchSource]
            GEN[GenetykaMatchSource]
        end
    end

    subgraph Repos["8 Repozytoriów"]
        PERSONREPO[PersonRepository\ntenant-isolated]
        TREEREPO[TreeRepository\ntenant-isolated]
        RELREPO[RelationshipRepository\ntenant-isolated]
        USERREPO[UserRepository]
        DISCREPO[DiscoveryRepository\nbezpośredni UUID]
        OTHERS2[+3 inne]
    end

    subgraph DB["MariaDB (Docker)"]
        TABLES[(users / trees / persons\nrelationships / global_person_index\nnotifications / source_audit_log\nmedia / invitations)]
    end

    ROUTER --> MW
    MW --> Controllers
    Controllers --> Services
    Services --> Repos
    Services --> EVTDISP
    EVTDISP --> GLOBALIDX
    EVTDISP --> MATCHSVC
    Repos --> DB
    RATELIMITER --> DB
    CSRF -.-> Controllers
    SESSION -.-> MW
```

**Legenda problemów:**
- Czerwone: `MARTWY KOD`, `god class`, `SYNCHRONICZNY`, `bezpośredni SQL`
- Żółte: `9 deps`, `brak rate limit`, `bez DB check`

---

## 2. Diagram sekwencji — Logowanie użytkownika

```mermaid
sequenceDiagram
    actor User
    participant Router as Router\n(index.php)
    participant RateLimiter
    participant AuthCtrl as AuthController
    participant AuthSvc as AuthService
    participant UserRepo as UserRepository
    participant Session
    participant DB as MariaDB

    User->>Router: POST /login {email, password}
    Router->>AuthCtrl: handle()
    AuthCtrl->>RateLimiter: isLimited(ip, 'login', 5, 900)
    RateLimiter->>DB: SELECT attempts FROM rate_limits
    DB-->>RateLimiter: attempts count
    alt > 5 prób / 15 min
        RateLimiter-->>AuthCtrl: true
        AuthCtrl-->>User: 429 Too Many Requests
    else OK
        AuthCtrl->>AuthSvc: login(email, password)
        AuthSvc->>UserRepo: findByEmail(email)
        UserRepo->>DB: SELECT * FROM users WHERE email = ?
        DB-->>UserRepo: User row
        UserRepo-->>AuthSvc: User|null
        alt User not found lub is_active=0
            AuthSvc-->>AuthCtrl: false
            AuthCtrl-->>User: Flash error, redirect /login
        else User found
            AuthSvc->>AuthSvc: password_verify(password, hash)
            alt hasło błędne
                AuthSvc-->>AuthCtrl: false
                AuthCtrl-->>User: Flash error, redirect /login
            else hasło OK
                AuthSvc->>Session: session_regenerate_id(true)
                AuthSvc->>Session: set('user_id', id)
                AuthSvc->>Session: set('session_version', version)
                Note over AuthSvc,Session: ⚠️ session_version nigdy nie sprawdzany\n(AuthMiddleware bez userRepo)
                AuthSvc-->>AuthCtrl: true
                AuthCtrl-->>User: redirect /dashboard
            end
        end
    end
```

---

## 3. Diagram sekwencji — Dodanie osoby z EventDispatcher

```mermaid
sequenceDiagram
    actor Editor
    participant PersonCtrl as PersonController
    participant PersonSvc as PersonService
    participant PersonRepo as PersonRepository
    participant EventDisp as EventDispatcher\n(static)
    participant MatchSvc as MatchingService\n(SYNCHRONICZNY)
    participant GlobalIdx as GlobalIndexService
    participant NotifySvc as NotificationService
    participant DB as MariaDB

    Editor->>PersonCtrl: POST /tree/{id}/person {dane}
    PersonCtrl->>PersonSvc: create(treeId, data)
    PersonSvc->>PersonRepo: insert(person)
    PersonRepo->>DB: INSERT INTO persons ...
    DB-->>PersonRepo: person_id
    PersonRepo-->>PersonSvc: Person

    PersonSvc->>EventDisp: dispatch('person.created', Person)
    Note over EventDisp: SYNCHRONICZNY — blokuje response

    EventDisp->>GlobalIdx: onPersonCreated(Person)
    GlobalIdx->>GlobalIdx: sprawdź 5 warunków RODO\n(is_living=0, visibility, tree.is_indexed, user.opt_in, 100 lat)
    alt Wszystkie warunki spełnione
        GlobalIdx->>DB: INSERT/UPDATE global_person_index
    end

    EventDisp->>MatchSvc: findAndNotifyMatches(Person)
    MatchSvc->>DB: SELECT fingerprint matches
    Note over MatchSvc,DB: 100-500ms blokowania
    alt Znaleziono dopasowanie
        MatchSvc->>NotifySvc: dispatch('person_match', ...)
        NotifySvc->>DB: INSERT INTO notifications
    end

    PersonSvc-->>PersonCtrl: Person
    PersonCtrl-->>Editor: redirect /tree/{id}/person/{pid}
    Note over Editor: Opóźnienie 100-500ms z powodu sync matching
```

---

## 4. Diagram sekwencji — Cross-tree Discovery

```mermaid
sequenceDiagram
    actor User
    participant DiscCtrl as DiscoveryController
    participant DiscRepo as DiscoveryRepository
    participant Registry as MatchSourceRegistry
    participant Local as LocalTreeMatchSource
    participant Cross as CrossTreeMatchSource
    participant DB as MariaDB
    participant GlobalIdx as global_person_index

    User->>DiscCtrl: GET /discovery/search?q=Kowalski
    DiscCtrl->>DiscRepo: search(params)
    Note over DiscCtrl: ⚠️ Bezpośrednie $db->fetchOne() też w kontrolerze

    DiscCtrl->>Registry: getSources()
    Registry-->>DiscCtrl: [Local, Cross, FamilySearch, Geneteka]

    DiscCtrl->>Local: search(Kowalski, ...)
    Local->>DB: SELECT FROM persons WHERE tree_id IN (user_trees) AND last_name LIKE ?
    DB-->>Local: lokalne wyniki

    DiscCtrl->>Cross: search(Kowalski, ...)
    Cross->>GlobalIdx: SELECT fingerprint_hash, region FROM global_person_index
    Note over Cross,GlobalIdx: Dane pseudonimizowane\nBrak PII żyjących osób
    GlobalIdx-->>Cross: fingerprint matches
    Cross-->>DiscCtrl: cross-tree matches (bez PII)

    DiscCtrl-->>User: wyniki z obu źródeł
    Note over User: Żyjące osoby widoczne tylko jako\n"potencjalne dopasowanie" bez danych
```

---

## 5. Metryki coupling/cohesion

### Zależności kontrolerów (Fan-In / Fan-Out)

| Kontroler | Zależności (Fan-Out) | Ocena |
|-----------|---------------------|-------|
| PersonController | 9 deps | Wysoki — jednorodny (CRUD osoby) |
| DiscoveryController | 9 deps + bezpośredni SQL | Wysoki — **niejednorodny** |
| AdminController | ~7 deps | Akceptowalny |
| AuthController | ~4 deps | Dobry |
| GedcomController | ~5 deps | Akceptowalny |
| InvitationController | ~4 deps | Dobry |

### Dependency Injection — brak kontenera DI

`public/index.php` tworzy ~25 obiektów ręcznie. Problemy:
1. Brak lazy loading — wszystkie serwisy tworzone przy każdym requeście.
2. Trudny do testowania bez zamiany zależności (mock).
3. Błąd konfiguracji (AuthMiddleware bez userRepo) łatwy do popełnienia i trudny do wykrycia.

Dla obecnej skali projektu akceptowalne. Przy wzroście do >20 kontrolerów — rozważyć lekki kontener DI (PHP-DI lub własny).

---

## 6. Bottlenecki wydajnościowe

### B1 — MatchingService synchroniczny (HIGH)
Wywołanie `MatchingService::findAndNotifyMatches()` blokuje response o 100-500ms przy każdym dodaniu osoby. Przy dużej bazie global_index — ryzyko timeoutu.

**Rozwiązanie:** Przenieść do asynchronicznego job queue (tabela `async_jobs`) lub przynajmniej wykonywać po wysłaniu response przez `fastcgi_finish_request()` / output buffering trick.

### B2 — GlobalIndexService::reindexTree synchroniczny (HIGH)
Reindeksacja całego drzewa wykonywana w ramach HTTP request. Przy drzewie 1000+ osób — timeout webservera.

**Rozwiązanie:** Job queue + endpoint statusu reindexacji.

### B3 — RateLimiter — SELECT+INSERT per request (MEDIUM)
Każdy request do chronionych endpointów wykonuje 2 zapytania do bazy danych. Przy dużym ruchu — wąskie gardło.

**Rozwiązanie:** Redis jako backend RateLimiter (atomic INCR + EXPIRE).

### B4 — GedcomService::import bez limitów (MEDIUM)
Brak `set_time_limit()`, brak limitu liczby rekordów. Import pliku 50MB z 100 000 osób może blokować serwer przez minuty.

**Rozwiązanie:** Limit rekordów per request (np. 5000), job queue dla dużych plików, progress tracking.

### B5 — Sesje plikowe (LOW przy MVP)
Natywne sesje PHP używają blokady pliku (file lock). Przy >100 równoczesnych użytkowników — lock contention dla sesji tego samego użytkownika (np. AJAX requests).

**Rozwiązanie długoterminowe:** Redis session handler (`session.save_handler = redis`).

---

## 7. Ocena gotowości na skalowanie horyzontalne

| Aspekt | Status | Uwagi |
|--------|--------|-------|
| Baza danych | Gotowe | MariaDB w Dockerze, łatwy deploy na dedykowany host |
| Sesje | Niegotowe | Plikowe sesje — nie działają multi-node |
| Rate limiting | Niegotowe | DB-backed — nie synchronizuje się między węzłami |
| Pliki mediów | Niegotowe | Lokalny storage — wymaga S3/NFS dla multi-node |
| EventDispatcher | Niegotowe | Static state — nie działa w multi-process |
| Kod aplikacji | Gotowe | Bezstanowe handlery HTTP |
| Matching async | Niegotowe | Synchroniczny blokuje response |

**Wniosek:** Aplikacja skrojona pod single-server deployment — odpowiednia dla MVP. Przed skalowaniem horyzontalnym wymagane: Redis sessions, Redis rate limiter, S3/NFS storage, async job queue.

---

## 8. Rekomendacje architektoniczne (priorytet)

| ID | Rekomendacja | Effort | Priorytet |
|----|-------------|--------|-----------|
| A1 | Async EventDispatcher dla person.created (job queue w DB) | L | HIGH |
| A2 | Redis session handler | S | HIGH |
| A3 | Rate limit `/invite/{token}` — brak ochrony | XS | MEDIUM |
| A4 | Podział DiscoveryController na DiscoverySettingsController + DiscoveryApiController | M | MEDIUM |
| A5 | Audit log dla GedcomController::import do source_audit_log | XS | MEDIUM |
| A6 | Limit rekordów GEDCOM + set_time_limit(300) | M | MEDIUM |
| A7 | GEDCOM export filtrować is_living=1 (nie eksportować żyjących) | S | HIGH |
| A8 | Rate limit dla photo upload per-user | XS | LOW |
| A9 | Ekstrakcja Uuid::generate() — DRY fix | XS | HIGH |
| A10 | src/Core/DI — prosty kontener dependency injection | L | LOW |
