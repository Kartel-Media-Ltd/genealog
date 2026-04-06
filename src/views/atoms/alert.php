<?php
/**
 * Atom: Alert
 * Komunikaty informacyjne z ikoną SVG inline.
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
    $configs = [
        'info' => [
            'wrapper' => 'border-[hsl(var(--info)/0.4)] bg-[hsl(var(--info)/0.08)] text-[hsl(217,60%,30%)]',
            'icon'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                               d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'role'    => 'status',
        ],
        'success' => [
            'wrapper' => 'border-[hsl(var(--success)/0.4)] bg-[hsl(var(--success)/0.08)] text-[hsl(142,60%,20%)]',
            'icon'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                               d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'role'    => 'status',
        ],
        'warning' => [
            'wrapper' => 'border-[hsl(var(--warning)/0.4)] bg-[hsl(var(--warning)/0.08)] text-[hsl(38,80%,25%)]',
            'icon'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                               d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732
                                  4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>',
            'role'    => 'alert',
        ],
        'error' => [
            'wrapper' => 'border-[hsl(var(--destructive)/0.4)] bg-[hsl(var(--destructive)/0.08)] text-[hsl(0,70%,30%)]',
            'icon'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                               d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
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
        <!-- Ikona SVG inline -->
        <svg
            class="mt-0.5 h-5 w-5 shrink-0"
            xmlns="http://www.w3.org/2000/svg"
            fill="none" viewBox="0 0 24 24" stroke="currentColor"
            aria-hidden="true"
        >
            <?= $cfg['icon'] ?>
        </svg>

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
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        <?php endif; ?>
    </div>
    <?php
}

/*
 * ─────────────────────────────────────────────────────────────
 * SNIPPET STATYCZNY — wszystkie warianty
 * ─────────────────────────────────────────────────────────────
 *
 * <!-- info -->
 * <div class="flash-message flex items-start gap-3 rounded-lg border p-4
 *             border-blue-200 bg-blue-50 text-blue-800" role="status">
 *   <svg class="mt-0.5 h-5 w-5 shrink-0" ...><!-- info circle icon --></svg>
 *   <p class="text-sm">Sesja wygaśnie za 5 minut.</p>
 * </div>
 *
 * <!-- success -->
 * <div class="flash-message flex items-start gap-3 rounded-lg border p-4
 *             border-green-200 bg-green-50 text-green-800" role="status">
 *   <svg class="mt-0.5 h-5 w-5 shrink-0" ...><!-- check circle icon --></svg>
 *   <p class="text-sm">Konto zostało utworzone pomyślnie.</p>
 * </div>
 *
 * <!-- warning -->
 * <div class="flash-message flex items-start gap-3 rounded-lg border p-4
 *             border-yellow-200 bg-yellow-50 text-yellow-800" role="alert">
 *   <svg class="mt-0.5 h-5 w-5 shrink-0" ...><!-- triangle icon --></svg>
 *   <p class="text-sm">Uzupełnij profil, aby korzystać ze wszystkich funkcji.</p>
 * </div>
 *
 * <!-- error -->
 * <div class="flash-message flex items-start gap-3 rounded-lg border p-4
 *             border-red-200 bg-red-50 text-red-800" role="alert">
 *   <svg class="mt-0.5 h-5 w-5 shrink-0" ...><!-- x circle icon --></svg>
 *   <p class="text-sm">Nieprawidłowy email lub hasło.</p>
 * </div>
 */
