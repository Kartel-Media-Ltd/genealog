<?php
declare(strict_types=1);

namespace App\Services\Discovery;

/**
 * Usługa liczenia fingerprint-ów i kodów Soundex dla osób.
 *
 * Fingerprint jest deterministycznym SHA-256 znormalizowanych pól
 * (imię, nazwisko, rok urodzenia, region) — używany do exact match
 * w `global_person_index` i lokalnych wyszukiwaniach.
 *
 * Soundex jest używany jako fallback do fuzzy matching gdy fingerprint
 * nie trafia (literówki, warianty imion). Natywny `soundex()` PHP nie
 * obsługuje polskich diakrytyk, więc ręcznie transliterujemy przez
 * `iconv ASCII//TRANSLIT` przed wywołaniem.
 */
final class FingerprintService
{
    /**
     * Oblicz fingerprint-hash dla osoby. Zwraca null gdy brak imienia lub nazwiska
     * (nie ma sensu indeksować osoby bez podstawowej tożsamości).
     */
    public function compute(
        ?string $firstName,
        ?string $lastName,
        ?int    $birthYear,
        ?string $birthPlace,
    ): ?string {
        $first = $this->normalize((string)$firstName);
        $last  = $this->normalize((string)$lastName);

        if ($first === '' || $last === '') {
            return null;
        }

        $region = $this->extractRegion($birthPlace) ?? '';
        $year   = $birthYear !== null ? (string)$birthYear : '';

        $payload = $first . '|' . $last . '|' . $year . '|' . $region;
        return hash('sha256', $payload);
    }

    /**
     * Oblicz kod soundex z obsługą polskich diakrytyk.
     * Łączy soundex imienia i nazwiska przez `-` (8 znaków łącznie przy obcinaniu).
     */
    public function computeSoundex(?string $firstName, ?string $lastName): ?string
    {
        $last = $this->transliterate((string)$lastName);
        if ($last === '') {
            return null;
        }
        $first = $this->transliterate((string)$firstName);

        $lastSdx  = soundex($last);
        $firstSdx = $first !== '' ? soundex($first) : '';

        // 8 chars total: 4 nazwisko + '-' + 3 imię (pierwsza litera-2-cyfry).
        // Soundex zawsze 4 znaki, więc 4 + 1 + 3 = 8 — substr było no-op (nit fix).
        return $lastSdx . ($firstSdx !== '' ? '-' . substr($firstSdx, 0, 3) : '');
    }

    /**
     * Wyciągnij region (województwo) z pola birth_place.
     * Prosta heurystyka: ostatnia część po przecinku lub całość gdy brak przecinka.
     * Np. "Warszawa, mazowieckie" → "mazowieckie"; "Kraków" → "Kraków" (fallback).
     */
    public function extractRegion(?string $birthPlace): ?string
    {
        if ($birthPlace === null) {
            return null;
        }
        $trimmed = trim($birthPlace);
        if ($trimmed === '') {
            return null;
        }
        if (!str_contains($trimmed, ',')) {
            return mb_strtolower($trimmed);
        }
        $parts = array_map('trim', explode(',', $trimmed));
        $last  = array_pop($parts);
        return $last !== '' ? mb_strtolower($last) : null;
    }

    /**
     * Czy osoba kwalifikuje się jako „historyczna" do globalnego indeksu?
     * Reguły RODO: is_living=0 AND birth_year < NOW()-100 lat.
     *
     * Bezpieczna domyślna: brak roku urodzenia → NIE indeksujemy.
     * User mógł zaznaczyć is_living=0 dla żyjącej osoby (anonimizacja, pomyłka,
     * stary rekord) i bez potwierdzenia rokiem nie mamy pewności że to faktycznie
     * osoba sprzed 100 lat. RODO Art. 25 — ostrożność > wygoda.
     */
    public function isHistorical(bool $isLiving, ?int $birthYear): bool
    {
        if ($isLiving) {
            return false;
        }
        if ($birthYear === null) {
            return false;
        }
        return $birthYear < ((int)date('Y')) - 100;
    }

    /**
     * Normalizacja pod fingerprint: lowercase, trim, bez diakrytyk, bez whitespace.
     */
    private function normalize(string $value): string
    {
        $transliterated = $this->transliterate($value);
        // Usuń cały whitespace środkowy oprócz pojedynczych spacji
        return preg_replace('/\s+/', ' ', trim($transliterated)) ?? '';
    }

    /**
     * Publiczny alias dla transliteracji — używany przez LocalTreeMatchSource
     * przed wywołaniem byte-based `levenshtein()` żeby polskie diakrytyki
     * nie były liczone jako 2 zmiany (UTF-8 to 2 bajty per diakrytyka).
     */
    public function asciiNormalize(string $value): string
    {
        return $this->transliterate($value);
    }

    /**
     * ASCII transliteration z obsługą polskich diakrytyk.
     * iconv zwraca false przy błędnym input — w takim wypadku fallback do lowercase.
     */
    private function transliterate(string $value): string
    {
        $value = mb_strtolower($value);
        // GNU iconv docs zalecają //IGNORE//TRANSLIT (IGNORE pierwsze, potem TRANSLIT)
        $ascii = @iconv('UTF-8', 'ASCII//IGNORE//TRANSLIT', $value);
        if ($ascii === false || $ascii === '') {
            // Fallback: ręczna mapa najczęstszych polskich liter
            $map = [
                'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
                'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            ];
            $ascii = strtr($value, $map);
        }
        return trim($ascii);
    }
}
