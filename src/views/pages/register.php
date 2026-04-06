<?php
/**
 * Page: Register
 * Strona rejestracji — używa AuthLayout.
 *
 * Zmienne przekazywane przez kontroler:
 *   $errors   = ['name' => '...', 'email' => '...', 'password' => '...', 'password_confirm' => '...']
 *   $oldInput = ['name' => '...', 'email' => '...']
 */

require_once __DIR__ . '/../atoms/button.php';
require_once __DIR__ . '/../atoms/input.php';
require_once __DIR__ . '/../atoms/label.php';
require_once __DIR__ . '/../atoms/spinner.php';
require_once __DIR__ . '/../atoms/alert.php';

$errors   = $errors   ?? [];
$oldInput = $oldInput ?? [];

$pageTitle = 'Utwórz konto';

/*
 * ─────────────────────────────────────────────────────────────────────
 * WIREFRAME ASCII
 * ─────────────────────────────────────────────────────────────────────
 *
 *  Mobile (< 640px):                 Tablet+ (≥ 640px):
 *
 *  ┌──────────────────────────┐      ┌────────────────────────────────────┐
 *  │ [Logo Genealog]          │      │                                    │
 *  ├──────────────────────────┤      │        [Logo + Tagline]            │
 *  │ Utwórz konto             │      │                                    │
 *  │ Wypełnij formularz       │      │   ┌────────────────────────────┐   │
 *  │                          │      │   │  Utwórz konto              │   │
 *  │ Imię i nazwisko *        │      │   │  Wypełnij poniższy formularz│  │
 *  │ [___________________]    │      │   ├────────────────────────────┤   │
 *  │                          │      │   │ Imię i nazwisko *          │   │
 *  │ Adres email *            │      │   │ [________________________] │   │
 *  │ [___________________]    │      │   │                            │   │
 *  │                          │      │   │ Adres email *              │   │
 *  │ Hasło *                  │      │   │ [________________________] │   │
 *  │ [___________________]    │      │   │                            │   │
 *  │ ████████░░ Siła hasła    │      │   │ Hasło *                    │   │
 *  │                          │      │   │ [________________________] │   │
 *  │ Powtórz hasło *          │      │   │ ████████░░ Siła hasła      │   │
 *  │ [___________________]    │      │   │                            │   │
 *  │                          │      │   │ Powtórz hasło *            │   │
 *  │ [   Utwórz konto      ]  │      │   │ [________________________] │   │
 *  │                          │      │   │                            │   │
 *  │ Masz już konto?          │      │   │ [    Utwórz konto       ] │   │
 *  │ → Zaloguj się            │      │   ├────────────────────────────┤   │
 *  └──────────────────────────┘      │   │ Masz już konto? → Zaloguj  │   │
 *                                    │   └────────────────────────────┘   │
 *                                    └────────────────────────────────────┘
 */

ob_start();
?>

