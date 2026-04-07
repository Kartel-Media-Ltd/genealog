<?php
declare(strict_types=1);

/**
 * @param string      $id       HTML id / name attribute
 * @param string      $label    Visible label text
 * @param array       $options  [value => label] pairs
 * @param mixed       $selected Currently selected value
 * @param string|null $error    Validation error message
 * @param array       $attrs    Extra HTML attributes as ['key' => 'value']
 */
function render_select(
    string  $id,
    string  $label,
    array   $options,
    mixed   $selected = null,
    ?string $error    = null,
    array   $attrs    = [],
): void {
    $errorId    = $id . '_error';
    $ariaDesc   = $error ? ' aria-describedby="' . htmlspecialchars($errorId) . '"' : '';
    $extraAttrs = '';
    foreach ($attrs as $k => $v) {
        $extraAttrs .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
    }
    $borderClass = $error
        ? 'border-destructive focus:ring-destructive'
        : 'border-border focus:ring-ring';
    ?>
    <div class="space-y-1.5">
        <label for="<?= htmlspecialchars($id) ?>" class="block text-sm font-medium text-foreground">
            <?= htmlspecialchars($label) ?>
        </label>
        <select
            id="<?= htmlspecialchars($id) ?>"
            name="<?= htmlspecialchars($id) ?>"
            class="w-full h-10 px-3 py-2 rounded-md border bg-background text-foreground text-sm
                   focus:outline-none focus:ring-2 focus:border-transparent
                   <?= $borderClass ?>"
            <?= $ariaDesc . $extraAttrs ?>
        >
            <?php foreach ($options as $value => $optLabel): ?>
                <option value="<?= htmlspecialchars((string)$value) ?>"
                    <?= ((string)$selected === (string)$value) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$optLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($error): ?>
            <p id="<?= htmlspecialchars($errorId) ?>" class="text-xs text-destructive" role="alert">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
