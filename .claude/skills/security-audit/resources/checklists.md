# Security Audit — Pelne Checklists

Ten plik zawiera rozszerzone checklists dla kazdego standardu. Uzyj go jako referencje podczas audytu.

---

## 1. OWASP Top 10 (2021) — Pelna Checklist

### A01: Broken Access Control

#### Tenant Isolation
- [ ] Kazdy SELECT/UPDATE/DELETE zawiera `WHERE tenantId = ?`
- [ ] Prisma queries — `where: { tenantId }` w KAZDYM zapytaniu
- [ ] TenantGuard — weryfikuje tenant context z JWT
- [ ] CompanyScope — COMPANY role ma dostep do WSZYSTKICH tenantow firmy
- [ ] GlobalScope — GLOBAL role (super_admin) ma dostep do wszystkiego
- [ ] SelfScope — SELF role widzi TYLKO swoje dane

#### Authorization
- [ ] `@RequirePermissions()` na KAZDYM CRUD endpoint
- [ ] Permissions granularne: `[module].[action]` (np. `rooms.create`, `rooms.delete`)
- [ ] Brak wildcard permissions w produkcji
- [ ] Role priority — nie mozna przypisac roli z priority >= swojej
- [ ] Permission inheritance — wyzsza rola dziedziczy permissions nizszej
- [ ] API routes — kazdy POST/PUT/PATCH/DELETE wymaga auth

#### IDOR (Insecure Direct Object References)
- [ ] UUID v4 dla WSZYSTKICH publicznych ID (nie autoincrement)
- [ ] Ownership check — `resource.tenantId === user.tenantId`
- [ ] Nested resources — weryfikacja parent ownership (np. equipment w danym tenant)
- [ ] Batch operations — weryfikacja kazdego ID w batch
- [ ] File access — S3 presigned URLs z tenant scope

#### Access Control Bypass
- [ ] Brak REST endpoint bez @Auth() lub @Public() dekoratora
- [ ] Brak frontend-only access control (wszystko weryfikowane server-side)
- [ ] Rate limiting na sensitive endpoints (login, register, password reset)
- [ ] Account enumeration prevention — identyczne odpowiedzi dla valid/invalid email

### A02: Cryptographic Failures

#### Password Security
- [ ] Bcrypt/Argon2 — cost factor >= 12
- [ ] Minimum 8 znakow (zalecane 12)
- [ ] Entropy check — blokada popularnych hasel
- [ ] Brak przechowywania plaintext hasel (NIGDY)
- [ ] Password reset — token z TTL (max 1h), jednorazowy

#### Token Security
- [ ] JWT — krotki TTL (15-30 min access, 7d refresh)
- [ ] Refresh token rotation — nowy token po kazdym uzyciu
- [ ] Token revocation — blacklist w Redis
- [ ] Secure cookie flags: httpOnly, secure, SameSite=Strict
- [ ] Brak tokenow w URL query string
- [ ] Brak tokenow w localStorage (uzywaj httpOnly cookies)

#### Data Encryption
- [ ] TLS 1.2+ — HTTPS only, HSTS header
- [ ] Database — szyfrowanie at-rest (PostgreSQL TDE lub disk encryption)
- [ ] Backups — zaszyfrowane
- [ ] PII — dodatkowe szyfrowanie w DB (email, telefon, PESEL)
- [ ] S3 — server-side encryption (SSE-S3 lub SSE-KMS)
- [ ] Redis — TLS connection, AUTH password

#### Key Management
- [ ] Secrets w env variables (nie w kodzie)
- [ ] Key rotation plan
- [ ] Rozne klucze per environment (dev/staging/prod)
- [ ] Brak secrets w git history

### A03: Injection

#### SQL Injection
- [ ] Prisma ORM — parametryzowane zapytania (domyslnie bezpieczne)
- [ ] `$queryRaw` / `$executeRaw` — TYLKO z tagged template literals
- [ ] Brak string concatenation w SQL queries
- [ ] Brak dynamicznych nazw tabel/kolumn z user input

