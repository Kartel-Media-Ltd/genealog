# EdTech SaaS Benchmarks & Market Data

Dane referencyjne dla analizy biznesowej `/ultra-biz`.
Aktualizacja: Q1 2026.

---

## 1. Wielkosc rynku EdTech

### Globalny
- **2024:** ~$340B (HolonIQ)
- **2025:** ~$400B (prognoza)
- **2030:** ~$740B (CAGR ~12%)
- **Segment SaaS LMS/SMS:** ~$25B w 2025, ~$50B w 2030

### Europa
- **2024:** ~$80B
- **Polska:** ~$1.5B (dynamiczny wzrost, cyfryzacja edukacji)
- **CEE region:** ~$5B

### Segmenty
| Segment | Udzial | Wzrost (CAGR) |
|---------|--------|---------------|
| K-12 School Management | 22% | 15% |
| Language Learning | 18% | 13% |
| Tutoring Platforms | 12% | 17% |
| Corporate Training | 28% | 11% |
| Higher Education | 20% | 9% |

---

## 2. Konkurenci — School Management SaaS

### Globalni liderzy

| Produkt | Model cenowy | Cena startowa | Glowne funkcje | Slabe strony |
|---------|-------------|---------------|-----------------|--------------|
| **Teachable** | Per-student | $39/mo | Kursy online, payments | Brak zarzadzania szkola |
| **Thinkific** | Tiered | $49/mo | Kreator kursow, marketing | Brak schedule/rooms |
| **Canvas LMS** | Enterprise | Custom | Pelny LMS, integracje | Drogi, skomplikowany |
| **Moodle** | Open source | Free + hosting | LMS, pluginy | Przestarzaly UX |
| **Google Classroom** | Free | $0 | Proste zadania, integracja Google | Brak monetyzacji, brak zarzadzania |
| **ClassCard** | Per-student | $1/student/mo | Zarzadzanie szkola, platnosci | Maly rynek (Korea) |

### Polscy / CEE konkurenci

| Produkt | Model | Cena | Segment |
|---------|-------|------|---------|
| **Vulcan** | Licencja | Custom | Szkoly publiczne |
| **Librus** | SaaS | Custom | Szkoly publiczne/prywatne |
| **Classter** | SaaS | $3/student/mo | Miedzynarodowe szkoly |
| **SuperSaaS** | SaaS | od $8/mo | Rezerwacje/scheduling |
| **Spocket** | SaaS | Custom | Szkoly jezykowe |

### Kluczowe wnioski
- **Luka rynkowa:** Brak dobrego all-in-one SaaS dla szkol prywatnych/jezykowych w PL
- **Wiekszosci brakuje:** Multi-tenant, RBAC, RODO compliance, polski jezyk
- **Szansa:** Polskie/CEE szkoly potrzebuja nowoczesnego rozwiazania

---

## 3. Pricing Patterns w EdTech SaaS

### Modele cenowe

| Model | Opis | Kiedy stosowac | Przyklad |
|-------|------|----------------|----------|
| **Per-seat** | Za nauczyciela/admina | Male zespoly | $10-30/seat/mo |
| **Per-student** | Za ucznia | Duze szkoly | $1-5/student/mo |
| **Flat tier** | Stala cena za tier | Proste pricing | $49/$149/$349/mo |
| **Usage-based** | Za zuzycie (API, storage) | Enterprise | Custom |
| **Freemium** | Darmowy tier + paid | Akwizycja | Free + $49+ |
| **Hybrid** | Flat + per-student | Elastycznosc | $29 + $2/student |

### Typowe tiers w EdTech

| Tier | Cena (PLN/mies) | Target | Limity |
|------|-----------------|--------|--------|
| **Free** | 0 | Solo korepetytorzy | 1 lokalizacja, 3 nauczycieli, 30 uczniow |
| **Starter/Basic** | 49-99 | Male szkoly | 1 lokalizacja, 10 nauczycieli, 200 uczniow |
| **Professional/Pro** | 149-299 | Srednie szkoly | 3 lokalizacje, bez limitu nauczycieli, 1000 uczniow |
| **Enterprise** | 499+ / custom | Sieci szkol | Bez limitow, SLA, dedicated support, SSO |

### Rekomendowane strategie freemium

**Co dac za darmo (CORE):**
- Podstawowe zarzadzanie uczniami (CRUD)
- Prosty rozklad zajec
- Podstawowe raporty
- 1 lokalizacja, ograniczone konta

**Co monetyzowac (PREMIUM):**
- Wiele lokalizacji (multi-tenant)
- Zaawansowane raporty i analytics
- Integracje (Stripe, calendar, email)
- Custom branding
- API access
- Eksport/import danych (CSV, JSON)
- Feature flags
- Priority support

---

## 4. Benchmarki metryczne

### Metryki SaaS (EdTech specificzne)

