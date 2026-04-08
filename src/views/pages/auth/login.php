<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../atoms/icon.php';
?>
<h2 class="text-xl font-semibold text-foreground mb-1">Zaloguj się</h2>
<p class="text-sm text-muted-foreground mb-6">Wprowadź dane dostępowe do konta</p>

<form method="POST" action="/login" novalidate>
    <?= Csrf::hiddenInput() ?>

    <div class="space-y-4">
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
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-sm font-medium text-foreground">
                    Hasło <span class="text-destructive" aria-hidden="true">*</span>
                </label>
                <a href="/forgot-password" class="text-xs text-muted-foreground hover:text-foreground">Nie pamiętasz?</a>
            </div>
            <div class="relative" x-data="{ show: false }">
                <input
                    :type="show ? 'text' : 'password'"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
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

        <button type="submit"
                class="w-full h-10 px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium
                       hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Zaloguj się
        </button>
    </div>
</form>

<div class="mt-6 text-center text-sm text-muted-foreground">
    Nie masz konta?
    <a href="/register" class="font-medium text-foreground hover:underline">Zarejestruj się</a>
</div>
