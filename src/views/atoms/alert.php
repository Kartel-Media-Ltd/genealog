<?php
declare(strict_types=1);

/**
 * Atom: Alert
 * Komunikaty informacyjne z ikoną Font Awesome.
 * Warianty: info | success | warning | error
 *
 * Użycie:
 *   <?php render_alert('Konto zostało utworzone.', 'success'); ?>
 *   <?php render_alert('Nieprawidłowe hasło.', 'error', 'Błąd logowania'); ?>
 *   <?php render_alert('Sesja wygaśnie za 5 minut.', 'warning'); ?>
 *
 * @param string $message  Treść komunikatu
 * @param string $variant  info | success | warning | error
 * @param string $title    Opcjonalny tytuł (pogrubiony)
 * @param bool   $dismiss  Pokazuje przycisk zamknięcia (Alpine.js)
 */
function render_alert(
    string $message,
    string $variant = 'info',
    string $title = '',
    bool $dismiss = false
): void {
    require_once __DIR__ . '/icon.php';

    $configs = [
        'info' => [
            'wrapper' => 'border-[hsl(var(--info)/0.4)] bg-[hsl(var(--info)/0.08)] text-[hsl(217,60%,30%)]',
            'icon'    => 'circle-info',
            'role'    => 'status',
        ],
        'success' => [
            'wrapper' => 'border-[hsl(var(--success)/0.4)] bg-[hsl(var(--success)/0.08)] text-[hsl(142,60%,20%)]',
            'icon'    => 'circle-check',
            'role'    => 'status',
        ],
        'warning' => [
            'wrapper' => 'border-[hsl(var(--warning)/0.4)] bg-[hsl(var(--warning)/0.08)] text-[hsl(38,80%,25%)]',
            'icon'    => 'triangle-exclamation',
            'role'    => 'alert',
        ],
        'error' => [
            'wrapper' => 'border-[hsl(var(--destructive)/0.4)] bg-[hsl(var(--destructive)/0.08)] text-[hsl(0,70%,30%)]',
            'icon'    => 'circle-exclamation',
            'role'    => 'alert',
        ],
    ];

    $cfg = $configs[$variant] ?? $configs['info'];
    ?>
    <div
        class="flash-message relative flex w-full items-start gap-3 rounded-lg border p-4
               <?= $cfg['wrapper'] ?>"
        role="<?= $cfg['role'] ?>"
        <?= $dismiss ? 'x-data="{ show: true }" x-show="show" x-transition' : '' ?>
    >
        <!-- Ikona Font Awesome -->
        <span class="mt-0.5 shrink-0">
            <?php render_icon($cfg['icon'], 'solid', 'h-5 w-5') ?>
        </span>

        <!-- Treść -->
        <div class="flex-1 min-w-0">
            <?php if ($title): ?>
                <p class="mb-1 font-semibold text-sm leading-none">
                    <?= htmlspecialchars($title) ?>
                </p>
            <?php endif; ?>
            <p class="text-sm leading-relaxed">
                <?= htmlspecialchars($message) ?>
            </p>
        </div>

        <!-- Przycisk zamknięcia (Alpine.js) -->
        <?php if ($dismiss): ?>
            <button
                type="button"
                @click="show = false"
                class="ml-auto -mr-1 -mt-1 flex h-8 w-8 items-center justify-center rounded-md
                       opacity-70 hover:opacity-100 focus-visible:outline-none
                       focus-visible:ring-2 focus-visible:ring-current"
                aria-label="Zamknij komunikat"
            >
                <?php render_icon('xmark', 'solid', 'h-4 w-4') ?>
            </button>
        <?php endif; ?>
    </div>
    <?php
}
