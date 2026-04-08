<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../atoms/icon.php';
?>
<h2 class="text-xl font-semibold text-foreground mb-1">Utwórz konto</h2>
<p class="text-sm text-muted-foreground mb-6">Wprowadź dane, aby założyć konto</p>

<form method="POST" action="/register" novalidate>
    <?= Csrf::hiddenInput() ?>

    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-foreground mb-1">
                Imię i nazwisko <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <input
                type="text"
                id="name"
                name="name"
                required
                autocomplete="name"
                placeholder="Jan Kowalski"
                class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background text-foreground text-sm
                       placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
            >
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-foreground mb-1">
                Adres e-mail <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <input
                type="email"
                id="email"
                name="email"
                required
                autocomplete="email"
                placeholder="jan@kowalski.pl"
                class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background text-foreground text-sm
                       placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
            >
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-foreground mb-1">
                Hasło <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <div class="relative" x-data="{ show: false }">
                <input
                    :type="show ? 'text' : 'password'"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full h-10 px-3 py-2 pr-10 rounded-md border border-border bg-background text-foreground text-sm
                           placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                >
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-muted-foreground hover:text-foreground"
                        :aria-label="show ? 'Ukryj hasło' : 'Pokaż hasło'">
                    <span x-show="!show"><?php render_icon('eye', 'solid', 'h-4 w-4') ?></span>
                    <span x-show="show"><?php render_icon('eye-slash', 'solid', 'h-4 w-4') ?></span>
                </button>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">Minimum 12 znaków, w tym cyfra lub znak specjalny.</p>
        </div>

        <div>
            <label for="password_confirm" class="block text-sm font-medium text-foreground mb-1">
                Potwierdź hasło <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <div class="relative" x-data="{ show: false }">
                <input
                    :type="show ? 'text' : 'password'"
                    id="password_confirm"
                    name="password_confirm"
                    required
                    autocomplete="new-password"
                    class="w-full h-10 px-3 py-2 pr-10 rounded-md border border-border bg-background text-foreground text-sm
                           placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                >
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-muted-foreground hover:text-foreground"
                        :aria-label="show ? 'Ukryj hasło' : 'Pokaż hasło'">
                    <span x-show="!show"><?php render_icon('eye', 'solid', 'h-4 w-4') ?></span>
                    <span x-show="show"><?php render_icon('eye-slash', 'solid', 'h-4 w-4') ?></span>
                </button>
            </div>
        </div>

        <!-- ZAD-1.3 (K3): Consent flow — wymagane RODO Art. 6/7 i Art. 13 -->
        <!-- ZAD-2.10 (P10): aria-describedby → WCAG 3.3.2 Labels or Instructions -->
        <div class="flex items-start gap-2 text-sm">
            <input type="checkbox"
                   id="consent"
                   name="consent"
                   value="1"
                   required
                   aria-describedby="consent-desc"
                   class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring"
            >
            <label for="consent" id="consent-desc" class="text-muted-foreground select-none">
                Akceptuję
                <a href="/terms" target="_blank" rel="noopener"
                   class="font-medium text-foreground hover:underline">Regulamin</a>
                i zapoznałem/am się z
                <a href="/privacy" target="_blank" rel="noopener"
                   class="font-medium text-foreground hover:underline">Polityką prywatności</a>.
                <span class="text-destructive" aria-hidden="true">*</span>
            </label>
        </div>

        <button type="submit"
                class="w-full h-10 px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium
                       hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Utwórz konto
        </button>
    </div>
</form>

<div class="mt-6 text-center text-sm text-muted-foreground">
    Masz już konto?
    <a href="/login" class="font-medium text-foreground hover:underline">Zaloguj się</a>
</div>
