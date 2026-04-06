<?php
/**
 * Atom: Card
 * Składa się z: Card > CardHeader > CardTitle + CardDescription > CardContent > CardFooter
 *
 * Użycie (otwarcie/zamknięcie):
 *   <?php card_open(); ?>
 *     <?php card_header('Zaloguj się', 'Wprowadź dane dostępowe do konta'); ?>
 *     <?php card_content_open(); ?>
 *       <!-- zawartość -->
 *     <?php card_content_close(); ?>
 *     <?php card_footer_open(); ?>
 *       <!-- przyciski, linki -->
 *     <?php card_footer_close(); ?>
 *   <?php card_close(); ?>
 */

/** Otwiera kontener Card */
function card_open(string $class = ''): void {
    $base = 'rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))] text-[hsl(var(--card-foreground))] shadow-sm';
    ?>
    <div class="<?= trim("$base $class") ?>">
    <?php
}

/** Zamyka kontener Card */
function card_close(): void {
    echo '</div>';
}

/**
 * CardHeader — tytuł + opcjonalny opis
 *
 * @param string $title       Główny tytuł karty (h2)
 * @param string $description Podtytuł / opis
 */
function card_header(string $title, string $description = ''): void {
    ?>
    <div class="flex flex-col gap-1.5 p-6">
        <h2 class="text-xl font-semibold leading-none tracking-tight text-[hsl(var(--card-foreground))]">
            <?= htmlspecialchars($title) ?>
        </h2>
        <?php if ($description): ?>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">
                <?= htmlspecialchars($description) ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/** Otwiera CardContent */
function card_content_open(string $class = ''): void {
    ?>
    <div class="<?= trim("p-6 pt-0 $class") ?>">
    <?php
}

/** Zamyka CardContent */
function card_content_close(): void {
    echo '</div>';
}

/** Otwiera CardFooter */
function card_footer_open(string $class = ''): void {
    ?>
    <div class="<?= trim("flex items-center p-6 pt-0 $class") ?>">
    <?php
}

/** Zamyka CardFooter */
function card_footer_close(): void {
    echo '</div>';
}

/*
 * ─────────────────────────────────────────────────────────────
 * SNIPPET STATYCZNY — gotowy do copy-paste
 * ─────────────────────────────────────────────────────────────
 *
 * <div class="rounded-lg border border-[hsl(var(--border))]
 *             bg-[hsl(var(--card))] text-[hsl(var(--card-foreground))] shadow-sm">
 *
 *   <!-- CardHeader -->
 *   <div class="flex flex-col gap-1.5 p-6">
 *     <h2 class="text-xl font-semibold leading-none tracking-tight">
 *       Zaloguj się
 *     </h2>
 *     <p class="text-sm text-[hsl(var(--muted-foreground))]">
 *       Wprowadź dane dostępowe do konta.
 *     </p>
 *   </div>
 *
 *   <!-- CardContent -->
 *   <div class="p-6 pt-0">
 *     <!-- form fields here -->
 *   </div>
 *
 *   <!-- CardFooter -->
 *   <div class="flex items-center p-6 pt-0">
 *     <!-- action buttons here -->
 *   </div>
 *
 * </div>
 */
