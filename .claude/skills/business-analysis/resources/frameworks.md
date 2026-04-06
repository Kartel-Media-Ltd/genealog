# Business Analysis Frameworks

Frameworki i narzedzia do analizy biznesowej w kontekscie EdTech SaaS.
Referencja dla `/ultra-biz` i skill `business-analysis`.

---

## 1. SWOT Analysis

### Definicja
Analiza czterech wymiarow organizacji/produktu:

| | Pozytywne | Negatywne |
|---|-----------|-----------|
| **Wewnetrzne** | **Strengths** — co robimy dobrze | **Weaknesses** — co wymaga poprawy |
| **Zewnetrzne** | **Opportunities** — szanse rynkowe | **Threats** — zagrozenia zewnetrzne |

### Jak wypelnic dla SaaS

**Strengths (Mocne strony):**
- Unikalne funkcje (np. multi-tenant, EAV, RBAC)
- Technologie (stack, architektura)
- Team expertise
- Istniejacy uzytkownicy / traction
- Compliance (RODO, NIS2)

**Weaknesses (Slabe strony):**
- Brakujace funkcje vs konkurencja
- Technical debt
- Team size / resources
- Brand recognition
- Customer support capacity

**Opportunities (Szanse):**
- Trendy rynkowe (AI, mobile, personalizacja)
- Niezaspokojone potrzeby klientow
- Regulacje sprzyjajace (np. cyfryzacja edukacji)
- Partnerstwa, integracje
- Ekspansja geograficzna

**Threats (Zagrozenia):**
- Konkurencja (duzi gracze, nowi wchodzacy)
- Zmiany regulacyjne
- Zmiany technologiczne
- Ryzyko cenowe (wrazliwosc klientow)
- Zależnosc od dostawcow (vendor lock-in)

### Diagram
Uzyj szablonu `quadrantChart` z `mermaid-templates.md` sekcja 1.

---

## 2. Business Model Canvas (BMC)

### 9 blokow

| Blok | Pytanie kluczowe | Przyklad EdTech |
|------|-------------------|-----------------|
| **Customer Segments** | Kto jest naszym klientem? | Szkoly jezykowe, korepetytorzy, szkoly prywatne |
| **Value Propositions** | Jaki problem rozwiazujemy? | All-in-one zarzadzanie szkola, automatyzacja |
| **Channels** | Jak docieramy do klientow? | SEO, content marketing, referral, social media |
| **Customer Relationships** | Jak utrzymujemy relacje? | Self-service, chat support, onboarding, community |
| **Revenue Streams** | Jak zarabiamy? | Subskrypcje (monthly/annual), enterprise custom |
| **Key Resources** | Czego potrzebujemy? | Zespol dev, infrastruktura cloud, baza klientow |
| **Key Activities** | Co musimy robic? | Rozwoj produktu, customer success, marketing |
| **Key Partnerships** | Z kim wspolpracujemy? | Cloud providers, payment (Stripe), szkoly partnerskie |
| **Cost Structure** | Jakie mamy koszty? | Hosting, zespol, marketing, compliance |

### Jak wypelnic
1. Zacznij od **Customer Segments** — kto placi?
2. Nastepnie **Value Propositions** — dlaczego placi?
3. Polacz segmenty z wartoscia przez **Channels** i **Relationships**
4. Zdefiniuj **Revenue Streams** — jak placi?
5. Okresl zasoby, aktywnosci, partnerstwa potrzebne do dostarczenia wartosci
6. Oblicz **Cost Structure** i zweryfikuj czy revenue > costs

### Diagram
Uzyj szablonu `flowchart TB` z `mermaid-templates.md` sekcja 10.

---

## 3. Value Proposition Canvas

### Profil klienta (Customer Profile)

**Jobs-to-be-done:**
- Functional: co klient chce zrobic? (np. zarzadzac rozkladem zajec)
- Social: jak chce byc postrzegany? (np. nowoczesna szkola)
- Emotional: jak chce sie czuc? (np. kontrola, spokoj)

**Pains (Bole):**
- Co frustruje klienta?
- Jakie ryzyka go dotycza?
- Co zajmuje za duzo czasu?
- Jakie bledy popelnia?

**Gains (Korzysci):**
- Czego oczekuje (minimum)?
- Co by go ucieszylo (ponad oczekiwania)?
- Co by go zaskoczylo pozytywnie?

### Mapa wartosci (Value Map)

**Products & Services:**
- Lista modulow/funkcji systemu

**Pain Relievers:**
- Jak system eliminuje bole klienta?
- Ktore funkcje rozwiazuja ktore problemy?

**Gain Creators:**
- Jak system tworzy wartosci dla klienta?
- Ktore funkcje generuja ktore korzysci?

### FIT
Dopasowanie = kazdy Pain ma Pain Reliever, kazdy Gain ma Gain Creator.

---

## 4. Porter's Five Forces

### 5 sil konkurencyjnych

#### 1. Rivalrosc miedzy istniejacymi konkurentami
- Ilu jest konkurentow?
- Jak zroznicowane sa produkty?
- Jakie sa bariery wyjscia?
- **W EdTech:** Duzo graczy, niskie zroznicowanie w podstawowych funkcjach

#### 2. Zagrozenie substytutow
- Jakie alternatywy ma klient?
- Jaki jest koszt zmiany?
- **W EdTech:** Excel/Google Sheets, grupy WhatsApp, papierowe dzienniki

