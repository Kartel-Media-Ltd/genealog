<?php
declare(strict_types=1);
use App\Core\Csrf;
?>
<h2 class="text-xl font-semibold text-foreground mb-1">Resetuj hasło</h2>
<p class="text-sm text-muted-foreground mb-6">
    Podaj swój adres e-mail. Jeśli konto istnieje, wyślemy link do resetowania hasła.
</p>

<form method="POST" action="/forgot-password" novalidate>
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

        <button type="submit"
                class="w-full h-10 px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium
                       hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Wyślij link resetujący
        </button>
    </div>
</form>

<div class="mt-6 text-center text-sm text-muted-foreground">
    <a href="/login" class="font-medium text-foreground hover:underline">Powrót do logowania</a>
</div>
