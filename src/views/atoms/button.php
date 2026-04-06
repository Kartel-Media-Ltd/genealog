<?php
/**
 * Atom: Button
 * Warianty: default, outline, ghost, destructive, loading
 *
 * Użycie:
 *   <?php render_button('Zaloguj się'); ?>
 *   <?php render_button('Usuń', 'destructive'); ?>
 *   <?php render_button('Zapisuję...', 'default', 'md', true); ?>
 *   <?php render_button('Anuluj', 'ghost', 'sm'); ?>
 *
 * @param string $label    Tekst przycisku
 * @param string $variant  default | outline | ghost | destructive | link
 * @param string $size     sm | md | lg
 * @param bool   $loading  Pokazuje spinner i blokuje kliknięcie
 * @param array  $attrs    Dodatkowe atrybuty HTML (type, name, value, form, id...)
 */
function render_button(
    string $label,
    string $variant = 'default',
    string $size = 'md',
    bool $loading = false,
    array $attrs = []
): void {
    // --- Klasy bazowe ---
    $base = implode(' ', [
        'inline-flex items-center justify-center gap-2',
        'whitespace-nowrap rounded-md font-medium',
        'transition-colors duration-200',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        'disabled:pointer-events-none disabled:opacity-50',
        'select-none',
    ]);

    // --- Wariant ---
    $variants = [
        'default'     => 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] hover:bg-[hsl(var(--primary)/0.9)] focus-visible:ring-[hsl(var(--ring))]',
        'outline'     => 'border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] focus-visible:ring-[hsl(var(--ring))]',
        'ghost'       => 'text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] focus-visible:ring-[hsl(var(--ring))]',
        'destructive' => 'bg-[hsl(var(--destructive))] text-[hsl(var(--destructive-foreground))] hover:bg-[hsl(var(--destructive)/0.9)] focus-visible:ring-[hsl(var(--destructive))]',
        'link'        => 'text-[hsl(var(--primary))] underline-offset-4 hover:underline focus-visible:ring-[hsl(var(--ring))]',
    ];

    // --- Rozmiar ---
    $sizes = [
        'sm' => 'h-9 px-3 text-sm',
        'md' => 'h-11 px-4 py-2 text-sm',   /* min 44px touch target (WCAG 2.2) */
        'lg' => 'h-12 px-8 text-base',
    ];

    $variantClass = $variants[$variant] ?? $variants['default'];
    $sizeClass    = $sizes[$size] ?? $sizes['md'];

    // --- Atrybuty HTML ---
    $defaultAttrs = [
        'type'     => 'button',
        'class'    => trim("$base $variantClass $sizeClass"),
    ];

    if ($loading) {
        $defaultAttrs['disabled']  = 'disabled';
        $defaultAttrs['aria-busy'] = 'true';
    }

    $mergedAttrs = array_merge($defaultAttrs, $attrs);
    $attrString  = '';
    foreach ($mergedAttrs as $key => $value) {
        if ($key === 'class' && isset($attrs['class'])) {
            // Merguj klasy zamiast nadpisywać
            $value = trim($defaultAttrs['class'] . ' ' . $attrs['class']);
        }
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    ?>
    <button<?= $attrString ?>>
        <?php if ($loading): ?>
            <?php render_spinner('sm', true) ?>
            <span><?= htmlspecialchars($label) ?></span>
        <?php else: ?>
            <?= htmlspecialchars($label) ?>
        <?php endif; ?>
    </button>
    <?php
}

/*
 * ─────────────────────────────────────────────────────────────
 * PRZYKŁADY (do usunięcia w produkcji — tylko podgląd)
 * ─────────────────────────────────────────────────────────────
 *
 * Variant: default
 * <button class="inline-flex items-center justify-center gap-2 whitespace-nowrap
 *   rounded-md font-medium transition-colors duration-200
 *   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2
 *   disabled:pointer-events-none disabled:opacity-50 select-none
 *   bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]
 *   hover:bg-[hsl(var(--primary)/0.9)]
 *   h-11 px-4 py-2 text-sm"
 *   type="button">
 *   Zaloguj się
 * </button>
 *
 * Variant: outline
 * <button class="... border border-[hsl(var(--input))] bg-[hsl(var(--background))]
 *   text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] h-11 px-4 py-2 text-sm"
 *   type="button">
 *   Anuluj
 * </button>
 *
 * Variant: destructive (submit z formem)
 * <button class="... bg-[hsl(var(--destructive))] text-[hsl(var(--destructive-foreground))]
 *   h-11 px-4 py-2 text-sm"
 *   type="submit" form="delete-form">
 *   Usuń konto
 * </button>
 *
 * State: loading (disabled + spinner)
 * <button class="... h-11 px-4 py-2 text-sm disabled:opacity-50"
 *   type="submit" disabled aria-busy="true">
 *   <svg class="h-4 w-4 animate-spin" ...></svg>
 *   <span>Zapisuję...</span>
 * </button>
 */
