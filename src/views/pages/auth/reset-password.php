<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var string $token */
?>
<div class="mx-auto max-w-md py-12">
    <div class="rounded-lg border border-border bg-card p-8 shadow-sm">
        <h1 class="mb-2 text-2xl font-semibold text-foreground">Ustaw nowe hasło</h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Wprowadź nowe hasło dla swojego konta. Po zapisaniu wszystkie aktywne sesje
            zostaną automatycznie wylogowane.
        </p>

        <form method="POST" action="/reset-password/<?= htmlspecialchars($token) ?>" class="space-y-4">
            <?= Csrf::hiddenInput() ?>

            <div class="space-y-1.5">
                <label for="password" class="block text-sm font-medium text-foreground">
                    Nowe hasło <span class="text-destructive">*</span>
                </label>
                <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password"
                       class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                              text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                <p class="text-xs text-muted-foreground">Minimum 12 znaków, w tym cyfra lub znak specjalny.</p>
            </div>

            <div class="space-y-1.5">
                <label for="password_confirm" class="block text-sm font-medium text-foreground">
                    Powtórz hasło <span class="text-destructive">*</span>
                </label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="12" autocomplete="new-password"
                       class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                              text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring">
            </div>

            <button type="submit"
                    class="w-full inline-flex h-10 items-center justify-center rounded-md
                           bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors">
                Ustaw hasło i zaloguj
            </button>

            <p class="text-center text-sm text-muted-foreground">
                <a href="/login" class="text-primary hover:underline">Powrót do logowania</a>
            </p>
        </form>
    </div>
</div>