| Metryka | Slaby | Dobry | Swietny |
|---------|-------|-------|---------|
| **Monthly Churn** | > 8% | 3-5% | < 3% |
| **Annual Churn** | > 50% | 20-30% | < 15% |
| **Trial-to-Paid Conversion** | < 2% | 3-5% | > 8% |
| **Free-to-Paid Conversion** | < 1% | 2-4% | > 5% |
| **ARPU (monthly)** | < $20 | $30-80 | > $100 |
| **LTV:CAC Ratio** | < 2:1 | 3:1 | > 5:1 |
| **Payback Period** | > 18 mies | 8-12 mies | < 6 mies |
| **Gross Margin** | < 60% | 70-80% | > 80% |
| **Net Revenue Retention** | < 90% | 100-110% | > 120% |
| **NPS** | < 20 | 30-50 | > 50 |

### Koszt akwizycji klienta (CAC)

| Kanal | CAC (PLN) | Czas do konwersji |
|-------|-----------|-------------------|
| Organic/SEO | 30-80 | 2-6 mies |
| Content Marketing | 50-120 | 1-4 mies |
| Social Media (organic) | 40-100 | 1-3 mies |
| Google Ads | 100-400 | Natychmiast |
| Facebook/Instagram Ads | 80-250 | 1-4 tyg |
| Cold Email | 60-200 | 2-8 tyg |
| Referral | 20-60 | 1-4 tyg |
| Events/Webinars | 150-500 | 1-3 mies |

---

## 5. Trendy EdTech 2025-2026

### Rosnace
1. **AI w edukacji** — personalizacja, auto-grading, chatboty
2. **Mobile-first** — aplikacje mobilne, PWA
3. **Micro-learning** — krotkie formaty, gamifikacja
4. **Data-driven decisions** — analytics, raporty real-time
5. **Compliance** — RODO, DORA, NIS2 (przewaga konkurencyjna)
6. **Parent engagement** — portale rodzicow, komunikacja
7. **Hybrid learning** — online + offline integration

### Malejace
1. Klasyczne LMS (ciężkie, skomplikowane)
2. One-size-fits-all platforms
3. Roczne licencje bez SaaS

### Technologiczne
1. **AI/ML** — recommendation engines, adaptive learning
2. **Real-time** — SSE/WebSocket, live notifications
3. **Headless CMS** — content management via API
4. **Low-code** — konfigurowalnosc bez kodowania
5. **Integration ecosystem** — Zapier, webhooks, open API

---

## 6. Sezonowsc w EdTech

| Miesiac | Aktywnosc | Sprzedaz | Uwagi |
|---------|-----------|----------|-------|
| Styczen | Srednia | Srednia | Poczatek semestru |
| Luty-Kwiecien | Wysoka | Niska | Peak usage, malo nowych |
| Maj-Czerwiec | Wysoka | Srednia | Koniec roku, renewals |
| Lipiec-Sierpien | NISKA | WYSOKA | Zakupy na nowy rok szkolny! |
| Wrzesien | PEAK | Srednia | Start roku, onboarding |
| Pazdziernik-Grudzien | Wysoka | Niska | Stabilne usage |

**Kluczowy wniosek:** Kampanie sprzedazowe celowac w **lipiec-sierpien** (decyzje zakupowe na nowy rok).

---

## 7. Revenue Scenarios Template

### Zalozenia bazowe
- Rynek docelowy: Polska, szkoly prywatne + jezykowe
- Estymowana liczba potencjalnych klientow: ~15,000 szkol
- TAM: 15,000 x 149 PLN/mo x 12 = ~27M PLN/rok
- SAM (realistycznie osiagalny): ~10% TAM = ~2.7M PLN/rok
- SOM (cel 2 lata): ~2% TAM = ~540K PLN/rok

### Scenariusz konserwatywny (Year 1)
- M1: 5 klientow (beta), ARPU 49 PLN
- M6: 30 klientow, ARPU 65 PLN
- M12: 80 klientow, ARPU 80 PLN
- **MRR M12:** ~6,400 PLN
- **ARR Year 1:** ~40,000 PLN

### Scenariusz umiarkowany (Year 1)
- M1: 10 klientow, ARPU 49 PLN
- M6: 60 klientow, ARPU 80 PLN
- M12: 180 klientow, ARPU 100 PLN
- **MRR M12:** ~18,000 PLN
- **ARR Year 1:** ~120,000 PLN

### Scenariusz optymistyczny (Year 1)
- M1: 20 klientow, ARPU 69 PLN
- M6: 120 klientow, ARPU 100 PLN
- M12: 350 klientow, ARPU 120 PLN
- **MRR M12:** ~42,000 PLN
- **ARR Year 1:** ~280,000 PLN

---

## Zrodla

- HolonIQ Global EdTech Report 2024/2025
- Statista EdTech Market Size
- SaaS Capital Benchmarks 2024
- OpenView SaaS Benchmarks 2024
- Emergen Research Education Technology Market
- GUS — dane o szkolach prywatnych w Polsce
- Deloitte EdTech Survey 2024
