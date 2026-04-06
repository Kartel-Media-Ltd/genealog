<?php
/**
 * Atom: Badge
 * Małe etykiety statusu / kategorii.
 *
 * Użycie:
 *   <?php render_badge('Aktywny'); ?>
 *   <?php render_badge('Admin', 'secondary'); ?>
 *   <?php render_badge('Błąd', 'destructive'); ?>
 *   <?php render_badge('Wersja beta', 'outline'); ?>
 *
 * @param string $text    Treść odznaki
 * @param string $variant default | secondary | destructive | outline | success | warning
 */
function render_badge(string $text, string $variant = 'default'): void {
    $base = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors select-none';

    $variants = [
        'default'     => 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] hover:bg-[hsl(var(--primary)/0.8)]',
        'secondary'   => 'bg-[hsl(var(--secondary))] text-[hsl(var(--secondary-foreground))] hover:bg-[hsl(var(--secondary)/0.8)]',
        'destructive' => 'bg-[hsl(var(--destructive))] text-[hsl(var(--destructive-foreground))] hover:bg-[hsl(var(--destructive)/0.8)]',
        'outline'     => 'border border-[hsl(var(--border))] text-[hsl(var(--foreground))]',
        'success'     => 'bg-[hsl(var(--success))] text-[hsl(var(--success-foreground))]',
        'warning'     => 'bg-[hsl(var(--warning))] text-[hsl(var(--warning-foreground))]',
    ];

    $variantClass = $variants[$variant] ?? $variants['default'];
    ?>
    <span class="<?= "$base $variantClass" ?>">
        <?= htmlspecialchars($text) ?>
    </span>
    <?php
}

/*
 * ─────────────────────────────────────────────────────────────
 * SNIPPET STATYCZNY
 * ─────────────────────────────────────────────────────────────
 *
 * <!-- Default (niebieski) -->
 * <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
 *              bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]">
 *   Aktywny
 * </span>
 *
 * <!-- Secondary (szary) -->
 * <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
 *              bg-[hsl(var(--secondary))] text-[hsl(var(--secondary-foreground))]">
 *   Admin
 * </span>
 *
 * <!-- Destructive (czerwony) -->
 * <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
 *              bg-[hsl(var(--destructive))] text-[hsl(var(--destructive-foreground))]">
 *   Błąd
 * </span>
 *
 * <!-- Outline (obramowany) -->
 * <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
 *              border border-[hsl(var(--border))] text-[hsl(var(--foreground))]">
 *   Wersja beta
 * </span>
 *
 * <!-- Success (zielony) -->
 * <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
 *              bg-[hsl(var(--success))] text-[hsl(var(--success-foreground))]">
 *   Ukończony
 * </span>
 */
