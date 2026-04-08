# Plan reagowania na incydenty bezpieczeństwa — Genealog

> Wymagany przez NIS2 Art. 21+23 i RODO Art. 33-34.
> Wersja: 1.0 | Data: 2026-04-07

## 1. Definicje

**Incydent bezpieczeństwa** — każde zdarzenie naruszające poufność, integralność
lub dostępność danych osobowych lub systemu Genealog. Przykłady:
- Wyciek danych osobowych (PII osób, drzewa innych userów)
- Nieautoryzowany dostęp do bazy danych
- Włamanie na konto admina
- Ransomware / utrata danych
- DDoS uniemożliwiający korzystanie z systemu
- Zhakowanie zewnętrznej zależności (`composer.json`)

## 2. Role i odpowiedzialności

| Rola | Osoba | Zakres |
|---|---|---|
| **Incident Commander** | Właściciel projektu | Decyzje, komunikacja zewnętrzna |
| **Tech Lead** | Główny developer | Analiza techniczna, mitygacja |
| **Compliance Officer** | (do ustalenia) | Powiadomienie UODO, dokumentacja |
| **Communications** | Właściciel projektu | Komunikacja z użytkownikami |

W projekcie one-person — wszystkie role pełni właściciel.

## 3. Timeline reakcji (NIS2 Art. 23)

| Czas | Akcja |
|---|---|
| **T+0** | Wykrycie incydentu (alert / zgłoszenie) |
| **T+1h** | Wstępna ocena: czy to prawdziwy incydent? skala? |
| **T+4h** | Mitygacja techniczna: izolacja, blokada, naprawa |
| **T+24h** | **Early warning** do CERT Polska (https://csirt.cert.pl/) |
| **T+72h** | **Notification do UODO** (jeśli dotyczy danych osobowych) — RODO Art. 33 |
| **T+72h** | **Komunikacja do użytkowników** (jeśli ich dotyczy) — RODO Art. 34 |
| **T+1 miesiąc** | **Final report** — analiza root cause + plan zapobiegawczy |

## 4. Procedura wykrycia

### Źródła alertów
1. **`error_log`** — wyjątki PHP, niepowodzenia DB
2. **`source_audit_log`** — anomalia w częstotliwości akcji
3. **`rate_limits`** — nieudane próby logowania, brute-force
4. **Zgłoszenia użytkowników** — email, formularz kontaktowy
5. **Monitoring zewnętrzny** — uptime, SSL expiry

### Pierwsze kroki
1. **NIE PANICZUJ** — spokojna ocena
2. Sprawdź `tail -f /var/log/php_errors.log`
3. Zaloguj się do `/admin/logs` i przejrzyj ostatnie wpisy
4. Sprawdź `source_audit_log` przez SQL: `SELECT * FROM source_audit_log ORDER BY created_at DESC LIMIT 100;`
5. Zrób snapshot bazy: `mysqldump > backup-incident-$(date +%s).sql`

## 5. Procedura mitygacji

### A) Wyciek danych
1. Zablokuj kompromisowane konto: `UPDATE users SET is_blocked=1 WHERE id='...'`
2. Inkrementuj `session_version` żeby unieważnić sesje: `UPDATE users SET session_version = session_version+1 WHERE id='...'`
3. Zmień hasło admina jeśli dotyczy
4. Sprawdź `source_audit_log` co user widział/eksportował

### B) Brute-force / DoS
1. Sprawdź `rate_limits` — które IP najwięcej prób
2. Block IP na poziomie nginx/firewall (jeśli powtarzające się ataki)
3. Tymczasowo zwiększ limity (`isLimited`) jeśli legitymny ruch

### C) Włamanie
1. Wyłącz aplikację (`maintenance mode`)
2. Snapshot DB i logów
3. Zmień wszystkie sekrety (`.env.local`)
4. Reset wszystkich sesji: `UPDATE users SET session_version = session_version+1`
5. Wymusić reset hasła wszystkim (komunikat email + tymczasowe blokady)

### D) Ransomware / utrata danych
1. Przywróć z najnowszego backup'u wg procedury w [`docs/operations/backup.md`](../operations/backup.md)
2. Sprawdź czy dane są spójne (kroki w Scenariuszu A, weryfikacja integralności)
3. Ostrzeż użytkowników o ewentualnej utracie najnowszych zmian (RPO 24h)
4. Rozpocznij dochodzenie (root cause analysis)

## 6. Kontakty

| Instytucja | Kontakt | Kiedy |
|---|---|---|
| **CERT Polska** | https://csirt.cert.pl/, csirt@cert.pl | NIS2 — early warning 24h |
| **UODO** | https://uodo.gov.pl/, kancelaria@uodo.gov.pl | RODO Art. 33 — 72h |
| **Hostingodawca** | (do uzupełnienia) | DDoS, awaria infrastruktury |

## 7. Komunikacja zewnętrzna

### Do użytkowników (Art. 34)
Jeśli incydent może powodować **wysokie ryzyko** dla praw użytkowników:
- Email do każdego dotkniętego usera (`notifications` table + `email_notifications=1`)
- Strona statusu / banner w aplikacji
- Informacja jasna i niespecjalistyczna

**Treść:**
- Co się stało
- Jakie dane mogły być naruszone
- Co zostało zrobione
- Co user może/powinien zrobić (np. zmienić hasło)
- Kontakt do dalszych pytań

### Do UODO (Art. 33)
Formularz: https://uodo.gov.pl/pl/p/zgloszenia
Dane wymagane:
- Charakter naruszenia
- Kategorie i przybliżona liczba osób
- Kategorie i przybliżona liczba rekordów
- Imię i kontakt do inspektora ochrony danych
- Możliwe konsekwencje
- Środki zaradcze

## 8. Post-mortem

Po zakończeniu incydentu (max miesiąc):
1. **Root cause analysis** — co dokładnie się stało
2. **Timeline** — kiedy co zostało zrobione
3. **Co zadziałało** — pozytywne aspekty reakcji
4. **Co nie zadziałało** — luki, opóźnienia
5. **Action items** — co zrobić żeby się nie powtórzyło
6. **Aktualizacja tego dokumentu** — wnioski

Zapisać w `docs/security/incidents/incident-{YYYY-MM-DD}.md`.

## 9. Testowanie planu

Co 6 miesięcy: przeprowadź **tabletop exercise** — symulacja incydentu, krok po kroku
przez tę procedurę. Cel: znaleźć luki, zaktualizować kontakty.

## 10. Compliance checklist

- [ ] Plan dostępny dla całego zespołu (one-person — właściciel zna)
- [ ] Kontakty UODO/CERT zweryfikowane
- [ ] Procedura mitygacji przetestowana
- [ ] Backup procedura działa
- [ ] Audit log retention działa (`bin/cleanup-audit-log.php` w cron)
- [ ] Wszystkie role obsadzone
- [ ] Dane do RODO Art. 33 zebrane: nazwa firmy, IDO, kontakt
