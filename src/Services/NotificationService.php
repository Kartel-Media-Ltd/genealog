<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;

/**
 * Serwis powiadomień użytkowników.
 *
 * Typy powiadomień:
 *   - person_match    — globalne dopasowanie osoby przez fingerprint
 *   - invitation      — zaproszenie do drzewa
 *   - edit            — ktoś zaktualizował dane w współdzielonym drzewie
 *   - impersonation   — admin używał Twojego konta (RODO Art. 5(1)(a))
 *   - gedcom_import   — zakończony import GEDCOM
 */
class NotificationService
{
    public const TYPE_PERSON_MATCH  = 'person_match';
    public const TYPE_INVITATION    = 'invitation';
    public const TYPE_EDIT          = 'edit';
    public const TYPE_IMPERSONATION = 'impersonation';
    public const TYPE_GEDCOM_IMPORT = 'gedcom_import';

    public function __construct(
        private readonly NotificationRepository $repo,
    ) {}

    public function dispatch(
        string  $userId,
        string  $type,
        string  $title,
        ?string $body = null,
        ?string $link = null,
    ): void {
        $this->repo->create($this->generateUuid(), $userId, $type, $title, $body, $link);
    }

    /**
     * Powiadomienie po zakończeniu impersonacji (RODO compliance).
     */
    public function notifyImpersonationEnded(string $impersonatedUserId, string $adminName, \DateTimeImmutable $startedAt): void
    {
        $this->dispatch(
            userId: $impersonatedUserId,
            type:   self::TYPE_IMPERSONATION,
            title:  'Twoje konto było używane przez administratora',
            body:   'Administrator ' . $adminName . ' uzyskał tymczasowy dostęp do Twojego konta dnia '
                  . $startedAt->format('d.m.Y H:i') . ' w celach diagnostycznych. '
                  . 'Jeśli masz pytania, skontaktuj się z administratorem.',
            link:   '/profile',
        );
    }

    /**
     * Powiadomienie o globalnym dopasowaniu osoby (fingerprint match).
     */
    public function notifyPersonMatch(string $treeOwnerId, string $personName, string $treeId, string $personId): void
    {
        $this->dispatch(
            userId: $treeOwnerId,
            type:   self::TYPE_PERSON_MATCH,
            title:  'Znaleziono potencjalne powiązanie',
            body:   'Osoba "' . $personName . '" w Twoim drzewie ma podobny profil do osoby w innym drzewie. '
                  . 'Sprawdź szczegóły w widoku osoby.',
            link:   '/trees/' . $treeId . '/persons/' . $personId,
        );
    }

    /**
     * Powiadomienie o zaproszeniu do drzewa.
     */
    public function notifyInvitation(string $invitedUserId, string $inviterName, string $treeName, string $token): void
    {
        $this->dispatch(
            userId: $invitedUserId,
            type:   self::TYPE_INVITATION,
            title:  $inviterName . ' zaprasza Cię do współpracy',
            body:   'Zaproszenie do drzewa "' . $treeName . '". Kliknij aby je zaakceptować.',
            link:   '/invite/' . $token,
        );
    }

    /**
     * Powiadomienie o zakończonym imporcie GEDCOM.
     */
    public function notifyGedcomImport(string $userId, string $treeId, int $personsCount, int $relationshipsCount, int $errorsCount): void
    {
        $title = $errorsCount > 0
            ? 'Import GEDCOM zakończony z ostrzeżeniami'
            : 'Import GEDCOM zakończony pomyślnie';
        $body = sprintf(
            'Zaimportowano %d osób i %d relacji.%s',
            $personsCount,
            $relationshipsCount,
            $errorsCount > 0 ? sprintf(' Pominięto %d błędnych rekordów.', $errorsCount) : ''
        );
        $this->dispatch(
            userId: $userId,
            type:   self::TYPE_GEDCOM_IMPORT,
            title:  $title,
            body:   $body,
            link:   '/trees/' . $treeId . '/persons',
        );
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