#### Cross-Site Scripting (XSS)
- [ ] React — auto-escaping w JSX (domyslnie bezpieczne)
- [ ] Brak `dangerouslySetInnerHTML` (lub sanitized z DOMPurify)
- [ ] CSP header — `script-src 'self'`, brak `unsafe-inline`/`unsafe-eval`
- [ ] User input w atrybutach HTML — escaped
- [ ] URL validation — brak `javascript:` protocol
- [ ] SVG uploads — sanitized (mogą zawierać JavaScript)

#### Command Injection
- [ ] Brak `child_process.exec()` z user input
- [ ] Brak `eval()`, `new Function()` z user input
- [ ] File paths — walidacja, brak `../` traversal
- [ ] Filenames — sanitized (alfanumeryczne + bezpieczne znaki)

#### Other Injections
- [ ] LDAP injection — escaped special chars (jesli LDAP)
- [ ] Template injection — brak user input w templates server-side
- [ ] Header injection — walidacja headersow
- [ ] Log injection — sanitize user input przed logowaniem

### A04: Insecure Design

#### Threat Modeling
- [ ] STRIDE analiza dla krytycznych flow'ow (auth, payment, data export)
- [ ] Data flow diagrams — zidentyfikowane trust boundaries
- [ ] Attack surface — zmapowana (endpoints, files, queues)
- [ ] Abuse cases — zdefiniowane obok use cases

#### Business Logic
- [ ] Rate limiting — per user, per IP, per tenant
- [ ] Quotas — limity na zasoby (np. max 1000 rooms per tenant)
- [ ] Workflow validation — statusy zmieniaja sie TYLKO w dozwolonych kierunkach
- [ ] Idempotency — powtorzony request nie tworzy duplikatow
- [ ] Time-of-check-to-time-of-use (TOCTOU) — transakcje DB

### A05: Security Misconfiguration

#### Server Configuration
- [ ] Debug mode OFF w produkcji (`NODE_ENV=production`)
- [ ] Stack traces — NIE ujawniane w API responses
- [ ] Default credentials — zmienione (DB, Redis, admin)
- [ ] Unnecessary features — wylaczone (Swagger w prod za auth)
- [ ] Directory listing — wylaczone

