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

use App\Core\Session;

function render_flash_messages(): void {
    // ZAD-4.6 (D6): dostęp przez Session API, nie bezpośrednio przez $_SESSION.
    // Konsystencja z resztą projektu (controller, middleware używają Session::).

    // Obsługa jednego flash
    $flash = Session::get('flash');
    if (!empty($flash) && is_array($flash)) {
        Session::delete('flash');

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
    $flashes = Session::get('flashes');
    if (!empty($flashes) && is_array($flashes)) {
        Session::delete('flashes');

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
 * Helper — ustaw flash (do użycia w kontrolerach, rzadko — preferuj Response::withFlash).
 */
function flash_set(string $type, string $message, string $title = ''): void {
    Session::set('flash', compact('type', 'message', 'title'));
}

/**
 * Helper — dodaj do tablicy flash (wiele komunikatów naraz).
 */
function flash_add(string $type, string $message, string $title = ''): void {
    $flashes = Session::get('flashes', []);
    if (!is_array($flashes)) {
        $flashes = [];
    }
    $flashes[] = compact('type', 'message', 'title');
    Session::set('flashes', $flashes);
}
