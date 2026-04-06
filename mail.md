# Mail do PTG — prośba o współpracę / dostęp do danych Geneteki

**Do:** zarzad@genealodzy.pl  
**Temat:** Propozycja współpracy — aplikacja genealogiczna open source + dostęp do danych Geneteki

---

Szanowni Państwo,

zwracam się z propozycją współpracy przy projekcie aplikacji webowej do budowania drzew genealogicznych, którą rozwijam jako narzędzie dostępne bezpłatnie dla użytkowników poszukujących swoich korzeni.

**O projekcie**

Projektuję aplikację „Genealog" — system umożliwiający tworzenie i współdzielenie drzew genealogicznych w przeglądarce, z naciskiem na użytkowników polskojęzycznych poszukujących przodków na terenie dawnych ziem polskich. Aplikacja będzie dostępna bezpłatnie, z kodem źródłowym opublikowanym na licencji open source.

Kluczową funkcją jest wyszukiwanie osób w zewnętrznych rejestrach historycznych w trakcie uzupełniania drzewa — tak aby użytkownik mógł jednym kliknięciem zweryfikować datę urodzenia czy parafię przodka bez opuszczania aplikacji.

**Prośba**

Geneteka jest bez wątpienia najcenniejszą polską bazą indeksowanych akt metrykalnych. Chciałbym zapytać o możliwość:

1. **Dostępu do eksportu danych CSV** — wiem, że PTG udostępnia dumpy bazy. Czy istnieje możliwość regularnego (np. miesięcznego) pobierania aktualnego eksportu w celu lokalnego indeksowania na potrzeby wyszukiwarki w aplikacji? Wyszukiwanie odbywałoby się po stronie naszego serwera — Geneteka nie byłaby obciążana żadnymi zapytaniami.

2. **Ewentualnego oficjalnego API** — czy PTG planuje lub udostępnia partnerom API do przeszukiwania bazy? Bylibyśmy gotowi do podpisania stosownego porozumienia o warunkach użytkowania.

3. **Informacji o preferowanej formie współpracy** — jeśli powyższe formy są niedostępne, chętnie poznam, jakie możliwości współpracy PTG rozważa z projektami niekomercyjnymi.

W zamian chętnie:
- oznaczymy Genetekę jako źródło danych w aplikacji z linkiem do Waszego serwisu,
- udostępnimy kod integracji jako open source z możliwością wykorzystania przez PTG,
- przekażemy PTG dostęp do aplikacji przed publicznym uruchomieniem.

**Kontekst techniczny**

Aplikacja jest pisana w PHP + MySQL, scraper w Pythonie. Dane z Geneteki byłyby przechowywane lokalnie wyłącznie w celu wyszukiwania — użytkownik widziałby wynik z informacją „źródło: Geneteka / PTG" i linkiem do oryginalnego rekordu na Waszej stronie. Nie kopiujemy treści — jedynie indeksujemy metadane (imię, nazwisko, rok, parafia) na potrzeby podpowiedzi.

Będę wdzięczny za odpowiedź w dowolnej formie — nawet krótką informację czy taka współpraca jest możliwa do rozważenia.

Z wyrazami szacunku dla Państwa pracy na rzecz polskiej genealogii,

[Imię Nazwisko]  
[e-mail]  
[opcjonalnie: link do repozytorium projektu gdy będzie dostępny]

---

*Projekt Genealog — aplikacja open source do budowania drzew genealogicznych*
