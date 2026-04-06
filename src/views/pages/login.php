<?php
/**
 * Page: Login
 * Strona logowania — używa AuthLayout.
 *
 * Zmienne przekazywane przez kontroler:
 *   $errors  = ['email' => '...', 'password' => '...', 'general' => '...']
 *   $oldInput = ['email' => '...']   (wartości po nieudanym submit)
 */

require_once __DIR__ . '/../atoms/button.php';
require_once __DIR__ . '/../atoms/input.php';
require_once __DIR__ . '/../atoms/label.php';
require_once __DIR__ . '/../atoms/spinner.php';
require_once __DIR__ . '/../atoms/alert.php';

$errors   = $errors   ?? [];
$oldInput = $oldInput ?? [];

$pageTitle = 'Zaloguj się';

/*
 * ─────────────────────────────────────────────────────────────────────
 * WIREFRAME ASCII
 * ─────────────────────────────────────────────────────────────────────
 *
 *  Mobile (< 640px):                 Tablet+ (≥ 640px):
 *
 *  ┌──────────────────────────┐      ┌────────────────────────────────────┐
 *  │ [Logo Genealog]          │      │                                    │
 *  │ Odkryj swoje korzenie    │      │        [Logo + Tagline]            │
 *  ├──────────────────────────┤      │                                    │
 *  │ [Error alert — jeśli]    │      │   ┌────────────────────────────┐   │
 *  ├──────────────────────────┤      │   │  Zaloguj się               │   │
 *  │                          │      │   │  Wprowadź dane do konta    │   │
 *  │ Zaloguj się              │      │   ├────────────────────────────┤   │
 *  │ Wprowadź dane do konta   │      │   │ [Error alert — jeśli]      │   │
 *  │                          │      │   │                            │   │
 *  │ Adres email *            │      │   │ Adres email *              │   │
 *  │ [___________________]    │      │   │ [________________________] │   │
 *  │                          │      │   │                            │   │
 *  │ Hasło *                  │      │   │ Hasło *                    │   │
 *  │ [___________________]    │      │   │ [________________________] │   │
 *  │ 👁 Pokaż hasło           │      │   │ 👁 Pokaż hasło            │   │
 *  │                          │      │   │                            │   │
 *  │ [     Zaloguj się     ]  │      │   │ [      Zaloguj się      ] │   │
 *  │                          │      │   ├────────────────────────────┤   │
 *  │ Nie masz konta?          │      │   │ Nie masz konta?            │   │
 *  │ → Zarejestruj się        │      │   │ → Zarejestruj się          │   │
 *  └──────────────────────────┘      │   └────────────────────────────┘   │
 *                                    │                                    │
 *                                    └────────────────────────────────────┘
 */

ob_start();
?>

<div class="rounded-lg border border-[hsl(var(--border))]
            bg-[hsl(var(--card))] text-[hsl(var(--card-foreground))] shadow-sm">

    <!-- CardHeader -->
    <div class="flex flex-col gap-1.5 p-6">
        <h1 class="text-xl font-semibold leading-none tracking-tight">
            Zaloguj się
        </h1>
        <p class="text-sm text-[hsl(var(--muted-foreground))]">
            Wprowadź swoje dane, aby uzyskać dostęp do konta.
        </p>
    </div>

    <!-- CardContent -->
    <div class="p-6 pt-0">

        <!-- Alert błędu ogólnego (np. złe hasło) -->
        <?php if (!empty($errors['general'])): ?>
            <div class="mb-6">
                <?php render_alert(
                    htmlspecialchars($errors['general']),
                    'error',
                    'Błąd logowania'
                ); ?>
            </div>
        <?php endif; ?>

        <!-- Formularz logowania -->
        <form
            method="POST"
            action="/login"
            class="flex flex-col gap-5"
            novalidate
            x-data="{
                showPassword: false,
                loading: false,
                submitForm(e) {
                    this.loading = true;
                }
            }"
            @submit="submitForm"
        >
            <!-- CSRF token -->
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

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
                    'autofocus'    => 'autofocus',
                ]
            ); ?>

            <!-- Hasło z przełącznikiem widoczności -->
            <div class="flex flex-col gap-1.5">
                <?php render_label('password', 'Hasło', required: true); ?>

                <div class="relative">
                    <input
                        id="password"
                        name="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="flex h-11 w-full rounded-md border px-3 py-2 pr-10 text-sm
                               bg-[hsl(var(--background))] text-[hsl(var(--foreground))]
                               placeholder:text-[hsl(var(--muted-foreground))]
                               transition-colors duration-150
                               focus-visible:outline-none focus-visible:ring-2
                               focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2
                               disabled:cursor-not-allowed disabled:opacity-50
                               <?= !empty($errors['password'])
                                   ? 'border-[hsl(var(--destructive))] focus-visible:ring-[hsl(var(--destructive))]'
                                   : 'border-[hsl(var(--input))]' ?>"
                        autocomplete="current-password"
                        required
                        <?= !empty($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>
                    >

                    <!-- Przycisk pokaż/ukryj hasło -->
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2
                               flex h-6 w-6 items-center justify-center
                               text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]
                               focus-visible:outline-none focus-visible:ring-2
                               focus-visible:ring-[hsl(var(--ring))] rounded"
                        :aria-label="showPassword ? 'Ukryj hasło' : 'Pokaż hasło'"
                    >
                        <!-- Oko otwarte -->
                        <svg x-show="!showPassword" class="h-4 w-4"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <!-- Oko zamknięte -->
                        <svg x-show="showPassword" class="h-4 w-4" style="display:none"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45
                                     18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11
                                     8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1
                                     1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>

                <?php if (!empty($errors['password'])): ?>
                    <p id="password-error" role="alert"
                       class="flex items-center gap-1 text-xs text-[hsl(var(--destructive))]">
                        <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <?= htmlspecialchars($errors['password']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Link zapomniane hasło -->
            <div class="flex justify-end -mt-2">
                <a href="/forgot-password"
                   class="text-sm text-[hsl(var(--primary))] hover:underline
                          focus-visible:outline-none focus-visible:ring-2
                          focus-visible:ring-[hsl(var(--ring))] rounded">
                    Nie pamiętasz hasła?
                </a>
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
                <!-- Spinner podczas ładowania -->
                <template x-if="loading">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg"
                         fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <span x-text="loading ? 'Logowanie...' : 'Zaloguj się'">Zaloguj się</span>
            </button>

        </form>
    </div>

    <!-- CardFooter -->
    <div class="flex items-center justify-center p-6 pt-0">
        <p class="text-sm text-[hsl(var(--muted-foreground))]">
            Nie masz konta?&nbsp;
            <a href="/register"
               class="font-medium text-[hsl(var(--primary))] hover:underline
                      focus-visible:outline-none focus-visible:ring-2
                      focus-visible:ring-[hsl(var(--ring))] rounded">
                Zarejestruj się
            </a>
        </p>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/auth-layout.php';