#### HTTP Headers
- [ ] `X-Frame-Options: DENY` lub `SAMEORIGIN`
- [ ] `X-Content-Type-Options: nosniff`
- [ ] `X-XSS-Protection: 0` (deprecated, CSP zamiast)
- [ ] `Content-Security-Policy` — restrykcyjne
- [ ] `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- [ ] `Referrer-Policy: strict-origin-when-cross-origin`
- [ ] `Permissions-Policy` — wylacz niepotrzebne API (camera, microphone)

#### CORS
- [ ] Whitelisted origins (nie `*`)
- [ ] Credentials — `Access-Control-Allow-Credentials` tylko z whitelist
- [ ] Methods — ograniczone do potrzebnych
- [ ] Headers — ograniczone do potrzebnych

### A06: Vulnerable and Outdated Components

#### Dependency Management
- [ ] `npm audit` — 0 krytycznych, 0 high
- [ ] `pnpm audit` — regularne skanowanie
- [ ] Lockfile — commitowany, integrity checks
- [ ] Dependabot/Renovate — automatyczne PR z aktualizacjami
- [ ] Brak paczek z known CVE (sprawdz snyk.io)

#### Version Management
- [ ] Node.js — LTS (aktualnie 22.x)
- [ ] Framework'i — max 1 major behind
- [ ] Brak deprecated APIs
- [ ] Brak porzuconych paczek (last commit > 2 lata)

### A07: Identification and Authentication Failures

#### Authentication
- [ ] Brute force protection — max 5 prób / 15 min, potem lockout
- [ ] Account lockout — temporary (nie permanent)
- [ ] Session fixation — nowy session ID po login
- [ ] Logout — invalidacja session/token server-side
- [ ] Remember me — osobny long-lived token, revocable

#### Session Management
- [ ] Session timeout — 30 min inactivity (admin), 24h (user)
- [ ] Concurrent sessions — limit lub notification
- [ ] Session storage — server-side (Redis), nie client-side
- [ ] Cookie flags — httpOnly, secure, SameSite=Strict

#### Multi-Factor Authentication
- [ ] MFA dostepne dla admin roles
- [ ] TOTP (Google Authenticator) lub WebAuthn
- [ ] Recovery codes — jednorazowe, bezpiecznie przechowywane
- [ ] MFA bypass — brak (poza recovery codes)

### A08: Software and Data Integrity Failures

#### CI/CD Security
- [ ] Pipeline integrity — signed commits (opcjonalnie)
- [ ] Build reproducibility — lockfile, pinned versions
- [ ] Secrets w CI — GitHub Secrets, nie w kodzie
- [ ] Branch protection — PR required, min 1 review

#### Deserialization
- [ ] Zod validation na KAZDYM API endpoint (body, query, params)
- [ ] Brak `JSON.parse()` bez walidacji
- [ ] File uploads — type/size validation
- [ ] Webhook payloads — signature verification

### A09: Security Logging and Monitoring Failures

#### Audit Logging
- [ ] Login/logout — logowane z IP, user agent
- [ ] Failed login attempts — logowane, alertowane
- [ ] Permission changes — logowane (kto, co, kiedy)
- [ ] Data export — logowane
- [ ] CRUD na sensitive data — logowane
- [ ] Admin actions — logowane z pelnym kontekstem

#### Log Security
- [ ] Brak PII w logach (hasla, tokeny, PESEL, numer karty)
- [ ] Log injection prevention — sanitize user input
- [ ] Log retention — min 90 dni (RODO), max 1 rok
- [ ] Immutable logs — append-only, brak edycji/usuwania
- [ ] Centralized logging — ELK/Loki/CloudWatch

#### Monitoring & Alerting
- [ ] Failed auth attempts > threshold → alert
- [ ] Privilege escalation attempts → alert
- [ ] Anomalous traffic patterns → alert
- [ ] Database errors spike → alert
- [ ] Application errors (5xx) > threshold → alert

### A10: Server-Side Request Forgery (SSRF)

- [ ] URL whitelist — TYLKO dozwolone domeny
- [ ] Blokada private IP ranges: 127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
- [ ] Blokada metadata endpoints: 169.254.169.254
- [ ] DNS rebinding protection — resolve domain PRZED fetch
- [ ] Timeout na external requests (max 10s)
- [ ] Brak user-controlled URL w server-side fetch (lub strict validation)

---

## 2. RODO/GDPR — Pelna Checklist

### Art. 5: Zasady przetwarzania danych
- [ ] Zgodnosc z prawem — podstawa prawna przetwarzania (zgoda, umowa, obowiazek prawny)
- [ ] Celowość — jasno okreslony cel przetwarzania
- [ ] Minimalizacja — zbieramy TYLKO dane niezbedne do celu
- [ ] Prawidlowosc — mechanizm aktualizacji danych
- [ ] Ograniczenie przechowywania — retention policy, auto-delete
- [ ] Integralnosc i poufnosc — szyfrowanie, access control
- [ ] Rozliczalnosc — dokumentacja procesow, audit trail

### Art. 6: Podstawy prawne przetwarzania
- [ ] Zgoda — dobrowolna, swiadoma, jednoznaczna, mozliwa do wycofania
- [ ] Umowa — przetwarzanie niezbedne do wykonania umowy
- [ ] Obowiazek prawny — np. dane podatkowe
- [ ] Uzasadniony interes — balance test udokumentowany

### Art. 12-23: Prawa podmiotow danych
- [ ] Art. 15: Prawo dostepu — endpoint do pobrania swoich danych
- [ ] Art. 16: Prawo do sprostowania — edycja profilu
- [ ] Art. 17: Prawo do usunięcia — soft delete + hard delete (configurable retention)
- [ ] Art. 18: Prawo do ograniczenia — mozliwosc dezaktywacji konta
- [ ] Art. 20: Prawo do przenoszenia — export JSON/CSV
- [ ] Art. 21: Prawo do sprzeciwu — opt-out z marketingu
- [ ] Art. 22: Zautomatyzowane decyzje — informacja o automatycznych procesach

### Art. 25: Privacy by Design
- [ ] Domyslne ustawienia — minimum danych, maximum prywatnosci
- [ ] Pseudonimizacja — PII oddzielone od danych operacyjnych
- [ ] Data masking — logi, raporty bez PII
- [ ] Access control — principle of least privilege
- [ ] Separation of concerns — PII w osobnych tabelach

### Art. 28: Podmioty przetwarzajace
- [ ] Umowy powierzenia — z kazdym procesorem (hosting, email, analytics)
- [ ] Sub-procesory — lista, monitoring
- [ ] Lokalizacja danych — EU/EEA (Schrems II compliance)

### Art. 30: Rejestr czynnosci przetwarzania
- [ ] Nazwa procesu
- [ ] Cel przetwarzania
- [ ] Kategorie danych
- [ ] Kategorie podmiotow
- [ ] Okres przechowywania
- [ ] Srodki bezpieczenstwa

### Art. 32: Bezpieczenstwo przetwarzania
- [ ] Pseudonimizacja i szyfrowanie danych osobowych
- [ ] Zapewnienie ciąglej poufnosci, integralnosci, dostepnosci
- [ ] Zdolnosc szybkiego przywrocenia dostepnosci danych (backups)
- [ ] Regularne testowanie, mierzenie i ocenianie skutecznosci
- [ ] Szyfrowanie at-rest (DB, backups, files)
- [ ] Szyfrowanie in-transit (TLS 1.2+)
- [ ] Access control — RBAC z principle of least privilege
- [ ] Audit trail — logowanie dostepu do PII

### Art. 33-34: Powiadamianie o naruszeniach
- [ ] Procedura zglaszania naruszen (kto, jak, kiedy)
- [ ] 72h na zgloszenie do organu nadzorczego
- [ ] Powiadomienie osob ktorych dane dotycza (jesli wysokie ryzyko)
- [ ] Rejestr naruszen — data, opis, skutki, podjete dzialania

### Art. 35: Ocena skutkow (DPIA)
- [ ] DPIA wymagana — duza skala PII, profilowanie, monitoring
- [ ] Opis operacji przetwarzania
- [ ] Ocena koniecznosci i proporcjonalnosci
- [ ] Ocena ryzyka dla praw i wolnosci
- [ ] Srodki mitygacji

---

## 3. NIS2 Directive — Pelna Checklist

### Art. 21: Srodki zarzadzania ryzykiem

#### Polityki bezpieczenstwa
- [ ] Information security policy
- [ ] Acceptable use policy
- [ ] Incident response plan
- [ ] Business continuity plan
- [ ] Disaster recovery plan

#### Zarzadzanie incydentami
- [ ] Procedura wykrywania incydentow
- [ ] Procedura reagowania (contain, eradicate, recover)
- [ ] Procedura eskalacji
- [ ] Post-incident review
- [ ] Lessons learned documentation

#### Ciaglosc dzialania
- [ ] Backup strategy — 3-2-1 (3 kopie, 2 media, 1 offsite)
- [ ] Recovery Time Objective (RTO) — zdefiniowany
- [ ] Recovery Point Objective (RPO) — zdefiniowany
- [ ] Disaster recovery testing — min 1x/rok
- [ ] Failover procedures — udokumentowane

#### Bezpieczenstwo lancucha dostaw
- [ ] Audyt dostawcow (hosting, SaaS, libraries)
- [ ] SLA z dostawcami — security requirements
- [ ] Monitoring dependencies — CVE tracking
- [ ] Alternative suppliers — plan B

#### Zarzadzanie zasobami
- [ ] Inventory — lista systemow, baz danych, uslug
- [ ] Classification — krytycznosc zasobow
- [ ] Access management — kto ma dostep do czego
- [ ] Decommissioning — procedura wylaczania

### Art. 23: Raportowanie incydentow

#### Early Warning (24h)
- [ ] Mechanizm wykrycia — monitoring, alerty
- [ ] Kanaly komunikacji — email, telefon, portal
- [ ] Template zgloszenia — co, kiedy, kto, estymowany wplyw

#### Incident Notification (72h)
- [ ] Pelny opis incydentu
- [ ] Wplyw na uslugi
- [ ] Srodki zaradcze podjete
- [ ] Srodki zaradcze planowane

#### Final Report (1 miesiac)
- [ ] Root cause analysis
- [ ] Timeline incydentu
- [ ] Skutki koncowe
- [ ] Wnioski i rekomendacje
- [ ] Zmiany w procedurach

---

## 4. WCAG 2.1 AA — Pelna Checklist

### 1. Perceivable (Postrzegalnosc)

#### 1.1 Text Alternatives
- [ ] 1.1.1 Non-text Content — `alt` na `<img>`, aria-label na ikonach
- [ ] Dekoracyjne obrazy — `alt=""` lub `role="presentation"`
- [ ] Ikony interaktywne — `aria-label` z opisem akcji
- [ ] Captcha — alternatywa audio lub tekstowa

#### 1.2 Time-based Media
- [ ] 1.2.1 Audio/Video — transkrypcja lub napisy (jesli applicable)

#### 1.3 Adaptable
- [ ] 1.3.1 Info and Relationships — semantyczny HTML (`<nav>`, `<main>`, `<h1>`-`<h6>`)
- [ ] 1.3.2 Meaningful Sequence — logiczna kolejnosc w DOM
- [ ] 1.3.3 Sensory Characteristics — nie polegaj TYLKO na ksztalcie/kolorze/pozycji
- [ ] 1.3.4 Orientation — dziala w portrait i landscape
- [ ] 1.3.5 Input Purpose — `autocomplete` na formularzach

#### 1.4 Distinguishable
- [ ] 1.4.1 Use of Color — kolor nie jest jedynym nośnikiem informacji
- [ ] 1.4.3 Contrast (Minimum) — 4.5:1 tekst normalny, 3:1 tekst duzy (18px+)
- [ ] 1.4.4 Resize Text — czytelne przy 200% zoom
- [ ] 1.4.5 Images of Text — uzywaj prawdziwego tekstu, nie obrazow
- [ ] 1.4.10 Reflow — brak horizontal scroll przy 320px szerokosci
- [ ] 1.4.11 Non-text Contrast — 3:1 dla elementow UI (przyciski, inputy, ikony)
- [ ] 1.4.12 Text Spacing — czytelne przy zwiekszonych odstepach
- [ ] 1.4.13 Content on Hover/Focus — tooltip persistent, dismissable

### 2. Operable (Funkcjonalnosc)

#### 2.1 Keyboard Accessible
- [ ] 2.1.1 Keyboard — WSZYSTKO dostepne z klawiatury
- [ ] 2.1.2 No Keyboard Trap — focus nie utknął w elemencie
- [ ] 2.1.4 Character Key Shortcuts — mozliwosc wylaczenia/zmiany

#### 2.2 Enough Time
- [ ] 2.2.1 Timing Adjustable — mozliwosc przedluzenia timeout
- [ ] 2.2.2 Pause, Stop, Hide — kontrola auto-play, karuzeli

#### 2.3 Seizures and Physical Reactions
- [ ] 2.3.1 Three Flashes — brak migotania > 3Hz

#### 2.4 Navigable
- [ ] 2.4.1 Bypass Blocks — skip links (przeskocz do tresci)
- [ ] 2.4.2 Page Titled — kazda strona ma unikalny `<title>`
- [ ] 2.4.3 Focus Order — logiczna kolejnosc fokusa (tab)
- [ ] 2.4.4 Link Purpose — tekst linka opisuje cel (nie "kliknij tutaj")
- [ ] 2.4.5 Multiple Ways — min 2 sposoby nawigacji (menu + search)
- [ ] 2.4.6 Headings and Labels — opisowe naglowki i etykiety
- [ ] 2.4.7 Focus Visible — widoczny outline przy fokusie
- [ ] 2.4.11 Focus Not Obscured — sfocusowany element nie jest zasłoniety

#### 2.5 Input Modalities
- [ ] 2.5.1 Pointer Gestures — multi-point gestures maja alternatywe single-point
- [ ] 2.5.2 Pointer Cancellation — akcja na `mouseup`/`click`, nie `mousedown`
- [ ] 2.5.3 Label in Name — accessible name zawiera visible label
- [ ] 2.5.4 Motion Actuation — alternatywa dla motion-based input

### 3. Understandable (Zrozumialosc)

#### 3.1 Readable
- [ ] 3.1.1 Language of Page — `<html lang="pl">`
- [ ] 3.1.2 Language of Parts — `lang` na elementach w innym jezyku

#### 3.2 Predictable
- [ ] 3.2.1 On Focus — zmiana focus nie zmienia kontekstu
- [ ] 3.2.2 On Input — zmiana wartosci nie zmienia kontekstu (chyba ze user uprzedzony)
- [ ] 3.2.3 Consistent Navigation — ta sama kolejnosc elementow nawigacji
- [ ] 3.2.4 Consistent Identification — te same elementy UI konsystentnie oznaczone

#### 3.3 Input Assistance
- [ ] 3.3.1 Error Identification — bledy walidacji czytelne, zlokalizowane
- [ ] 3.3.2 Labels or Instructions — kazde pole formularza ma label
- [ ] 3.3.3 Error Suggestion — sugestia poprawy bledu
- [ ] 3.3.4 Error Prevention — potwierdzenie destrukcyjnych akcji
- [ ] 3.3.7 Redundant Entry — nie pytaj o te same dane ponownie

### 4. Robust (Solidnosc)

#### 4.1 Compatible
- [ ] 4.1.2 Name, Role, Value — custom components z ARIA roles
- [ ] 4.1.3 Status Messages — `role="status"`, `aria-live="polite"` dla toastow/alertow

---

## 5. Multi-tenant SaaS — Rozszerzone Patterns

### Data Isolation Patterns

#### Row-Level Security
```sql
-- PostgreSQL RLS (jesli uzywane)
CREATE POLICY tenant_isolation ON rooms
  USING (tenant_id = current_setting('app.tenant_id')::uuid);
