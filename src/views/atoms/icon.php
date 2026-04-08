<?php
declare(strict_types=1);

/**
 * Atom: Font Awesome Icon
 *
 * Renderuje ikonę Font Awesome jako <i class="fa-..."> z domyślnym aria-hidden.
 * Używaj zamiast inline SVG dla spójności i mniejszego HTML.
 *
 * @param string $name    Nazwa ikony bez prefiksu fa- (np. 'user', 'house', 'gear')
 * @param string $variant 'solid' | 'brands' | 'regular' (default: 'solid')
 * @param string $extra   Dodatkowe klasy CSS (np. 'h-5 w-5 text-primary')
 * @param string $title   Opcjonalny title (tooltip + aria-label dla ikon interaktywnych)
 *
 * @example
 *   <?php render_icon('user') ?>
 *   <?php render_icon('github', 'brands') ?>
 *   <?php render_icon('gear', 'solid', 'h-5 w-5 text-muted-foreground', 'Ustawienia') ?>
 *
 * WAŻNE a11y: Każde render_icon w <button> lub <a> MUSI mieć $title lub sr-only w parent.
 */
if (!function_exists('render_icon')) :
function render_icon(
    string $name,
    string $variant = 'solid',
    string $extra = '',
    string $title = '',
): void {
    // Strip 'fa-' prefix gdyby user go podał
    $clean = str_starts_with($name, 'fa-') ? substr($name, 3) : $name;

    // Whitelist wariantów (chroni przed dowolnym CSS injection)
    $variantClass = match ($variant) {
        'brands'  => 'fa-brands',
        'regular' => 'fa-regular',
        default   => 'fa-solid',
    };

    $extraAttr = $title !== ''
        ? sprintf(' title="%s" aria-label="%s"', htmlspecialchars($title, ENT_QUOTES), htmlspecialchars($title, ENT_QUOTES))
        : ' aria-hidden="true"';

    printf(
        '<i class="%s fa-%s%s"%s></i>',
        $variantClass,
        htmlspecialchars($clean, ENT_QUOTES),
        $extra !== '' ? ' ' . htmlspecialchars($extra, ENT_QUOTES) : '',
        $extraAttr
    );
}
endif;
