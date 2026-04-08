<?php
declare(strict_types=1);

namespace App\Services;

class EmailService
{
    public function sendInvitation(
        string $toEmail,
        string $token,
        string $treeName,
        string $inviterName,
    ): bool {
        // ZAD-1.1 (K1): sanityzacja wartości trafiających do nagłówków SMTP.
        // `$toEmail` to dane userowe (invitations.invited_email) — podatne na header injection
        // `\r\n`. PHP mail() filtruje To/Subject od 5.2.14, ale stosujemy defense-in-depth.
        $toEmail      = $this->sanitizeHeader($toEmail);
        $treeName     = $this->sanitizeHeader($treeName);
        $inviterName  = $this->sanitizeHeader($inviterName);

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('EmailService::sendInvitation: invalid recipient email after sanitization');
            return false;
        }

        $appUrl   = defined('APP_URL')           ? APP_URL           : 'http://localhost';
        $from     = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@genealog.pl';
        $fromName = defined('MAIL_FROM_NAME')    ? MAIL_FROM_NAME    : 'Genealog';

        $link    = rtrim($appUrl, '/') . '/invite/' . $token;
        $subject = '=?UTF-8?B?' . base64_encode('Zaproszenie do drzewa genealogicznego: ' . $treeName) . '?=';

        $plain = "Cześć!\n\n"
            . $inviterName . " zaprasza Cię do współpracy przy drzewie genealogicznym \"{$treeName}\".\n\n"
            . "Kliknij link, aby zaakceptować zaproszenie (ważne 7 dni):\n"
            . $link . "\n\n"
            . "Jeśli nie spodziewasz się tego zaproszenia, zignoruj tę wiadomość.";

        $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:24px">'
            . '<h2 style="color:#1a1a1a">Zaproszenie do drzewa genealogicznego</h2>'
            . '<p><strong>' . htmlspecialchars($inviterName) . '</strong> zaprasza Cię do współpracy przy drzewie '
            . '<strong>' . htmlspecialchars($treeName) . '</strong>.</p>'
            . '<p style="margin:24px 0">'
            . '<a href="' . htmlspecialchars($link) . '" '
            . 'style="background:#18181b;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600">'
            . 'Dołącz do drzewa</a></p>'
            . '<p style="color:#666;font-size:13px">Link jest jednorazowy i wygasa po 7 dniach.<br>'
            . 'Jeśli nie spodziewasz się tego zaproszenia, zignoruj tę wiadomość.</p>'
            . '<p style="color:#999;font-size:12px">Link: ' . htmlspecialchars($link) . '</p>'
            . '</body></html>';

        // ZAD-4.3 (D3): kryptograficznie bezpieczny boundary (konsystentne z resztą projektu).
        $boundary = bin2hex(random_bytes(16));
        $headers  = implode("\r\n", [
            'From: ' . $fromName . ' <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Genealog/1.0',
        ]);

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $plain . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html . "\r\n\r\n"
            . "--{$boundary}--";

        return mail($toEmail, $subject, $body, $headers);
    }

    /**
     * Wysyła link do resetowania hasła. Token TTL 1h, jednorazowy.
     */
    public function sendPasswordReset(string $toEmail, string $token): bool
    {
        // ZAD-1.1 (K1): sanityzacja user-controlled headerów.
        $toEmail = $this->sanitizeHeader($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('EmailService::sendPasswordReset: invalid recipient email after sanitization');
            return false;
        }

        $appUrl   = defined('APP_URL')           ? APP_URL           : 'http://localhost';
        $from     = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@genealog.pl';
        $fromName = defined('MAIL_FROM_NAME')    ? MAIL_FROM_NAME    : 'Genealog';

        $link    = rtrim($appUrl, '/') . '/reset-password/' . $token;
        $subject = '=?UTF-8?B?' . base64_encode('Reset hasła — Genealog') . '?=';

        $plain = "Cześć!\n\n"
            . "Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta w Genealog.\n\n"
            . "Kliknij link, aby ustawić nowe hasło (ważny 1 godzinę):\n"
            . $link . "\n\n"
            . "Jeśli to nie Ty prosiłeś o reset, zignoruj tę wiadomość.";

        $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:24px">'
            . '<h2 style="color:#1a1a1a">Reset hasła</h2>'
            . '<p>Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta w Genealog.</p>'
            . '<p style="margin:24px 0">'
            . '<a href="' . htmlspecialchars($link) . '" '
            . 'style="background:#18181b;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600">'
            . 'Ustaw nowe hasło</a></p>'
            . '<p style="color:#666;font-size:13px">Link jest jednorazowy i wygasa po godzinie.<br>'
            . 'Jeśli to nie Ty prosiłeś o reset, zignoruj tę wiadomość — Twoje hasło nie zostało zmienione.</p>'
            . '<p style="color:#999;font-size:12px">Link: ' . htmlspecialchars($link) . '</p>'
            . '</body></html>';

        // ZAD-4.3 (D3): kryptograficznie bezpieczny boundary (konsystentne z resztą projektu).
        $boundary = bin2hex(random_bytes(16));
        $headers  = implode("\r\n", [
            'From: ' . $fromName . ' <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Genealog/1.0',
        ]);

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $plain . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html . "\r\n\r\n"
            . "--{$boundary}--";

        return mail($toEmail, $subject, $body, $headers);
    }

    /**
     * ZAD-1.1 (K1): usuwa `\r`, `\n`, `\0` z wartości trafiających do nagłówków SMTP.
     * Bez tego atakujący z kontrolowanym `name`/`invited_email` może wstrzyknąć
     * `Bcc:`/`Subject:` — klasyczny SMTP header injection.
     */
    private function sanitizeHeader(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}