```

#### Application-Level (Prisma)
```typescript
// KAZDY serwis MUSI filtrowac po tenantId
findAll(tenantId: string) {
  return this.prisma.room.findMany({
    where: { tenantId }
  });
}
```

#### Cache Isolation
```typescript
// Cache key MUSI zawierac tenantId
const key = `tenant:${tenantId}:rooms:list`;
```

### Common Attack Vectors

#### 1. Parameter Tampering
```
GET /api/rooms?tenantId=OTHER_TENANT_ID
→ Backend MUSI ignorowac tenantId z query, uzywac z JWT
```

#### 2. Mass Assignment
```json
POST /api/rooms
{ "name": "Room 1", "tenantId": "OTHER_TENANT_ID" }
→ Backend MUSI usuwac tenantId z body, uzywac z JWT
```

#### 3. GraphQL Introspection Abuse
```
→ Wylacz introspection w produkcji
```

#### 4. API Enumeration
```
GET /api/rooms/1  → 404 (sequential ID)
GET /api/rooms/2  → 200 (IDOR!)
→ Uzywaj UUID, nie sequential IDs
```

#### 5. Race Condition (Tenant Switching)
```
→ Transakcje DB dla operacji multi-step
→ Optimistic locking (version field)
```

---

## 6. Scoring Guide

### Severity Classification

| Severity | CVSS | Opis | Response Time |
|----------|------|------|--------------|
| KRYTYCZNE | 9.0-10.0 | Remote code execution, data breach, auth bypass | Natychmiast |
| POWAZNE | 7.0-8.9 | Privilege escalation, XSS, IDOR | Przed deploy |
| DROBNE | 4.0-6.9 | Information disclosure, missing headers | Nastepny sprint |
| INFO | 0.1-3.9 | Best practice, hardening | Backlog |

### Overall Assessment

| Ocena | Kryteria |
|-------|----------|
| **PASS** | 0 krytycznych, 0 powaznych, max 5 drobnych |
| **PASS WITH CONDITIONS** | 0 krytycznych, max 3 powazne (z planem naprawy) |
| **FAIL** | >= 1 krytyczny LUB > 3 powazne |
