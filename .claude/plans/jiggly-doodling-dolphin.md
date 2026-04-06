# ADR: Next.js Proxy (BFF) — Analiza zasadnosci

## Context

User pyta czy wzorzec BFF (Backend For Frontend) — gdzie **caly ruch** z przegladarki idzie przez Next.js API Routes do NestJS backendu — ma sens w school-mono. Pierwotna motywacja: "zeby nie bylo widac linkow w przegladarce". System: max ~1000 jednoczesnych userow.

## Obecna architektura (zbadana w codebase)

```
Browser → fetch('/api/rooms') → Next.js API Route → fetch('http://localhost:3020/rooms') → NestJS
                                   ↑ wyciaga cookies (server-side)
                                   ↑ wstrzykuje Authorization: Bearer + X-Tenant-Id headers
                                   ↑ zwraca response z zachowaniem statusu
```

### Co proxy FAKTYCZNIE robi (nie tylko forwarding):
1. **Konwersja httpOnly cookies → headers**: access_token cookie → `Authorization: Bearer` header
2. **Wstrzykiwanie tenant ID**: selected_tenant_id cookie → `X-Tenant-Id` header
3. **Wczesna walidacja auth**: zwraca 401 jesli brak tokena (zanim trafi do backendu)
4. **Obsługa multipart**: file upload z zachowaniem content-type boundary
5. **Streaming**: export CSV/Excel z arrayBuffer passthrough
6. **Same-origin**: zero problemow z CORS z perspektywy przegladarki

### Backend juz obsluguje cookies:
```typescript
// JwtAuthGuard - DWIE sciezki auth:
const cookieToken = request.cookies?.access_token; // 1. Cookie direct
const authHeader = request.headers.authorization;   // 2. Bearer header (z proxy)
```

## Moja ocena (Solutions Architect)

### WERDYKT: ZACHOWAC PROXY — architektura jest POPRAWNA i UZASADNIONA

### Dlaczego proxy ma sens:

**1. XSS Protection (defense-in-depth)**
- JWT w httpOnly cookie — JS nie ma dostepu
- Z proxy: token nigdy nie pojawia sie jako header w DevTools Network tab
- Roznica: defense-in-depth, nie "security by obscurity"

**2. Tenant Isolation (NAJSILNIEJSZY argument)**
- `selected_tenant_id` jest w httpOnly cookie — klient NIE MOZE go sfalszowac
- Proxy wstrzykuje go server-side → backend dostaje zaufany X-Tenant-Id
- BEZ proxy: klient musialby wyslac X-Tenant-Id header → latwy do manipulacji (IDOR!)

**3. Backend URL ukryty**
- Przegladarka widzi tylko `/api/rooms` — nie wie o `:3020`
- Produkcja: backend moze byc na private network (VPC)

**4. Token Rotation przezroczysty**
- Keycloak refresh token w proxy/middleware
- Frontend nic nie wie o rotacji

**5. CSRF — same-origin**
- Zapytania ida na ten sam origin (port 3000) — brak cross-origin cookies

### Dlaczego argumenty PRZECIW nie sa krytyczne:

**1. Latency (+10-30ms produkcja)**
- React Query staleTime=2min → wiekszosc GET z cache
- SSE juz idzie direct do NestJS (bypass proxy)
- Dla 1000 userow: zerowy wplyw na UX

**2. Boilerplate (~80+ plikow proxy)**
- Realny koszt, ALE pliki sa proste (15-30 linii)
- Mozna zoptymalizowac generic utility (przyszlosc)

**3. "SameSite=Strict wystarczy"**
- Technicznie prawda dla XSS/CSRF
- ALE nie daje zaufanego tenant ID injection
- ALE nie ukrywa backend URL

### Porownanie tabelaryczne

| Aspekt | Proxy BFF (obecne) | Direct (FE → NestJS) |
|--------|-------------------|---------------------|
| XSS | httpOnly + invisible w DevTools | httpOnly (wystarczajace) |
| CSRF | Same-origin | SameSite=Lax (wystarczajace) |
| **Tenant isolation** | **Server-side (zaufane)** | **Client header (manipulowalne!)** |
| Backend exposure | Ukryty (VPC mozliwy) | Publiczny |
| Latency | +10-30ms | Baseline |
| Token rotation | Przezroczysty | Retry interceptor |
| Boilerplate | ~80 plikow | Zero |
| SSE/streaming | Juz bypass (direct) | N/A |

### Przyszle optymalizacje (bez zmiany arch):
1. Generic proxy utility — 30 linii → 5 per route
2. `next.config.js` rewrites dla prostych GET
3. Middleware-level auto-proxy dla standardowych CRUD

## Deliverable

Stworzyc `docs/architecture/ADR_NEXT_PROXY_BFF.md` z pelna analiza + update `docs/README.md`.

## Pliki do utworzenia/modyfikacji

1. **NOWY**: `docs/architecture/ADR_NEXT_PROXY_BFF.md`
2. **EDIT**: `docs/README.md` — link do ADR

## Weryfikacja
- Dokument zawiera konkretne argumenty (nie ogolniki)
- Porownanie tabelaryczne jest obiektywne
- Odzwierciedla rzeczywisty stan codebase (nie hipotetyczny)
