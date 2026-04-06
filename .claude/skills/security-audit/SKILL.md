---
name: security-audit
description: "Audyt bezpieczenstwa (OWASP Top 10, RODO/GDPR Art. 25/32, NIS2 Directive, WCAG 2.1 AA). Checklists, threat modeling (STRIDE), pentesting patterns dla multi-tenant SaaS. Uzywaj przy audycie bezpieczenstwa, compliance, security review, vulnerability assessment, penetration testing."
---

# Security Audit Skill

Skill do przeprowadzania audytow bezpieczenstwa w projekcie genealog (multi-tenant SaaS).

## Kiedy uzywac

- Audyt bezpieczenstwa nowego feature'a (architektura, endpointy, permissions)
- Compliance check (RODO/GDPR, NIS2, WCAG 2.1 AA)
- Threat modeling (STRIDE) dla krytycznych flow'ow
- Pentesting plan dla multi-tenant isolation
- Security review przed merge/deploy
- Wywoywany przez `/ultra-think` (Faza 5), `/ultra-audit` (Faza 3)

## Workflow

### Krok 1: Okresl zakres audytu

| Zakres | Opis | Checklists |
|--------|------|------------|
| **QUICK** | Pojedynczy feature/endpoint | OWASP A01-A03, Tenant Isolation |
| **STANDARD** | Modul/system (CRUD + logika) | OWASP Top 10, RODO Art. 32, Tenant |
| **FULL** | Caly backend lub frontend | OWASP + RODO + NIS2 + WCAG + STRIDE |

### Krok 2: Zbierz kontekst

1. **Architektura** — modele Prisma, endpointy, permissions, cache keys
2. **Kod** — controller, service, guards, middleware, frontend auth flow
3. **Konfiguracja** — CORS, headers, rate limiting, CSP
4. **Infrastruktura** — Redis, PostgreSQL, S3, Docker

### Krok 3: Uruchom checklists

Pelne checklists w `resources/checklists.md`. Ponizej — skrocone wersje:

---

## OWASP Top 10 (2021) — Quick Reference

### A01: Broken Access Control
- [ ] Tenant isolation — kazdy query filtruje po `tenantId`
- [ ] IDOR — UUID zamiast sequential ID, weryfikacja ownership
- [ ] RBAC — `@RequirePermissions()` na KAZDYM CRUD endpoint
- [ ] Vertical privilege escalation — guard sprawdza role priority
- [ ] Horizontal privilege escalation — user nie widzi danych innego usera
- [ ] CORS — whitelisted origins, nie `*`
- [ ] Directory traversal — walidacja sciezek plikow

### A02: Cryptographic Failures
- [ ] Hasla — bcrypt/argon2, min 12 chars, entropy check
- [ ] Tokeny — JWT z krotkim TTL, refresh token rotation
- [ ] Dane w DB — PII zaszyfrowane at-rest (RODO Art. 32)
- [ ] Transport — HTTPS only, HSTS header
- [ ] Secrets — env variables, nie w kodzie, nie w git

### A03: Injection
- [ ] SQL Injection — Prisma ORM (parametryzowane), NIGDY raw SQL bez $queryRaw
- [ ] XSS — React auto-escaping, CSP header, sanitize user input
- [ ] Command Injection — nigdy `exec()` z user input
- [ ] SSRF — walidacja URL, whitelist domen
- [ ] NoSQL Injection — walidacja Zod na wejsciu

### A04: Insecure Design
- [ ] Threat model (STRIDE) dla krytycznych flow'ow
- [ ] Rate limiting na auth endpoints
- [ ] Input validation (Zod) na KAZDYM endpoint
- [ ] Business logic abuse — limity, quotas

### A05: Security Misconfiguration
- [ ] Domyslne credentials zmienione
- [ ] Debug mode OFF w produkcji
- [ ] Error messages — nie ujawniaja stack trace
- [ ] Headers — X-Frame-Options, X-Content-Type-Options, CSP
- [ ] Swagger docs — wylaczone w produkcji lub za auth

### A06: Vulnerable Components
- [ ] `npm audit` — brak krytycznych CVE
- [ ] Zależności aktualne (major version < 2 behind)
- [ ] Brak paczek z known vulnerabilities

