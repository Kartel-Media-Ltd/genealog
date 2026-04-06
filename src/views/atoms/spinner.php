<?php
/**
 * Atom: Spinner
 * Animowany wskaźnik ładowania — SVG inline, bez zależności.
 *
 * Użycie:
 *   <?php render_spinner(); ?>
 *   <?php render_spinner('lg'); ?>
 *   <?php render_spinner('sm', true); ?>  <!-- wewnątrz przycisku (aria-hidden) -->
 *
 * @param string $size       sm | md | lg
 * @param bool   $hideLabel  true = tylko wizualny (aria-hidden), np. w przycisku
 * @param string $label      Tekst dla screen-reader (gdy $hideLabel = false)
 */
function render_spinner(
    string $size = 'md',
    bool $hideLabel = false,
    string $label = 'Ładowanie...'
): void {
    $sizes = [
        'sm' => 'h-4 w-4',
        'md' => 'h-6 w-6',
        'lg' => 'h-8 w-8',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['md'];
    ?>
    <span
        role="<?= $hideLabel ? 'presentation' : 'status' ?>"
        <?= $hideLabel ? 'aria-hidden="true"' : '' ?>
        class="inline-flex items-center justify-center"
    >
        <svg
            class="<?= $sizeClass ?> animate-spin text-current"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle
                class="opacity-25"
                cx="12" cy="12" r="10"
                stroke="currentColor"
                stroke-width="4"
            ></circle>
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962
                   7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
        </svg>
        <?php if (!$hideLabel): ?>
            <span class="sr-only"><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>
    </span>
    <?php
}

/*
 * ─────────────────────────────────────────────────────────────
 * SNIPPET STATYCZNY (copy-paste gotowy)
 * ─────────────────────────────────────────────────────────────
 *
 * <!-- Spinner MD (standalone) -->
 * <span role="status" class="inline-flex items-center justify-center">
 *   <svg class="h-6 w-6 animate-spin text-current"
 *        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
 *        aria-hidden="true">
 *     <circle class="opacity-25" cx="12" cy="12" r="10"
 *             stroke="currentColor" stroke-width="4"></circle>
 *     <path class="opacity-75" fill="currentColor"
 *           d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962
 *              7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
 *   </svg>
 *   <span class="sr-only">Ładowanie...</span>
 * </span>
 *
 * Rozmiary:
 *   sm → h-4 w-4
 *   md → h-6 w-6
 *   lg → h-8 w-8
 */
