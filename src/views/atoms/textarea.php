<?php
declare(strict_types=1);

/**
 * @param string      $id       HTML id / name attribute
 * @param string      $label    Visible label text
 * @param string      $value    Current value
 * @param int         $rows     Textarea rows
 * @param string|null $error    Validation error message
 * @param array       $attrs    Extra HTML attributes as ['key' => 'value']
 */
function render_textarea(
    string  $id,
    string  $label,
    string  $value    = '',
    int     $rows     = 3,
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
        <textarea
            id="<?= htmlspecialchars($id) ?>"
            name="<?= htmlspecialchars($id) ?>"
            rows="<?= $rows ?>"
            class="w-full px-3 py-2 rounded-md border bg-background text-foreground text-sm
                   placeholder:text-muted-foreground resize-none
                   focus:outline-none focus:ring-2 focus:border-transparent
                   <?= $borderClass ?>"
            <?= $ariaDesc . $extraAttrs ?>
        ><?= htmlspecialchars($value) ?></textarea>
        <?php if ($error): ?>
            <p id="<?= htmlspecialchars($errorId) ?>" class="text-xs text-destructive" role="alert">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