### A07: Identification and Authentication Failures
- [ ] Brute force protection — rate limiting, account lockout
- [ ] Session management — httpOnly cookies, secure flag, SameSite
- [ ] Password reset — time-limited token, one-use
- [ ] MFA — dostepne dla admin roles

### A08: Software and Data Integrity Failures
- [ ] CI/CD — pipeline integrity, signed commits
- [ ] Dependencies — lockfile, integrity checks
- [ ] Deserialization — Zod validation na wejsciu

### A09: Security Logging and Monitoring Failures
- [ ] Audit trail — logowanie login/logout/permission_change/data_export
- [ ] Alerty — failed login attempts, privilege escalation attempts
- [ ] Log retention — min 90 dni (RODO)
- [ ] Brak PII w logach (hasla, tokeny, numery PESEL)

### A10: Server-Side Request Forgery (SSRF)
- [ ] URL whitelist — walidacja domen
- [ ] Blokada internal IP ranges (127.0.0.1, 10.0.0.0/8, 169.254.0.0/16)
- [ ] Timeout na external requests

---

## RODO/GDPR Compliance — Quick Reference

### Art. 25: Privacy by Design
- [ ] Data minimization — zbieramy TYLKO potrzebne dane
- [ ] Purpose limitation — jasny cel przetwarzania
- [ ] Pseudonimizacja — PII oddzielone od danych operacyjnych
- [ ] Default privacy — domyslnie minimum danych

### Art. 32: Security of Processing
- [ ] Szyfrowanie — at-rest (DB, backups) + in-transit (TLS)
- [ ] Integralnosc — FK constraints, transakcje ACID
- [ ] Dostepnosc — backups, disaster recovery plan
- [ ] Testowanie — regularne testy bezpieczenstwa

### Art. 33-34: Breach Notification
- [ ] Incident response plan — kto, co, kiedy
- [ ] 72h notification — mechanizm alertow
- [ ] Data breach log — rejestr naruszen

### Prawa podmiotow danych
- [ ] Prawo dostepu (Art. 15) — endpoint export danych usera
- [ ] Prawo do usunecia (Art. 17) — soft delete + hard delete po okresie
- [ ] Prawo do przenoszenia (Art. 20) — eksport JSON/CSV
- [ ] Prawo do sprostowania (Art. 16) — edycja profilu

---

## NIS2 Directive — Quick Reference

### Risk Management (Art. 21)
- [ ] Risk assessment — identyfikacja zagrozen
- [ ] Incident handling — procedury reagowania
- [ ] Business continuity — backup, recovery plan
- [ ] Supply chain security — audyt dostawcow/dependencies

### Incident Reporting (Art. 23)
- [ ] Early warning — 24h od wykrycia
- [ ] Incident notification — 72h pelny raport
- [ ] Final report — 1 miesiac analiza przyczyn

### Technical Measures
- [ ] Multi-factor authentication
- [ ] Encryption — at-rest + in-transit
- [ ] Access control — principle of least privilege
- [ ] Network segmentation
- [ ] Vulnerability management — regularne skanowanie

---

## WCAG 2.1 AA — Quick Reference

### Perceivable
- [ ] Alt text na obrazach
- [ ] Kontrast — min 4.5:1 (tekst), 3:1 (duzy tekst)
- [ ] Responsywnosc — 320px-1920px
- [ ] Nie polegaj TYLKO na kolorze

### Operable
- [ ] Keyboard navigation — wszystko dostepne z klawiatury
- [ ] Focus visible — widoczny outline
- [ ] Skip links — przeskocz do tresci
- [ ] Brak flashy content (migotanie < 3Hz)

### Understandable
- [ ] Jezyk strony — atrybut `lang`
- [ ] Etykiety formularzy — `<label>` powiazane z `<input>`
- [ ] Bledy walidacji — jasne komunikaty, focus na bledzie
- [ ] Konsystencja nawigacji

### Robust
- [ ] Semantyczny HTML — `<nav>`, `<main>`, `<article>`
- [ ] ARIA roles — poprawne uzycie
- [ ] Testowanie z screen reader

---

## STRIDE Threat Model — Template