#### 3. Zagrozenie nowych graczy
- Jakie sa bariery wejscia?
- Ekonomia skali?
- **W EdTech:** Niskie bariery (SaaS template'y), ale wysoki koszt compliance (RODO)

#### 4. Sila przetargowa dostawcow
- Ilu jest dostawcow?
- Jak unikalni sa?
- **W EdTech:** Cloud (AWS/GCP — wielu), Payments (Stripe — dominujacy), Auth (Keycloak — open source)

#### 5. Sila przetargowa nabywcow
- Ilu jest klientow?
- Jak wrazliwi sa na cene?
- **W EdTech:** Szkoly — wrazliwe cenowo, korepetytorzy — bardzo wrazliwi

### Diagram
Uzyj szablonu `mindmap` z `mermaid-templates.md` sekcja 2.

---

## 5. Blue Ocean Strategy — ERRC Grid

### Eliminate-Reduce-Raise-Create

| Akcja | Opis | Przyklad |
|-------|------|----------|
| **Eliminate** | Co mozna usunac, co branża uwaza za oczywiste? | Dlugi onboarding, skomplikowane UI |
| **Reduce** | Co mozna zredukowac ponizej standardu branzy? | Liczba klikow do wykonania zadania |
| **Raise** | Co mozna podniesc ponad standard? | Automatyzacja, compliance RODO |
| **Create** | Co branża nigdy nie oferowala? | AI asystent, parent portal, real-time analytics |

### Zastosowanie
1. Zidentyfikuj 6-8 czynnikow konkurencyjnych w EdTech
2. Porownaj obecne podejscie branzy vs Twoje
3. Dla kazdego czynnika okresl: Eliminate / Reduce / Raise / Create
4. Wynikowa strategia = nowa krzywa wartosci

---

## 6. Unit Economics — Formuly

### Kluczowe metryki SaaS

| Metryka | Formula | Benchmark EdTech |
|---------|---------|-----------------|
| **MRR** | Suma subskrypcji miesicznych | — |
| **ARR** | MRR x 12 | — |
| **ARPU** | MRR / liczba klientow | 30-150 PLN |
| **CAC** | Koszt marketingu / nowi klienci | 50-500 PLN |
| **LTV** | ARPU x (1 / churn rate) | CAC x 3+ |
| **LTV:CAC** | LTV / CAC | > 3:1 (zdrowy) |
| **Churn Rate** | Odejscia / total klienci (monthly) | < 5% (dobry) |
| **Net Revenue Retention** | (MRR + expansion - contraction - churn) / MRR | > 100% (swietny) |
| **Payback Period** | CAC / ARPU | < 12 mies (dobry) |
| **Gross Margin** | (Revenue - COGS) / Revenue | > 70% (SaaS) |

### Scenariusze przychodow

**Konserwatywny:**
- Wolny wzrost organiczny (5-10% m/m)
- Niski conversion rate (1-2%)
- Wyzszy churn (5-8%)

**Umiarkowany:**
- Sredni wzrost (10-20% m/m)
- Sredni conversion (3-5%)
- Umiarkowany churn (3-5%)

**Optymistyczny:**
- Szybki wzrost (20-30% m/m)
- Wysoki conversion (5-8%)
- Niski churn (< 3%)

### Diagram
Uzyj szablonu `xychart-beta` z `mermaid-templates.md` sekcja 5.

---

## 7. Go-to-Market (GTM) Strategy

### Kanaly akwizycji

| Kanal | Koszt | Czas do efektu | Skalowalnosc |
|-------|-------|-----------------|--------------|
| SEO / Content | Niski | 3-6 mies | Wysoka |
| Social Media | Niski-Sredni | 1-3 mies | Srednia |
| Referral Program | Niski | 2-4 mies | Wysoka |
| Paid Ads (Google) | Wysoki | Natychmiast | Wysoka |
| Cold Outreach | Sredni | 1-2 mies | Niska |
| Partnerships | Niski | 3-6 mies | Srednia |
| Events / Webinars | Sredni | 1-3 mies | Niska |

### Fazy GTM

1. **Pre-launch** (1-2 mies): Landing page, waitlist, beta program
2. **Soft Launch** (2-3 mies): Pierwsi klienci, feedback loop, iteracja
3. **Public Launch** (1 mies): PR, Product Hunt, social media push
4. **Growth** (ongoing): SEO, content, referral, partnerships
5. **Scale** (6+ mies): Paid acquisition, enterprise sales, expansion

### Diagram
Uzyj szablonu `gantt` z `mermaid-templates.md` sekcja 6.

---

## 8. Competitive Analysis Framework

### Wymiary porownania

| Wymiar | Co porownywac |
|--------|---------------|
| **Funkcje** | Lista features, feature parity |
| **Cena** | Tiers, pricing model, free tier |
| **UX** | Onboarding time, ease of use |
| **Integracje** | Jakie 3rd party tools |
| **Compliance** | RODO, NIS2, certyfikaty |
| **Support** | Kanaly, SLA, jezyki |
| **Mobile** | App, responsive, PWA |
| **Lokalizacja** | Jezyki, waluty, regiony |

### Scoring
Dla kazdego wymiaru: 1 (slaby) — 5 (doskonaly)
Porownaj radar chart: Twoj produkt vs top 3 konkurenci.

### Diagram
Uzyj szablonu `quadrantChart` z `mermaid-templates.md` sekcja 7.

---

## Referencje

- Business Model Canvas: Osterwalder & Pigneur (2010)
- Value Proposition Design: Osterwalder et al. (2014)
- Blue Ocean Strategy: Kim & Mauborgne (2005)
- Competitive Strategy: Porter (1980)
- SaaS Metrics 2.0: David Skok (forentrepreneurs.com)
- EdTech benchmarks: HolonIQ, Emergen Research, Statista
