<?php
/**
 * Atom: Label
 * Etykieta formularza — zawsze powiązana z inputem przez for/id.
 *
 * Użycie:
 *   <?php render_label('email', 'Adres email'); ?>
 *   <?php render_label('password', 'Hasło', true); ?>   <!-- wymagane = gwiazdka -->
 *
 * @param string $for      Odpowiada id powiązanego inputu
 * @param string $text     Tekst etykiety
 * @param bool   $required Pokazuje wizualny znacznik * (z aria-hidden)
 */
function render_label(
    string $for,
    string $text,
    bool $required = false
): void {
    ?>
    <label
        for="<?= htmlspecialchars($for) ?>"
        class="text-sm font-medium leading-none text-[hsl(var(--foreground))]
               peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
    >
        <?= htmlspecialchars($text) ?>
        <?php if ($required): ?>
            <span
                aria-hidden="true"
                class="ml-0.5 text-[hsl(var(--destructive))]"
                title="Pole wymagane"
            >*</span>
        <?php endif; ?>
    </label>
    <?php
}

/*
 * ─────────────────────────────────────────────────────────────
 * SNIPPET STATYCZNY
 * ─────────────────────────────────────────────────────────────
 *
 * <!-- Label podstawowy -->
 * <label for="email"
 *        class="text-sm font-medium leading-none text-[hsl(var(--foreground))]
 *               peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
 *   Adres email
 * </label>
 *
 * <!-- Label wymagany -->
 * <label for="password"
 *        class="text-sm font-medium leading-none text-[hsl(var(--foreground))]">
 *   Hasło
 *   <span aria-hidden="true" class="ml-0.5 text-[hsl(var(--destructive))]" title="Pole wymagane">
 *     *
 *   </span>
 * </label>
 */
