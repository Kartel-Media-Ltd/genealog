# Tech Stack Checklist

Checklisty do code review dla każdej technologii w projekcie.

---

## Next.js 15 / App Router

### Async Request APIs (Breaking Change w 15+)
- [ ] `params` jest await'owane: `const { slug } = await params`
- [ ] `searchParams` jest await'owane: `const { q } = await searchParams`
- [ ] Dotyczy: `page.tsx`, `layout.tsx`, `route.ts`, `generateMetadata`, `generateStaticParams`
- [ ] Brak synchronicznego dostępu: ~~`params.slug`~~ → `(await params).slug`

### Server vs Client Components
- [ ] `"use client"` tylko na liściach drzewa (minimalizacja JS bundle)
- [ ] Server Components dla statycznych części UI
- [ ] Brak importu client-only hooks w Server Components
- [ ] Brak bezpośredniego użycia `window`, `document` w Server Components

### Routing i struktura
- [ ] `loading.tsx` dla Streaming UI
- [ ] `error.tsx` dla error boundaries
- [ ] `not-found.tsx` gdzie potrzeba
- [ ] Metadata (SEO) zdefiniowana statycznie lub dynamicznie

### Data Fetching
- [ ] Cache strategy zdefiniowana jawnie (Next.js 15+ domyślnie `no-store`)
- [ ] `revalidate` ustawione dla ISR gdzie sensowne
- [ ] Parallel fetching gdzie możliwe (`Promise.all`)
- [ ] `headers()`/`cookies()` tylko w dynamicznych komponentach (psują cache statycznych)

### Server Actions
- [ ] W oddzielnych plikach z `"use server"` (bezpieczeństwo)
- [ ] Walidacja inputów (Zod)
- [ ] Sprawdzenie uprawnień użytkownika
- [ ] Brak wycieków danych do klienta
- [ ] Przekazywane do Client Components jako props lub importowane z pliku `"use server"`

### API Routes
- [ ] Używane tylko gdy Server Actions nie wystarczają
- [ ] Proper error responses (status codes)
- [ ] Rate limiting dla publicznych endpointów
- [ ] `params` i `searchParams` await'owane w `route.ts`

### Hydration
- [ ] Brak Hydration Mismatch (daty, random, window)
- [ ] `suppressHydrationWarning` tylko gdy uzasadnione
- [ ] Dynamiczne importy z `ssr: false` dla client-only bibliotek

---

## React 19

### Nowe API
- [ ] `use()` zamiast `useEffect` + `useState` dla async data
- [ ] `useFormStatus()` dla form loading states
- [ ] `useOptimistic()` dla optimistic updates
- [ ] `useActionState()` dla Server Actions w formularzach

### Usunięte/zmienione wzorce
- [ ] Brak `forwardRef` — ref to zwykły prop w React 19
- [ ] `<Context>` zamiast `<Context.Provider>`
- [ ] Brak `useContext` gdzie można użyć `use(Context)`

### React Compiler (jeśli włączony)
- [ ] Brak ręcznych `useMemo` (compiler optymalizuje automatycznie)
- [ ] Brak ręcznych `useCallback` (compiler optymalizuje automatycznie)
- [ ] Brak `React.memo` wrapperów (compiler decyduje o memoizacji)

### Rendering
- [ ] Suspense boundaries dla async components
- [ ] Brak niepotrzebnych renderów
- [ ] Stan na odpowiednim poziomie (lifting vs colocation)
- [ ] Keys w listach są stabilne i unikalne

### Forms
- [ ] Native form actions gdzie możliwe
- [ ] `formAction` prop na `<button>`
- [ ] Progressive enhancement (działa bez JS)
- [ ] Server Actions przekazywane do Client Components jako props (lub importowane z pliku `"use server"`)

---

## Prisma ORM / PostgreSQL

### Schema (`libs/prisma/db/schema.prisma`)
- [ ] Typy kolumn odpowiadają danym
- [ ] Relacje prawidłowo zdefiniowane (@relation)
- [ ] Indeksy dla często wyszukiwanych kolumn (@@index)
- [ ] Migracje SQL w `libs/prisma/db/migrations/`
- [ ] `pnpm db:generate` po zmianach schematu

### Queries
- [ ] **`await` przy każdym zapytaniu** (częsty błąd — zwraca Promise zamiast danych)
- [ ] Brak N+1 (użyj `include` dla relacji)
- [ ] `select` tylko potrzebne kolumny
- [ ] `where` używa indeksowanych kolumn
- [ ] Używaj typed queries z wygenerowanego klienta

### Transakcje
- [ ] `prisma.$transaction()` dla operacji atomowych
- [ ] Krótkie transakcje (< 100ms)
- [ ] Brak długich transakcji (blokowanie)
- [ ] Interactive transactions dla złożonych operacji

### Typy
- [ ] Typy z `@prisma/client` używane w całej aplikacji
- [ ] Import z `@prisma` (alias)
- [ ] Brak `any` przy operacjach DB
- [ ] Używaj generated types: `Prisma.UserCreateInput`, etc.

---

## React Query (TanStack Query) / Data Fetching

### Kiedy używać React Query vs Server Components
- [ ] React Query dla danych dynamicznych/klienckich (polling, real-time, user-specific)
- [ ] Server Components dla danych przy nawigacji (SSR)
- [ ] Brak dublowania — użyj `React.cache()` dla deduplikacji metadata + page

### Konfiguracja (`lib/react-query/client.ts`)
- [ ] `queryKey` unikalne i opisowe (tablica)
- [ ] `staleTime` skonfigurowane (domyślnie 2-5 min)
- [ ] `gcTime` (garbage collection) ustawione sensownie
- [ ] `refetchOnWindowFocus: false` w większości przypadków

