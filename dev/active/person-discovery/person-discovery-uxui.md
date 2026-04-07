# UX/UI: Person Discovery

**Feature:** Person Discovery  
**Data:** 2026-04-07  
**Stack UI:** Alpine.js + Tailwind CSS v4 + shadcn/ui tokens

---

## Wireframes (ASCII)

### A) Formularz nowej osoby — autosuggest panel

```
┌── Formularz: Dodaj osobę ──────────────────────────────────────────┐
│                                                                      │
│  Imię:      [Jan___________________________]                        │
│  Nazwisko:  [Kowalski______________________]                        │
│  Data ur.:  [__________________________]                            │
│  Miejsce:   [__________________________]                            │
│                                                                      │
│  ┌── 💡 Możliwe dopasowania (3) ──────────────────────────────┐    │
│  │                                                              │    │
│  │  📁 Z Twoich drzew (1)                              [▼]    │    │
│  │  ┌──────────────────────────────────────────────────────┐  │    │
│  │  │  Jan Kowalski                           ⭐ 97%       │  │    │
│  │  │  ur. 1850 — zm. 1920 · drzewo "Babcia"              │  │    │
│  │  │  [Użyj tych danych]                                  │  │    │
│  │  └──────────────────────────────────────────────────────┘  │    │
│  │                                                              │    │
│  │  🌐 Z innych drzew (1)                              [▼]    │    │
│  │  ┌──────────────────────────────────────────────────────┐  │    │
│  │  │  Jan Kowalski                           ⭐ 81%       │  │    │
│  │  │  ur. 1850 · mazowieckie · Drzewo #A4F8              │  │    │
│  │  │  [Skontaktuj się z właścicielem]                     │  │    │
│  │  └──────────────────────────────────────────────────────┘  │    │
│  │                                                              │    │
│  │  🔍 FamilySearch (1)                                [▼]    │    │
│  │  ┌──────────────────────────────────────────────────────┐  │    │
│  │  │  Jan Kowalski                           ⭐ 74%       │  │    │
│  │  │  ur. 1850 · Warsaw, Poland                           │  │    │
│  │  │  [Importuj dane]                                     │  │    │
│  │  └──────────────────────────────────────────────────────┘  │    │
│  │                                                              │    │
│  └──────────────────────────────────────────────────────────────┘    │
│                                                                      │
│  [Anuluj]                                    [Dodaj osobę →]        │
└──────────────────────────────────────────────────────────────────────┘
```

Stany panelu:
- **Pusty** (< 2 znaki w obu polach): panel ukryty
- **Ładowanie** (po 400ms debounce): spinner + "Szukam..."
- **Wyniki**: sekcje accordion z licznikami
- **Brak wyników**: "Nie znaleziono dopasowań — nowa osoba zostanie dodana"
- **Błąd sieciowy**: toast "Nie udało się wyszukać. Kontynuuj bez sugestii."

---

### B) Panel sugestii w profilu osoby

```
┌── Profil: Jan Kowalski (1850–1920) ────────────────────────────────┐
│                                                                      │
│  [Dane osobowe] [Relacje] [Zdjęcia] [Historia]                     │
│                                                                      │
│  ┌── 💡 Możliwe powiązania (2) ───────────────────────────────┐   │
│  │                                                              │   │
│  │  ┌──────────────────────────────────────────────────────┐  │   │
│  │  │  ⭐ 95% zgodności                        LOCAL       │  │   │
│  │  │  Jan Kowalski · ur. 1850 · zm. 1920                  │  │   │
│  │  │  Z Twojego drzewa "Babcia — rodzina"                  │  │   │
│  │  │  → To ten sam Jan? Możesz połączyć dane.              │  │   │
│  │  │                                                        │  │   │
│  │  │  [✓ Akceptuj i połącz dane]  [✗ Odrzuć]              │  │   │
│  │  └──────────────────────────────────────────────────────┘  │   │
│  │                                                              │   │
│  │  ┌──────────────────────────────────────────────────────┐  │   │
│  │  │  ⭐ 78% zgodności                     CROSS-TREE     │  │   │
│  │  │  Jan Kowalski · ur. 1850 · mazowieckie               │  │   │
│  │  │  Z Drzewo #A4F8 (właściciel nieznany)                │  │   │
│  │  │  → Może to być ta sama osoba z innej rodziny.        │  │   │
│  │  │                                                        │  │   │
│  │  │  [Napisz do właściciela]  [✗ Odrzuć]                 │  │   │
│  │  └──────────────────────────────────────────────────────┘  │   │
│  │                                                              │   │
│  └──────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────┘
```