<div class="rounded-lg border border-[hsl(var(--border))]
            bg-[hsl(var(--card))] text-[hsl(var(--card-foreground))] shadow-sm">

    <!-- CardHeader -->
    <div class="flex flex-col gap-1.5 p-6">
        <h1 class="text-xl font-semibold leading-none tracking-tight">
            Utwórz konto
        </h1>
        <p class="text-sm text-[hsl(var(--muted-foreground))]">
            Dołącz do Genealog i zacznij budować swoje drzewo rodzinne.
        </p>
    </div>

    <!-- CardContent -->
    <div class="p-6 pt-0">

        <!-- Alert błędu ogólnego -->
        <?php if (!empty($errors['general'])): ?>
            <div class="mb-6">
                <?php render_alert($errors['general'], 'error', 'Błąd rejestracji'); ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            action="/register"
            class="flex flex-col gap-5"
            novalidate
            x-data="{
                showPassword: false,
                showConfirm: false,
                loading: false,
                password: '',
                passwordStrength: 0,
                passwordLabel: '',

                calcStrength(val) {
                    let score = 0;
                    if (val.length >= 8)                score++;
                    if (val.length >= 12)               score++;
                    if (/[A-Z]/.test(val))              score++;
                    if (/[0-9]/.test(val))              score++;
                    if (/[^A-Za-z0-9]/.test(val))       score++;
                    this.passwordStrength = score;
                    this.passwordLabel = ['', 'Słabe', 'Słabe', 'Średnie', 'Silne', 'Bardzo silne'][score] || '';
                },

                submitForm() {
                    this.loading = true;
                }
            }"
            @submit="submitForm"
        >
            <!-- CSRF -->
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <!-- Imię i nazwisko -->
            <?php render_input(
                id:         'name',
                type:       'text',
                label:      'Imię i nazwisko',
                value:      $oldInput['name'] ?? '',
                error:      $errors['name'] ?? '',
                helperText: '',
                attrs:      [
                    'name'         => 'name',
                    'placeholder'  => 'Jan Kowalski',
                    'autocomplete' => 'name',
                    'required'     => 'required',
                    'autofocus'    => 'autofocus',
                    'minlength'    => '2',
                ]
            ); ?>

            <!-- Email -->
            <?php render_input(
                id:         'email',
                type:       'email',
                label:      'Adres email',
                value:      $oldInput['email'] ?? '',
                error:      $errors['email'] ?? '',
                helperText: '',
                attrs:      [
                    'name'         => 'email',
                    'placeholder'  => 'jan@example.com',
                    'autocomplete' => 'email',
                    'required'     => 'required',
                ]
            ); ?>

            <!-- Hasło z miernikiem siły -->
            <div class="flex flex-col gap-1.5">
                <?php render_label('password', 'Hasło', required: true); ?>

                <div class="relative">
                    <input
                        id="password"
                        name="password"
                        :type="showPassword ? 'text' : 'password'"
                        x-model="password"
                        @input="calcStrength(password)"
                        class="flex h-11 w-full rounded-md border px-3 py-2 pr-10 text-sm
                               bg-[hsl(var(--background))] text-[hsl(var(--foreground))]
                               placeholder:text-[hsl(var(--muted-foreground))]
                               transition-colors duration-150
                               focus-visible:outline-none focus-visible:ring-2
                               focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2
                               <?= !empty($errors['password'])
                                   ? 'border-[hsl(var(--destructive))] focus-visible:ring-[hsl(var(--destructive))]'
                                   : 'border-[hsl(var(--input))]' ?>"
                        autocomplete="new-password"
                        required
                        minlength="8"
                        aria-describedby="password-strength<?= !empty($errors['password']) ? ' password-error' : '' ?>"
                        <?= !empty($errors['password']) ? 'aria-invalid="true"' : '' ?>
                    >
                    <button type="button" @click="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Ukryj hasło' : 'Pokaż hasło'"
                            class="absolute right-3 top-1/2 -translate-y-1/2 flex h-6 w-6 items-center
                                   justify-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] rounded">
                        <svg x-show="!showPassword" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg x-show="showPassword" class="h-4 w-4" style="display:none"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1
                                     5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0
                                     1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>

                <!-- Miernik siły hasła -->
                <div id="password-strength" x-show="password.length > 0" class="space-y-1">
                    <div class="flex gap-1" aria-hidden="true">
                        <template x-for="i in 5">
                            <div class="h-1.5 flex-1 rounded-full transition-colors duration-300"
                                 :class="{
                                     'bg-[hsl(var(--destructive))]': i <= passwordStrength && passwordStrength <= 2,
                                     'bg-[hsl(var(--warning))]':     i <= passwordStrength && passwordStrength === 3,
                                     'bg-[hsl(var(--success))]':     i <= passwordStrength && passwordStrength >= 4,
                                     'bg-[hsl(var(--muted))]':       i > passwordStrength,
                                 }">
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-[hsl(var(--muted-foreground))]">
                        Siła hasła: <span class="font-medium" x-text="passwordLabel"></span>
                    </p>
                </div>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">
                    Minimum 8 znaków. Zalecamy: wielka litera, cyfra, znak specjalny.
                </p>

                <?php if (!empty($errors['password'])): ?>
                    <p id="password-error" role="alert"
                       class="flex items-center gap-1 text-xs text-[hsl(var(--destructive))]">
                        <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <?= htmlspecialchars($errors['password']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Powtórz hasło -->
            <div class="flex flex-col gap-1.5">
                <?php render_label('password_confirm', 'Powtórz hasło', required: true); ?>

                <div class="relative">
                    <input
                        id="password_confirm"
                        name="password_confirm"
                        :type="showConfirm ? 'text' : 'password'"
                        class="flex h-11 w-full rounded-md border px-3 py-2 pr-10 text-sm
                               bg-[hsl(var(--background))] text-[hsl(var(--foreground))]
                               placeholder:text-[hsl(var(--muted-foreground))]
                               transition-colors duration-150
                               focus-visible:outline-none focus-visible:ring-2
                               focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2
                               <?= !empty($errors['password_confirm'])
                                   ? 'border-[hsl(var(--destructive))] focus-visible:ring-[hsl(var(--destructive))]'
                                   : 'border-[hsl(var(--input))]' ?>"
                        autocomplete="new-password"
                        required
                        <?= !empty($errors['password_confirm']) ? 'aria-invalid="true" aria-describedby="password-confirm-error"' : '' ?>
                    >
                    <button type="button" @click="showConfirm = !showConfirm"
                            :aria-label="showConfirm ? 'Ukryj powtórzenie hasła' : 'Pokaż powtórzenie hasła'"
                            class="absolute right-3 top-1/2 -translate-y-1/2 flex h-6 w-6 items-center
                                   justify-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] rounded">
                        <svg x-show="!showConfirm" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg x-show="showConfirm" class="h-4 w-4" style="display:none"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1
                                     5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0
                                     1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>

                <?php if (!empty($errors['password_confirm'])): ?>
                    <p id="password-confirm-error" role="alert"
                       class="flex items-center gap-1 text-xs text-[hsl(var(--destructive))]">
                        <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <?= htmlspecialchars($errors['password_confirm']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Zgoda na regulamin -->
            <div class="flex items-start gap-3">
                <input
                    type="checkbox"
                    id="terms"
                    name="terms"
                    value="1"
                    required
                    class="mt-0.5 h-4 w-4 rounded border-[hsl(var(--input))]
                           text-[hsl(var(--primary))] accent-[hsl(var(--primary))]
                           focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))]"
                    <?= !empty($errors['terms']) ? 'aria-invalid="true" aria-describedby="terms-error"' : '' ?>
                >
                <div class="flex-1">
                    <label for="terms"
                           class="text-sm text-[hsl(var(--foreground))] leading-relaxed
                                  peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                        Akceptuję
                        <a href="/terms" class="text-[hsl(var(--primary))] hover:underline
                                                focus-visible:outline-none focus-visible:ring-2
                                                focus-visible:ring-[hsl(var(--ring))] rounded">
                            regulamin
                        </a>
                        i
                        <a href="/privacy" class="text-[hsl(var(--primary))] hover:underline
                                                   focus-visible:outline-none focus-visible:ring-2
                                                   focus-visible:ring-[hsl(var(--ring))] rounded">
                            politykę prywatności
                        </a>
                        <span aria-hidden="true" class="text-[hsl(var(--destructive))]">*</span>
                    </label>
                    <?php if (!empty($errors['terms'])): ?>
                        <p id="terms-error" role="alert"
                           class="mt-1 text-xs text-[hsl(var(--destructive))]">
                            <?= htmlspecialchars($errors['terms']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Przycisk submit -->
            <button
                type="submit"
                :disabled="loading"
                class="inline-flex h-11 w-full items-center justify-center gap-2
                       rounded-md px-4 py-2 text-sm font-medium
                       bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]
                       hover:bg-[hsl(var(--primary)/0.9)]
                       transition-colors duration-200
                       focus-visible:outline-none focus-visible:ring-2
                       focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2
                       disabled:pointer-events-none disabled:opacity-50"
                :aria-busy="loading"
            >
                <template x-if="loading">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg"
                         fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <span x-text="loading ? 'Tworzenie konta...' : 'Utwórz konto'">Utwórz konto</span>
            </button>

        </form>
    </div>

    <!-- CardFooter -->
    <div class="flex items-center justify-center p-6 pt-0">
        <p class="text-sm text-[hsl(var(--muted-foreground))]">
            Masz już konto?&nbsp;
            <a href="/login"
               class="font-medium text-[hsl(var(--primary))] hover:underline
                      focus-visible:outline-none focus-visible:ring-2
                      focus-visible:ring-[hsl(var(--ring))] rounded">
                Zaloguj się
            </a>
        </p>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/auth-layout.php';