| Threat | Opis | Mitygacja |
|--------|------|-----------|
| **S**poofing | Podszywanie sie pod innego uzytkownika | JWT validation, session management |
| **T**ampering | Modyfikacja danych w tranzycie/storage | HTTPS, integrity checks, FK constraints |
| **R**epudiation | Zaprzeczanie wykonanym akcjom | Audit trail, immutable logs |
| **I**nformation Disclosure | Wyciek danych | Encryption, access control, minimize data |
| **D**enial of Service | Niedostepnosc uslugi | Rate limiting, CDN, auto-scaling |
| **E**levation of Privilege | Eskalacja uprawnien | RBAC, priority-based roles, tenant isolation |

### Jak wypelnic:
1. Zidentyfikuj AKTOROW (user roles, external systems)
2. Zidentyfikuj KOMPONENTY (endpoints, DB, cache, queues)
3. Dla kazdej pary Aktor-Komponent → przejdz STRIDE
4. Ocen ryzyko: HIGH/MEDIUM/LOW
5. Zaplanuj mitygacje

---

## Multi-tenant SaaS — Specific Patterns

### Tenant Isolation
- [ ] KAZDY query do DB zawiera `WHERE tenantId = ?`
- [ ] Prisma middleware/guard automatycznie dodaje tenant filter
- [ ] Cache keys zawieraja `tenant:{tenantId}:` prefix
- [ ] S3 paths zawieraja tenant ID: `tenants/{tenantId}/...`
- [ ] Redis keys izolowane per tenant
- [ ] Background jobs (BullMQ) — tenant context w job data

### IDOR Prevention
- [ ] UUID v4 zamiast sequential IDs
- [ ] Ownership check — user.tenantId === resource.tenantId
- [ ] Brak enumeracji — nie mozna zgadnac ID innego tenanta
- [ ] API nie ujawnia ID wewnetrznych (np. autoincrement)

### RBAC Bypass Prevention
- [ ] Role priority enforcement — nie mozna przypisac roli >= swojej
- [ ] Permission check na KAZDYM CRUD endpoint
- [ ] RoleScope validation — GLOBAL/COMPANY/TENANT/SELF
- [ ] Brak hardcoded role checks — uzywaj permissions, nie role names
- [ ] Admin endpoints oddzielone od user endpoints

### Cross-tenant Data Leaks
- [ ] Listy — ZAWSZE filtrowane po tenantId
- [ ] Relacje — FK do tenant-scoped tabel
- [ ] Search — wyniki TYLKO z wlasnego tenanta
- [ ] Export — dane TYLKO z wlasnego tenanta
- [ ] Logs — nie loguj danych z wielu tenantow w jednym wpisie

---

## Format raportu audytu

```markdown
## Security Audit: [nazwa systemu/feature]

### Zakres
- Typ: [QUICK/STANDARD/FULL]
- Moduly: [lista]
- Checklists: [OWASP/RODO/NIS2/WCAG/STRIDE]

### KRYTYCZNE [K]
K1. **[opis]** — [gdzie w kodzie]
    - Ryzyko: [co moze sie stac]
    - Naprawa: [jak naprawic]
    - Priorytet: NATYCHMIAST

### POWAZNE [P]
P1. **[opis]** — [gdzie]
    - Ryzyko: [opis]
    - Naprawa: [jak]
    - Priorytet: Przed deploy

### DROBNE [D]
D1. **[opis]** — [naprawa]

### POZYTYWNE [+]
+ [co jest dobrze zrobione]

### OCENA
- [ ] PASS — gotowe do produkcji
- [ ] PASS WITH CONDITIONS — wymaga poprawek [P] przed deploy
- [ ] FAIL — wymaga poprawek [K] NATYCHMIAST

### Metryki
- Checklists sprawdzonych: X/Y
- Krytyczne: X
- Powazne: X
- Drobne: X
- Pozytywne: X
```

## Dokumentacja referencyjna

- **Pelne checklists** -> `resources/checklists.md`
- OWASP Top 10 2021: https://owasp.org/Top10/
- RODO: Rozporzadzenie (UE) 2016/679
- NIS2: Dyrektywa (UE) 2022/2555
- WCAG 2.1: https://www.w3.org/WAI/WCAG21/quickref/
