<?php
/**
 * Molecule: Flash Messages
 * Wyświetla komunikaty sesji PHP (success/error/warning/info) po przekierowaniu.
 *
 * --- Jak używać ---
 *
 * 1. W kontrolerze (po akcji) — ustaw flash:
 *    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Zalogowano pomyślnie.'];
 *    header('Location: /dashboard');
 *    exit;
 *
 * 2. W szablonie (template) — dołącz ten plik:
 *    <?php require SRC . '/views/molecules/flash-messages.php'; ?>
 *    <?php render_flash_messages(); ?>
 *
 * Typy: success | error | warning | info
 */

require_once __DIR__ . '/../atoms/alert.php';

function render_flash_messages(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Obsługa jednego flash
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        $type    = $flash['type']    ?? 'info';
        $message = $flash['message'] ?? '';
        $title   = $flash['title']   ?? '';

        // Mapowanie typów
        $variant = match ($type) {
            'success'           => 'success',
            'error', 'danger'   => 'error',
            'warning'           => 'warning',
            default             => 'info',
        };

        if ($message) {
            echo '<div class="mb-6">';
            render_alert($message, $variant, $title, dismiss: true);
            echo '</div>';
        }
    }

    // Obsługa wielu flash (tablica)
    if (!empty($_SESSION['flashes']) && is_array($_SESSION['flashes'])) {
        $flashes = $_SESSION['flashes'];
        unset($_SESSION['flashes']);

        echo '<div class="mb-6 flex flex-col gap-3">';
        foreach ($flashes as $flash) {
            $type    = $flash['type']    ?? 'info';
            $message = $flash['message'] ?? '';
            $title   = $flash['title']   ?? '';

            $variant = match ($type) {
                'success'           => 'success',
                'error', 'danger'   => 'error',
                'warning'           => 'warning',
                default             => 'info',
            };

            if ($message) {
                render_alert($message, $variant, $title, dismiss: true);
            }
        }
        echo '</div>';
    }
}

/**
 * Helper — ustaw flash (do użycia w kontrolerach)
 *
 * Przykład:
 *   flash_set('success', 'Zarejestrowano pomyślnie!');
 *   flash_set('error', 'Nieprawidłowe hasło.', 'Błąd logowania');
 */
function flash_set(string $type, string $message, string $title = ''): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = compact('type', 'message', 'title');
}

/**
 * Helper — dodaj do tablicy flash (wiele komunikatów naraz)
 */
function flash_add(string $type, string $message, string $title = ''): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flashes'][] = compact('type', 'message', 'title');
}
