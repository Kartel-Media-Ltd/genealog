<?php
/**
 * Atom: Input + Label
 * Kompletny zestaw: Label → Input → HelperText / Error
 *
 * Użycie:
 *   <?php render_input('email', 'email', 'Adres email', 'jan@example.com'); ?>
 *   <?php render_input('password', 'password', 'Hasło', '', '', 'Minimum 8 znaków'); ?>
 *   <?php render_input('email', 'email', 'Email', '', 'Podaj poprawny adres email'); ?>
 *
 * @param string $id          Unikalny id (powiązuje label z inputem — WCAG)
 * @param string $type        text | email | password | number | tel | url | search
 * @param string $label       Tekst etykiety
 * @param string $value       Bieżąca wartość (np. z $_POST po walidacji)
 * @param string $error       Komunikat błędu (pusty = brak błędu)
 * @param string $helperText  Podpowiedź pod inputem
 * @param array  $attrs       Dodatkowe atrybuty: name, placeholder, required, autocomplete...
 */
function render_input(
    string $id,
    string $type = 'text',
    string $label = '',
    string $value = '',
    string $error = '',
    string $helperText = '',
    array  $attrs = []
): void {
    $hasError      = !empty($error);
    $helperTextId  = $helperText ? "{$id}-helper" : '';
    $errorId       = $hasError   ? "{$id}-error"  : '';

    // Describedby — łączy input z pomocniczym tekstem i błędem (WCAG)
    $describedBy = trim("$helperTextId $errorId");

    // Klasy inputu
    $inputBase = implode(' ', [
        'flex h-11 w-full rounded-md border px-3 py-2',
        'text-sm text-[hsl(var(--foreground))]',
        'bg-[hsl(var(--background))]',
        'placeholder:text-[hsl(var(--muted-foreground))]',
        'transition-colors duration-150',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        'file:border-0 file:bg-transparent file:text-sm file:font-medium',
    ]);

    $inputState = $hasError
        ? 'border-[hsl(var(--destructive))] focus-visible:ring-[hsl(var(--destructive))]'
        : 'border-[hsl(var(--input))]';

    // Buduj atrybuty inputu
    $defaultAttrs = [
        'id'    => $id,
        'name'  => $attrs['name'] ?? $id,
        'type'  => $type,
        'value' => $value,
        'class' => "$inputBase $inputState",
    ];

    if ($describedBy) {
        $defaultAttrs['aria-describedby'] = $describedBy;
    }
    if ($hasError) {
        $defaultAttrs['aria-invalid'] = 'true';
    }

    $mergedAttrs = array_merge($defaultAttrs, $attrs);
    // Klasy: merge zamiast nadpisania
    if (isset($attrs['class'])) {
        $mergedAttrs['class'] = $defaultAttrs['class'] . ' ' . $attrs['class'];
    }

    $attrString = '';
    foreach ($mergedAttrs as $key => $val) {
        if ($key === 'value' && $type === 'password') {
            // Nigdy nie przywracaj hasła do inputu
            continue;
        }
        $attrString .= ' ' . htmlspecialchars($key)
                     . '="' . htmlspecialchars((string)$val) . '"';
    }
    ?>
    <div class="flex flex-col gap-1.5">

        <?php if ($label): ?>
            <?php render_label($id, $label, (bool)($attrs['required'] ?? false)) ?>
        <?php endif; ?>

        <input<?= $attrString ?>>

        <?php if ($helperText && !$hasError): ?>
            <p id="<?= htmlspecialchars($helperTextId) ?>"
               class="text-xs text-[hsl(var(--muted-foreground))]">
                <?= htmlspecialchars($helperText) ?>
            </p>
        <?php endif; ?>

        <?php if ($hasError): ?>
            <p id="<?= htmlspecialchars($errorId) ?>"
               role="alert"
               class="flex items-center gap-1 text-xs text-[hsl(var(--destructive))]">
                <!-- Ikona błędu inline -->
                <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

    </div>
    <?php
}

/*
 * ─────────────────────────────────────────────────────────────
 * SNIPPET STATYCZNY — gotowy do copy-paste
 * ─────────────────────────────────────────────────────────────
 *
 * <!-- Input: normalny stan -->
 * <div class="flex flex-col gap-1.5">
 *   <label for="email"
 *          class="text-sm font-medium leading-none text-[hsl(var(--foreground))]
 *                 peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
 *     Adres email <span aria-hidden="true" class="text-[hsl(var(--destructive))]">*</span>
 *   </label>
 *   <input
 *     id="email" name="email" type="email"
 *     class="flex h-11 w-full rounded-md border border-[hsl(var(--input))]
 *            bg-[hsl(var(--background))] px-3 py-2 text-sm
 *            placeholder:text-[hsl(var(--muted-foreground))]
 *            focus-visible:outline-none focus-visible:ring-2
 *            focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2
 *            disabled:cursor-not-allowed disabled:opacity-50"
 *     aria-describedby="email-helper"
 *     placeholder="jan@example.com"
 *     autocomplete="email"
 *     required>
 *   <p id="email-helper" class="text-xs text-[hsl(var(--muted-foreground))]">
 *     Użyjemy go do logowania.
 *   </p>
 * </div>
 *
 * <!-- Input: stan błędu -->
 * <div class="flex flex-col gap-1.5">
 *   <label for="email" class="text-sm font-medium leading-none ...">Adres email</label>
 *   <input
 *     id="email" name="email" type="email"
 *     class="... border-[hsl(var(--destructive))] focus-visible:ring-[hsl(var(--destructive))]"
 *     aria-invalid="true"
 *     aria-describedby="email-error">
 *   <p id="email-error" role="alert"
 *      class="flex items-center gap-1 text-xs text-[hsl(var(--destructive))]">
 *     <svg ...></svg>
 *     Podaj poprawny adres email.
 *   </p>
 * </div>
 */