### Mutacje
- [ ] `useMutation` z `onSuccess` do invalidacji cache
- [ ] `queryClient.invalidateQueries()` po mutacjach
- [ ] Optimistic updates gdzie UX tego wymaga (`onMutate`, `onError`, `onSettled`)

### States
- [ ] Loading state obsłużony (`isLoading` / `isPending`)
- [ ] Error state obsłużony (`error`, `isError`)
- [ ] Empty state obsłużony
- [ ] **CRITICAL**: Unwrap response data - hooki zwracają `{ data: Entity }`, dostęp przez `response.data`

### Performance
- [ ] Deduplikacja działa (ten sam queryKey)
- [ ] `enabled: false` dla conditional fetching
- [ ] `select` dla transformacji danych (memoizowane)
- [ ] Prefetching dla przewidywalnych nawigacji

---

## Tailwind CSS 4

### Konfiguracja (v4 breaking change)
- [ ] Konfiguracja przez blok `@theme` w CSS (nie `tailwind.config.js`)
- [ ] Zmienne CSS definiowane w `@theme { }` lub `:root { }`
- [ ] Import Tailwind przez `@import "tailwindcss"` w CSS
- [ ] Brak starego `tailwind.config.js` (lub świadoma migracja)

### Klasy
- [ ] Uporządkowane (prettier-plugin-tailwindcss)
- [ ] Brak przestarzałych utility classes
- [ ] Brak `@apply` — kompozycja w React zamiast tego
- [ ] Unikanie arbitrary values (`w-[123px]`) — preferuj tokeny z design systemu

### Theming
- [ ] Zmienne CSS w bloku `@theme`
- [ ] Dark mode obsłużony (`dark:`)
- [ ] Spójne spacing, colors, typography
- [ ] `field-sizing: content` dla auto-growing textarea (zamiast JS hacków)

### Responsive
- [ ] Mobile-first approach
- [ ] Breakpointy używane konsekwentnie
- [ ] Testowane na różnych rozmiarach

### Komponenty
- [ ] Radix UI stylowany spójnie
- [ ] Hover/focus/active states zdefiniowane
- [ ] Transitions dla interakcji

---

## Radix UI

### Użycie
- [ ] Odpowiedni komponent (Dialog vs AlertDialog, etc.)
- [ ] Prawidłowa kompozycja (Root, Trigger, Content)
- [ ] Portal używany dla overlays

### Accessibility
- [ ] `aria-label` gdzie brak widocznego tekstu
- [ ] `aria-describedby` dla opisów
- [ ] Focus trap w modalach
- [ ] Escape zamyka overlay

### Styling
- [ ] `data-state` używane do stylowania stanów
- [ ] Animacje przez CSS/Tailwind
- [ ] Spójne z resztą UI

### Icons (Lucide)
- [ ] Spójny rozmiar (np. `size={20}`)
- [ ] `aria-hidden` lub `aria-label`
- [ ] Stroke width konsekwentny

---

## TypeScript

### Typy
- [ ] Brak `any` (użyj `unknown` jeśli trzeba)
- [ ] Interfejsy/typy eksportowane gdzie potrzeba
- [ ] Props komponentów typowane
- [ ] Return types dla funkcji (explicit lub inferred)

### Strict mode
- [ ] `strictNullChecks` respektowane
- [ ] Brak `!` (non-null assertion) bez uzasadnienia
- [ ] Optional chaining (`?.`) zamiast `&&`

### Imports
- [ ] Type imports (`import type { X }`)
- [ ] Brak circular dependencies
- [ ] Path aliases używane konsekwentnie (`@/`)

---

## Bezpieczeństwo

### Input
- [ ] Walidacja Zod na Server Actions
- [ ] Sanityzacja danych użytkownika
- [ ] Prepared statements dla SQL

### Auth/Authz
- [ ] Sprawdzenie sesji w Server Actions
- [ ] Sprawdzenie uprawnień przed operacją
- [ ] Brak danych innych użytkowników

### Secrets
- [ ] Brak hardcoded secrets
- [ ] Env variables przez `process.env`
- [ ] `.env` w `.gitignore`

### Output
- [ ] Brak XSS (React domyślnie escapuje)
- [ ] `dangerouslySetInnerHTML` tylko sanityzowane
- [ ] Error messages nie zdradzają internals

---

## Wydajność

### Bundle
- [ ] Dynamic imports dla dużych komponentów
- [ ] `next/dynamic` z `loading` component
- [ ] Tree shaking działa (named imports)

### Images
- [ ] `next/image` zamiast `<img>`
- [ ] Width/height zdefiniowane
- [ ] Lazy loading (domyślne)

### Lists
- [ ] Wirtualizacja dla długich list (>100 items)
- [ ] Pagination/infinite scroll
- [ ] Stable keys

### DB
- [ ] Indeksy dla WHERE/ORDER BY
- [ ] Limit dla list queries
- [ ] Brak N+1
- [ ] `db.batch()` dla wielu operacji (LibSQL)

---

## Dostępność (a11y)

### Interactive elements
- [ ] Touch targets min 44x44px
- [ ] Focus visible (outline)
- [ ] Keyboard navigation działa

### Semantics
- [ ] Headings w hierarchii (h1 → h2 → h3)
- [ ] Landmarks (`main`, `nav`, `aside`)
- [ ] Labels dla form inputs

### ARIA
- [ ] `aria-label` dla icon buttons
- [ ] `aria-live` dla dynamicznych treści
- [ ] `role` gdzie semantyczny HTML nie wystarczy

### Visual
- [ ] Kontrast WCAG 2.2 AA (4.5:1 text, 3:1 UI)
- [ ] Nie tylko kolor przekazuje informację
- [ ] Animacje respektują `prefers-reduced-motion`