Stany akcji:
- **Akceptuj** → POST import → karta znika (x-show) + toast "Dane połączone"
- **Odrzuć** → POST reject → karta znika + toast "Sugestia odrzucona"
- **Błąd race condition** → toast "Ktoś inny już zaakceptował tę sugestię"

---

### C) Bell icon — powiadomienia w nagłówku

```
┌── Nagłówek aplikacji ──────────────────────────────────────────────┐
│                                                                      │
│  🌳 Genealog     Drzewa ▾    Szukaj    Pomoc        🔔 3  Jan K ▾  │
│                                              │                       │
│                                         ┌────┴───────────────────┐  │
│                                         │ Powiadomienia          │  │
│                                         │ ──────────────────────  │  │
│                                         │ • 💡 Znaleziono        │  │
│                                         │   powiązanie z Drzewo  │  │
│                                         │   #A4F8 dla Jana K.    │  │
│                                         │   przed 5 min          │  │
│                                         │                         │  │
│                                         │ • 👤 Maria N. zaprosiła│  │
│                                         │   Cię do drzewa        │  │
│                                         │   "Rodzina Nowak"      │  │
│                                         │   przed 2 godz.        │  │
│                                         │                         │  │
│                                         │ • 💡 Nowe dopasowanie  │  │
│                                         │   dla Anny K. (1820)   │  │
│                                         │   wczoraj              │  │
│                                         │                         │  │
│                                         │ [Wszystkie powiadomienia]│  │
│                                         └─────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

- Badge counter: czerwona kropka z cyfrą, znika gdy count=0
- Dropdown: 10 ostatnich, kliknięcie → mark read + redirect
- Polling: co 30s (fetch /api/notifications/count z Cache-Control: max-age=25)

---

### D) Ustawienia drzewa — discovery settings

```
┌── Ustawienia drzewa: "Babcia — rodzina" ───────────────────────────┐
│                                                                      │
│  Ogólne | Członkowie | Odkrywanie | Eksport                        │
│                                                                      │
│  ┌── Globalny indeks genealogiczny ───────────────────────────┐   │
│  │                                                              │   │
│  │  [ ] Włącz globalne odkrywanie dla tego drzewa             │   │
│  │                                                              │   │
│  │  ℹ️  Po włączeniu, historyczne osoby z tego drzewa          │   │
│  │     (nieżyjące, urodzone ponad 100 lat temu) będą           │   │
│  │     anonimowo indeksowane — bez adresu e-mail, zdjęć        │   │
│  │     ani prywatnych notatek.                                  │   │
│  │                                                              │   │
│  │  Inni użytkownicy zobaczą tylko: imię, nazwisko, rok        │   │
│  │     urodzenia i region. Twoje dane kontaktowe pozostają      │   │
│  │     ukryte do czasu, gdy sam zdecydujesz się odpowiedzieć.  │   │
│  │                                                              │   │
│  │  Podstawa prawna: RODO Art. 6(1)(f) — uzasadniony          │   │
│  │     interes badań genealogicznych.                           │   │
│  │                                                              │   │
│  │  [Zapisz ustawienia]  [Reindeksuj retroaktywnie]            │   │
│  └──────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Komponenty Alpine.js + PHP

### personDiscovery (Alpine — autosuggest)

```
Plik: src/views/pages/trees/persons/create.php (inline component)
State: firstName, lastName, results{local,crossTree,external}, loading, error, debounceTimer
Metody: init(), debouncedSearch(), fetchResults(), useData(result), clearResults()
Watch: $watch firstName, $watch lastName
Trigger: min 2 znaki w obu polach, debounce 400ms
```

### notificationBell (Alpine — nagłówek)

```
Plik: src/views/templates/AppLayout.php (inline component)
State: count, open, notifications[], loading
Metody: init(), fetchCount(), openDropdown(), markRead(id, link)
Polling: setInterval 30000ms
Cache: przeglądarka korzysta z Cache-Control: max-age=25 z serwera
```

### matchSuggestionCard (PHP component)

```
Plik: src/views/molecules/match-suggestion-card.php
Parametry: $suggestion (MatchResult), $personId, $csrfToken
Warianty: $suggestion['source_type'] = 'local' | 'cross_tree' | 'external'
Alpine: x-data="{status: 'pending'}", x-show="status === 'pending'"
Akcje: accept(), reject() — fetch POST + x-show toggle
```

