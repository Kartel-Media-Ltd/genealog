<?php
declare(strict_types=1);

/**
 * Atom: Spinner
 * Animowany wskaźnik ładowania — Font Awesome fa-spinner fa-spin.
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
    require_once __DIR__ . '/icon.php';

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
        <?php render_icon('spinner', 'solid', "fa-spin {$sizeClass}") ?>
        <?php if (!$hideLabel): ?>
            <span class="sr-only"><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>
    </span>
    <?php
}
