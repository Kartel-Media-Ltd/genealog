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
    // render_input ma sygnaturę: (id, type, label, value, error, helperText, attrs)
    // Wewnątrz sam renderuje label przez render_label, więc używamy go w pełni —
    // bez ręcznego wywołania render_label.
    $required = !empty($attrs['required']);
    if ($required && !str_contains($labelText, '*')) {
        // render_input nie obsługuje "required marker" sam — i tak nie szkodzi
        // bo render_label dba o gwiazdkę.
    }
    render_input(
        id: $id,
        type: $type,
        label: $labelText,
        value: $value,
        error: $error ?? '',
        helperText: '',
        attrs: $attrs,
    );
}
