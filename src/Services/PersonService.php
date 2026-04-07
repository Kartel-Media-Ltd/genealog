<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Repositories\TreeRepository;

class PersonService
{
    public function __construct(
        private readonly PersonRepository $personRepo,
        private readonly TreeRepository   $treeRepo,
    ) {}

    public function create(string $treeId, string $createdBy, array $input): Person
    {
        $data = $this->validateAndBuildData($input);
        $id   = $this->generateUuid();

        $this->personRepo->create($id, $treeId, $createdBy, $data);
        $this->treeRepo->touchUpdatedAt($treeId);

        $person = $this->personRepo->findById($id, $treeId);
        if ($person === null) {
            throw new \RuntimeException('Nie udało się utworzyć osoby.');
        }
        return $person;
    }

    public function update(string $personId, string $treeId, string $userId, array $input): Person
    {
        $person = $this->personRepo->findById($personId, $treeId);
        if ($person === null) {
            throw new \InvalidArgumentException('Osoba nie istnieje.');
        }

        $data = $this->validateAndBuildData($input);
        $this->personRepo->update($personId, $treeId, $data);
        $this->treeRepo->touchUpdatedAt($treeId);

        return $this->personRepo->findById($personId, $treeId)
            ?? throw new \RuntimeException('Błąd po aktualizacji osoby.');
    }

    public function delete(string $personId, string $treeId): void
    {
        $person = $this->personRepo->findById($personId, $treeId);
        if ($person === null) {
            throw new \InvalidArgumentException('Osoba nie istnieje.');
        }
        $this->personRepo->delete($personId, $treeId);
        $this->treeRepo->touchUpdatedAt($treeId);
    }

    /** @return Person[] */
    public function getForTree(string $treeId, string $sortBy = 'last_name'): array
    {
        return $this->personRepo->findByTree($treeId, $sortBy);
    }

    public function getForDetail(string $personId, string $treeId): Person
    {
        $person = $this->personRepo->findById($personId, $treeId);
        if ($person === null) {
            throw new \InvalidArgumentException('Osoba nie istnieje lub brak dostępu.');
        }
        return $person;
    }

    private function validateAndBuildData(array $input): array
    {
        $firstName = trim((string)($input['first_name'] ?? ''));
        $lastName  = trim((string)($input['last_name']  ?? ''));

        if ($firstName === '') {
            throw new \InvalidArgumentException('Imię jest wymagane.');
        }
        if ($lastName === '') {
            throw new \InvalidArgumentException('Nazwisko jest wymagane.');
        }
        if (mb_strlen($firstName) > 100) {
            throw new \InvalidArgumentException('Imię może mieć maksymalnie 100 znaków.');
        }
        if (mb_strlen($lastName) > 150) {
            throw new \InvalidArgumentException('Nazwisko może mieć maksymalnie 150 znaków.');
        }

        $gender = trim((string)($input['gender'] ?? 'unknown'));
        if (!in_array($gender, \App\Models\Person::GENDERS, true)) {
            $gender = 'unknown';
        }

        $isLiving = (bool)($input['is_living'] ?? false);

        $visibility = trim((string)($input['visibility'] ?? 'private'));
        if (!in_array($visibility, \App\Models\Person::VISIBILITIES, true)) {
            $visibility = 'private';
        }

        // RODO enforcement
        $this->enforceVisibilityRules($isLiving, $visibility);
        if ($isLiving) {
            $visibility = 'private';
        }

        $birthDate  = $this->sanitizeDate($input['birth_date']  ?? '');
        $deathDate  = $this->sanitizeDate($input['death_date']  ?? '');
        $birthPlace = mb_substr(trim((string)($input['birth_place'] ?? '')), 0, 255) ?: null;
        $deathPlace = mb_substr(trim((string)($input['death_place'] ?? '')), 0, 255) ?: null;
        $maidenName = mb_substr(trim((string)($input['maiden_name'] ?? '')), 0, 150) ?: null;
        $notes      = mb_substr(trim((string)($input['notes'] ?? '')), 0, 5000) ?: null;

        return [
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'maiden_name' => $maidenName,
            'birth_date'  => $birthDate,
            'birth_place' => $birthPlace,
            'death_date'  => $deathDate,
            'death_place' => $deathPlace,
            'gender'      => $gender,
            'is_living'   => $isLiving,
            'visibility'  => $visibility,
            'notes'       => $notes,
        ];
    }

    private function enforceVisibilityRules(bool $isLiving, string &$visibility): void
    {
        if ($isLiving) {
            $visibility = 'private';
        }
    }

    private function sanitizeDate(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        // Accept YYYY-MM-DD or YYYY
        if (preg_match('/^\d{4}(-\d{2}-\d{2})?$/', $v)) {
            return $v === substr($v, 0, 4) ? $v . '-01-01' : $v;
        }
        return null;
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
