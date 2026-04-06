# Mermaid Templates — Business Analysis

Gotowe szablony diagramow Mermaid do uzycia w raportach biznesowych `/ultra-biz`.
Wszystkie szablony sa kompatybilne z GitHub Markdown i VS Code preview.

---

## 1. SWOT Analysis — Quadrant Chart

```mermaid
quadrantChart
    title SWOT Analysis — [Nazwa Systemu]
    x-axis "Wewnetrzne" --> "Zewnetrzne"
    y-axis "Negatywne" --> "Pozytywne"
    quadrant-1 "SZANSE (Opportunities)"
    quadrant-2 "MOCNE STRONY (Strengths)"
    quadrant-3 "SLABE STRONY (Weaknesses)"
    quadrant-4 "ZAGROZENIA (Threats)"
    "Multi-tenant SaaS": [0.3, 0.8]
    "RBAC system": [0.2, 0.7]
    "Stripe integration": [0.4, 0.75]
    "Brak mobile app": [0.3, 0.25]
    "Wczesny etap": [0.2, 0.3]
    "Rynek EdTech rosnie": [0.7, 0.85]
    "AI w edukacji": [0.8, 0.9]
    "Silna konkurencja": [0.7, 0.2]
    "Regulacje RODO": [0.8, 0.3]
```

**Uzycie:** Zamien etykiety i wspolrzedne [x, y] na realne dane z analizy.
- x-axis: 0.0-0.5 = wewnetrzne, 0.5-1.0 = zewnetrzne
- y-axis: 0.0-0.5 = negatywne, 0.5-1.0 = pozytywne

---

## 2. Porter's Five Forces — Mindmap

```mermaid
mindmap
  root((Porter's Five Forces))
    Rivalrosc konkurentow
      Teachable
      Thinkific
      ClassCard
      Google Classroom
      Intensywnosc: WYSOKA
    Zagrozenie substytutow
      Arkusze Excel/Google
      Grupy WhatsApp
      Systemy ERP
      Intensywnosc: SREDNIA
    Sila przetargowa nabywcow
      Szkoly prywatne
      Szkoly jezykowe
      Korepetytorzy
      Wrazliwosc cenowa: WYSOKA
    Sila przetargowa dostawcow
      Cloud providers AWS/GCP
      Stripe payments
      Keycloak auth
      Zaleznosc: SREDNIA
    Zagrozenie nowych graczy
      Niskie bariery wejscia
      Open source alternatywy
      Big Tech EdTech
      Zagrozenie: WYSOKIE
```

---

## 3. Customer Journey — Journey Diagram

```mermaid
journey
    title Sciezka klienta — Od Discovery do Renewal
    section Discovery
      Szuka rozwiazania online: 3: Prospect
      Trafia na landing page: 4: Prospect
      Czyta case studies: 3: Prospect
    section Evaluation
      Zaklada darmowe konto: 5: Trial User
      Testuje podstawowe funkcje: 4: Trial User
      Importuje dane uczniow: 3: Trial User
      Porownuje z konkurencja: 3: Trial User
    section Purchase
      Wybiera plan Basic: 4: Customer
      Podaje dane platnosci: 3: Customer
      Otrzymuje onboarding email: 5: Customer
    section Onboarding
      Konfiguruje szkole: 4: Customer
      Dodaje nauczycieli: 4: Customer
      Tworzy plan zajec: 3: Customer
    section Usage
      Codzienne uzywanie: 5: Active User
      Generuje raporty: 4: Active User
      Uzywa premium features: 4: Active User
    section Renewal
      Otrzymuje reminder: 3: Customer
      Analizuje ROI: 4: Customer
      Odnawia subskrypcje: 5: Loyal Customer
      Upgrade do wyzszego planu: 4: Loyal Customer
```

---

## 4. Feature Categorization — Pie Chart

```mermaid
pie showData
    title Rozklad funkcji — Free vs Paid
    "CORE (Free)" : 35
    "BASIC (Paid)" : 25
    "PRO (Paid)" : 25
    "ENTERPRISE (Paid)" : 15
```

---

## 5. Revenue Projections — XY Chart

```mermaid
xychart-beta
    title "Projekcja przychodow MRR (12 miesiecy)"
    x-axis ["M1", "M2", "M3", "M4", "M5", "M6", "M7", "M8", "M9", "M10", "M11", "M12"]
    y-axis "MRR (PLN)" 0 --> 50000
    line "Konserwatywny" [500, 1000, 1800, 2800, 4000, 5500, 7200, 9000, 11000, 13500, 16000, 19000]
    line "Umiarkowany" [800, 1800, 3200, 5000, 7500, 10500, 14000, 18000, 22500, 27500, 33000, 39000]
    line "Optymistyczny" [1200, 3000, 5500, 9000, 13500, 19000, 25500, 33000, 41000, 50000, 50000, 50000]
```

---

## 6. GTM Timeline — Gantt Chart

```mermaid
gantt
    title Go-to-Market Timeline
    dateFormat YYYY-MM-DD
    axisFormat %b %Y

    section Pre-launch
    Landing page + waitlist     :done, 2026-03-01, 2026-03-15
    Beta program (10 szkol)     :active, 2026-03-15, 2026-04-30
    Feedback + iteracja         :2026-04-01, 2026-04-30

    section Launch
    Public launch               :milestone, 2026-05-01, 0d
    Content marketing           :2026-05-01, 2026-07-31
    SEO + Blog                  :2026-05-01, 2026-08-31
    Social media kampania       :2026-05-15, 2026-07-15

    section Growth
    Referral program            :2026-06-01, 2026-08-31
    Partnerstwa ze szkolami     :2026-07-01, 2026-09-30
    Premium features launch     :milestone, 2026-08-01, 0d
    Enterprise sales            :2026-08-01, 2026-12-31
```

