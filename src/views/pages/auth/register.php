<?php
declare(strict_types=1);
use App\Core\Csrf;
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
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                </button>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">Minimum 8 znaków</p>
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
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                </button>
            </div>
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
