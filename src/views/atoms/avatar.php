<?php
/**
 * Atom: Avatar
 * Zdjęcie użytkownika z fallbackiem na inicjały (gdy brak zdjęcia lub błąd ładowania).
 *
 * Użycie:
 *   <?php render_avatar('Jan Kowalski'); ?>
 *   <?php render_avatar('Jan Kowalski', '/uploads/jan.jpg', 'lg'); ?>
 *   <?php render_avatar('A', '', 'sm'); ?>
 *
 * @param string $name  Pełna nazwa (do inicjałów i alt)
 * @param string $src   URL zdjęcia (pusty = tylko inicjały)
 * @param string $size  sm | md | lg | xl
 */
function render_avatar(
    string $name,
    string $src = '',
    string $size = 'md'
): void {
    $sizes = [
        'sm' => ['wrapper' => 'h-8 w-8',   'text' => 'text-xs'],
        'md' => ['wrapper' => 'h-10 w-10',  'text' => 'text-sm'],
        'lg' => ['wrapper' => 'h-12 w-12',  'text' => 'text-base'],
        'xl' => ['wrapper' => 'h-16 w-16',  'text' => 'text-xl'],
    ];

    $sizeConfig = $sizes[$size] ?? $sizes['md'];

    // Generuj inicjały z max 2 liter
    $initials = avatar_initials($name);

    // Kolor tła z inicjałów (deterministyczny)
    $bgColor = avatar_bg_color($name);
    ?>
    <span
        class="relative inline-flex <?= $sizeConfig['wrapper'] ?> shrink-0
               items-center justify-center overflow-hidden rounded-full"
        role="img"
        aria-label="<?= htmlspecialchars($name) ?>"
        title="<?= htmlspecialchars($name) ?>"
    >
        <?php if ($src): ?>
            <img
                src="<?= htmlspecialchars($src) ?>"
                alt="<?= htmlspecialchars($name) ?>"
                class="h-full w-full object-cover"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
        <?php endif; ?>

        <!-- Fallback inicjały — widoczny gdy brak zdjęcia lub błąd -->
        <span
            class="<?= $sizeConfig['wrapper'] ?> <?= $sizeConfig['text'] ?>
                   <?= $src ? 'hidden' : 'flex' ?>
                   items-center justify-center rounded-full font-semibold text-white"
            style="background-color: <?= $bgColor ?>; <?= $src ? 'display:none' : '' ?>"
            aria-hidden="true"
        >
            <?= htmlspecialchars($initials) ?>
        </span>
    </span>
    <?php
}

/**
 * Generuje inicjały z nazwy (max 2 litery).
 * "Jan Kowalski" → "JK"
 * "Maria"        → "M"
 */
function avatar_initials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    if (count($words) >= 2) {
        return mb_strtoupper(
            mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1)
        );
    }
    return mb_strtoupper(mb_substr($name, 0, 2));
}

/**
 * Deterministyczny kolor tła z nazwy (spójny dla tego samego użytkownika).
 */
function avatar_bg_color(string $name): string {
    $colors = [
        '#4f46e5', // indigo
        '#0891b2', // cyan
        '#059669', // emerald
        '#d97706', // amber
        '#dc2626', // red
        '#7c3aed', // violet
        '#db2777', // pink
        '#0369a1', // sky
    ];

    $hash = 0;
    for ($i = 0; $i < mb_strlen($name); $i++) {
        $hash = (($hash << 5) - $hash) + mb_ord(mb_substr($name, $i, 1));
        $hash &= $hash;
    }

    return $colors[abs($hash) % count($colors)];
}

/*
 * ─────────────────────────────────────────────────────────────
 * SNIPPET STATYCZNY
 * ─────────────────────────────────────────────────────────────
 *
 * <!-- Avatar ze zdjęciem -->
 * <span class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center
 *              overflow-hidden rounded-full" role="img" aria-label="Jan Kowalski">
 *   <img src="/uploads/jan.jpg" alt="Jan Kowalski"
 *        class="h-full w-full object-cover" loading="lazy">
 * </span>
 *
 * <!-- Avatar z inicjałami (fallback) -->
 * <span class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center
 *              overflow-hidden rounded-full" role="img" aria-label="Jan Kowalski">
 *   <span class="h-10 w-10 text-sm flex items-center justify-center rounded-full
 *                font-semibold text-white" style="background-color: #4f46e5"
 *         aria-hidden="true">
 *     JK
 *   </span>
 * </span>
 *
 * Rozmiary:
 *   sm → h-8 w-8,   text-xs
 *   md → h-10 w-10, text-sm   (default)
 *   lg → h-12 w-12, text-base
 *   xl → h-16 w-16, text-xl
 */