---

## 7. Competitive Landscape — Quadrant Chart

```mermaid
quadrantChart
    title Competitive Landscape — Cena vs Funkcjonalnosc
    x-axis "Malo funkcji" --> "Duzo funkcji"
    y-axis "Niska cena" --> "Wysoka cena"
    quadrant-1 "Premium Niche"
    quadrant-2 "Enterprise Leaders"
    quadrant-3 "Budget Options"
    quadrant-4 "Mass Market"
    "Kartel School": [0.55, 0.35]
    "Teachable": [0.75, 0.7]
    "Thinkific": [0.7, 0.65]
    "ClassCard": [0.45, 0.4]
    "Google Classroom": [0.5, 0.1]
    "Moodle": [0.6, 0.15]
    "Canvas LMS": [0.8, 0.8]
```

---

## 8. Revenue Flow — Flowchart

```mermaid
flowchart LR
    subgraph Acquisition
        A[Landing Page] --> B[Free Trial]
        C[Referral] --> B
        D[Content/SEO] --> A
    end

    subgraph Conversion
        B --> E{Trial ends}
        E -->|Upgrade| F[Basic Plan]
        E -->|No action| G[Churn]
        F --> H[Pro Plan]
        H --> I[Enterprise]
    end

    subgraph Revenue
        F -->|49 PLN/mo| J[MRR]
        H -->|149 PLN/mo| J
        I -->|Custom| J
        J --> K[ARR]
    end

    style G fill:#ff6b6b,color:#fff
    style J fill:#51cf66,color:#fff
    style K fill:#339af0,color:#fff
```

---

## 9. Upsell Path — Flowchart TD

```mermaid
flowchart TD
    FREE["FREE<br/>3 nauczycieli, 30 uczniow<br/>Podstawowe funkcje"]
    BASIC["BASIC — 49 PLN/mies<br/>10 nauczycieli, 200 uczniow<br/>Raporty, eksport CSV"]
    PRO["PRO — 149 PLN/mies<br/>Bez limitu nauczycieli<br/>API, integracje, branding"]
    ENT["ENTERPRISE — Custom<br/>Multi-lokalizacja, SLA<br/>Dedicated support, SSO"]

    FREE -->|"Limit uczniow<br/>Potrzeba raportow"| BASIC
    BASIC -->|"Wiecej lokalizacji<br/>Custom branding"| PRO
    PRO -->|"SLA, compliance<br/>Dedicated CSM"| ENT

    style FREE fill:#e3f2fd,stroke:#1976d2
    style BASIC fill:#e8f5e9,stroke:#388e3c
    style PRO fill:#fff3e0,stroke:#f57c00
    style ENT fill:#fce4ec,stroke:#c62828
```

---

## 10. Business Model Canvas — Flowchart

```mermaid
flowchart TB
    subgraph KP["Kluczowi Partnerzy"]
        kp1["Cloud providers"]
        kp2["Stripe payments"]
        kp3["Szkoly partnerskie"]
    end

    subgraph KA["Kluczowe Aktywnosci"]
        ka1["Rozwoj platformy"]
        ka2["Customer support"]
        ka3["Marketing/Sales"]
    end

    subgraph KR["Kluczowe Zasoby"]
        kr1["Zespol dev"]
        kr2["Infrastruktura cloud"]
        kr3["Baza klientow"]
    end

    subgraph VP["Propozycja Wartosci"]
        vp1["All-in-one zarzadzanie szkola"]
        vp2["Multi-tenant, skalowalne"]
        vp3["RODO compliance"]
    end

    subgraph CR["Relacje z Klientami"]
        cr1["Self-service + onboarding"]
        cr2["Chat support"]
        cr3["Community forum"]
    end

    subgraph CH["Kanaly"]
        ch1["Website / SEO"]
        ch2["Social media"]
        ch3["Referral program"]
    end

    subgraph CS["Segmenty Klientow"]
        cs1["Szkoly jezykowe"]
        cs2["Korepetytorzy"]
        cs3["Szkoly prywatne"]
    end

    subgraph COST["Struktura Kosztow"]
        cost1["Hosting + infra"]
        cost2["Zespol (dev + support)"]
        cost3["Marketing"]
    end

    subgraph REV["Strumienie Przychodow"]
        rev1["Subskrypcje miesiczne"]
        rev2["Roczne plany (rabat)"]
        rev3["Enterprise custom"]
    end
```

---

## 11. System Modules — Pie Chart (CORE vs PREMIUM)

```mermaid
pie showData
    title Moduly systemu — kategoryzacja monetyzacji
    "CORE (free)" : 8
    "PREMIUM (paid)" : 12
    "NICE-TO-HAVE (future)" : 5
```

---

## Wskazowki uzycia

1. **ZAWSZE** dostosuj dane do realnych wynikow analizy — szablony sa startowe.
2. **quadrantChart** — wspolrzedne [x, y] w zakresie 0.0-1.0.
3. **xychart-beta** — wymaga Mermaid v10.6+, moze nie renderowac w starszych wersjach.
4. **gantt** — dateFormat i axisFormat musza byc spojne.
5. **journey** — skala 1-5 (1=frustracja, 5=zadowolenie).
6. **pie** — dodaj `showData` aby wyswietlic wartosci liczbowe.
7. **mindmap** — wciecia definiuja hierarchie (2 spacje per level).
8. **flowchart** — uzywaj `subgraph` do grupowania, `style` do kolorow.
