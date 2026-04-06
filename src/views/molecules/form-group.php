<?php
/**
 * Molecule: Form Group
 * Label + input + optional error message, poprawnie powiązane przez aria-describedby.
 *
 * Użycie:
 *   render_form_group('email', 'Adres e-mail', 'email', $oldEmail, $errors['email'] ?? null);
 *   render_form_group('name', 'Imię', 'text', '', null, ['required' => true, 'placeholder' => 'Jan Kowalski']);
 */

require_once __DIR__ . '/../atoms/label.php';
require_once __DIR__ . '/../atoms/input.php';

/**
 * @param string      $id        ID pola (łączy label + input + error)
 * @param string      $labelText Tekst etykiety
 * @param string      $type      Typ inputu (text, email, password, ...)
 * @param string      $value     Aktualna wartość
 * @param string|null $error     Komunikat błędu lub null
 * @param array       $attrs     Dodatkowe atrybuty: name, required, placeholder, autocomplete, ...
 */
function render_form_group(
    string  $id,
    string  $labelText,
    string  $type = 'text',
    string  $value = '',
    ?string $error = null,
    array   $attrs = []
): void {
    $errorId  = $id . '-error';
    $hasError = $error !== null;

    // name domyślnie = id
    $attrs['name'] ??= $id;
    $attrs['id']   = $id;
    $attrs['type'] = $type;
    $attrs['value'] = $value;

    if ($hasError) {
        $attrs['aria-invalid']       = 'true';
        $attrs['aria-describedby']   = $errorId;
    }

    $required = !empty($attrs['required']);
    ?>
    <div class="space-y-1">
        <?php render_label($id, $labelText, $required) ?>
        <?php render_input($attrs, $hasError) ?>
        <?php if ($hasError): ?>
            <p id="<?= htmlspecialchars($errorId) ?>"
               class="text-xs text-destructive"
               role="alert">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