### discoverySettingsForm (PHP)

```
Plik: src/views/pages/trees/settings/discovery.php
Parametry: $tree, $owner, $csrfToken
Elementy: checkbox is_indexed_globally, info-box RODO, button Zapisz, button Reindeksuj
```

---

## User flows

### Flow 1: Dodawanie nowej osoby z sugestią

```
1. Użytkownik otwiera /trees/{id}/persons/create
2. Wpisuje imię: "Jan" (< 2 znaki w nazwisku → brak fetch)
3. Wpisuje nazwisko: "Ko" → debounce start
4. Wpisuje "wal" → debounce reset
5. Wpisuje "ski" → debounce zakończony (400ms)
6. Fetch GET /api/discovery/search?firstName=Jan&lastName=Kowalski
7. Panel pojawia się pod formularzem z 3 sekcjami
8. Użytkownik klika "Użyj tych danych" przy wyniku z własnego drzewa
9. Pola formularza wypełniają się automatycznie
10. Użytkownik weryfikuje dane, klika "Dodaj osobę"
```

### Flow 2: Przeglądanie profilu z sugestiami

```
1. Użytkownik otwiera /trees/{id}/persons/{pid}
2. Na dole profilu widzi sekcję "Możliwe powiązania (2)"
3. Klika "Akceptuj i połącz dane" przy sugestii 95%
4. Fetch POST /api/discovery/match/{id}/import
5. Karta znika (x-show) → toast "Dane połączone pomyślnie"
6. Profil osoby odświeżony bez przeładowania strony
7. Klika "Odrzuć" przy drugiej sugestii 78%
8. Karta znika → toast "Sugestia odrzucona"
```

### Flow 3: Obsługa powiadomienia

```
1. Bell icon w nagłówku pokazuje badge "3"
2. Użytkownik klika bell
3. Dropdown pojawia się z 10 ostatnimi powiadomieniami
4. Klika "Znaleziono powiązanie dla Jana K."
5. POST /api/notifications/{id}/read
6. Redirect do /trees/{id}/persons/{pid}
7. Na profilu widzi panel "Możliwe powiązania" z nowym matchem
```

### Flow 4: Kontakt cross-tree

```
1. W panelu sugestii widzi match z "Drzewo #A4F8" (78%)
2. Klika "Napisz do właściciela"
3. Pojawia się modal z zanonimizowanym formularzem kontaktowym
4. Wpisuje wiadomość: "Czy Jan Kowalski to Pana przodek?"
5. Właściciel "Drzewo #A4F8" otrzymuje e-mail z wiadomością
6. Właściciel sam decyduje, czy odpowiedzieć i czy ujawnić dane
```

---

## Responsive design

| Breakpoint | Zachowanie panelu sugestii | Bell dropdown |
|------------|---------------------------|---------------|
| Mobile (<640px) | Sekcje jako accordiony, sticky footer z przyciskiem "Dodaj" | Fullscreen overlay zamiast dropdown |
| Tablet (640-1024px) | Panel pełny pod formularzem, max-h scroll | Dropdown 320px |
| Desktop (>1024px) | Panel pełny, 3 sekcje side-by-side opcjonalnie | Dropdown 380px |

---

## Accessibility

| Element | ARIA/semantyka |
|---------|---------------|
| Bell icon | `aria-label="Powiadomienia, 3 nowych"`, `aria-expanded` na dropdown |
| Badge counter | Numeryczny tekst widoczny, nie tylko kolor |
| Panele sugestii | `role="region"`, `aria-label="Możliwe dopasowania"` |
| Karty wyników | `role="article"`, keyboard navigable (tab/enter) |
| Loading state | `aria-live="polite"` na kontenerze wyników |
| Confidence badge | Widoczny tekst "97% zgodności", nie tylko kolor |
| Akceptuj/Odrzuć buttons | `aria-label="Akceptuj sugestię: Jan Kowalski 1850"` |

---

## Tokeny kolorów shadcn/ui dla confidence

| Confidence | Badge variant | Kolor |
|-----------|--------------|-------|
| >= 90% | `default` (ciemny) | `bg-primary text-primary-foreground` |
| 70-89% | `secondary` | `bg-secondary text-secondary-foreground` |
| 50-69% | `outline` | `border border-input` |
| < 50% | nie wyświetlać | — (filtrowane przez threshold) |

Ikona gwiazdki ⭐ tylko przy >= 90% — wizualne wyróżnienie najlepszych dopasowań.
