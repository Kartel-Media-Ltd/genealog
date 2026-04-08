<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\User $user */
$activeTab = $_GET['tab'] ?? 'profile';
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/dashboard" class="hover:text-foreground transition-colors">Dashboard</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span class="text-foreground font-medium">Mój profil</span>
</nav>

<div class="mx-auto max-w-2xl"
     x-data="{ tab: '<?= htmlspecialchars($activeTab) ?>' }">

    <!-- Header -->
    <div class="mb-6 flex items-center gap-4">
        <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center text-xl font-semibold text-muted-foreground select-none">
            <?= htmlspecialchars(mb_strtoupper(mb_substr($user->name, 0, 1))) ?>
        </div>
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground"><?= htmlspecialchars($user->name) ?></h1>
            <p class="text-sm text-muted-foreground"><?= htmlspecialchars($user->email) ?></p>
            <p class="text-xs text-muted-foreground mt-0.5">
                Konto od <?= date('j M Y', strtotime($user->createdAt)) ?>
            </p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-border mb-6" role="tablist">
        <button type="button" role="tab"
                :aria-selected="tab === 'profile'"
                @click="tab = 'profile'"
                :class="tab === 'profile'
                    ? 'border-b-2 border-foreground text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground'"
                class="-mb-px pb-3 pt-1 px-1 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t">
            Dane profilu
        </button>
        <button type="button" role="tab"
                :aria-selected="tab === 'email'"
                @click="tab = 'email'"
                :class="tab === 'email'
                    ? 'border-b-2 border-foreground text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground'"
                class="-mb-px pb-3 pt-1 px-4 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t">
            Zmień email
        </button>
        <button type="button" role="tab"
                :aria-selected="tab === 'password'"
                @click="tab = 'password'"
                :class="tab === 'password'
                    ? 'border-b-2 border-foreground text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground'"
                class="-mb-px pb-3 pt-1 px-4 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t">
            Zmień hasło
        </button>
    </div>

    <!-- Tab: Dane profilu -->
    <div x-show="tab === 'profile'" role="tabpanel">
        <div class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold text-card-foreground">Dane profilu</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">Zmień swoją nazwę wyświetlaną.</p>
            </div>
            <form method="POST" action="/profile" class="p-6 space-y-5" novalidate>
                <?= Csrf::hiddenInput() ?>

                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-foreground">
                        Imię i nazwisko <span class="text-destructive" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text" id="name" name="name"
                        value="<?= htmlspecialchars($user->name) ?>"
                        required minlength="2" maxlength="100"
                        class="w-full h-10 px-3 rounded-md border border-border bg-background
                               text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                               focus:border-transparent"
                        autocomplete="name"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-foreground">Email</label>
                    <p class="h-10 px-3 flex items-center rounded-md border border-border bg-muted
                               text-sm text-muted-foreground">
                        <?= htmlspecialchars($user->email) ?>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Aby zmienić email, przejdź do zakładki „Zmień email".
                    </p>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-md px-5 text-sm font-medium
                                   bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Zapisz zmiany
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab: Zmień email -->
    <div x-show="tab === 'email'" role="tabpanel">
        <div class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold text-card-foreground">Zmień adres email</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">Wymagane potwierdzenie hasłem.</p>
            </div>
            <form method="POST" action="/profile/email" class="p-6 space-y-5" novalidate>
                <?= Csrf::hiddenInput() ?>

                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-medium text-foreground">
                        Nowy adres email <span class="text-destructive" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="email" id="email" name="email"
                        value="<?= htmlspecialchars($user->email) ?>"
                        required
                        class="w-full h-10 px-3 rounded-md border border-border bg-background
                               text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                               focus:border-transparent"
                        autocomplete="email"
                    >
                </div>

                <div class="space-y-1.5">
                    <label for="email_password" class="block text-sm font-medium text-foreground">
                        Potwierdź hasłem <span class="text-destructive" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="password" id="email_password" name="password"
                        required
                        class="w-full h-10 px-3 rounded-md border border-border bg-background
                               text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                               focus:border-transparent"
                        autocomplete="current-password"
                        placeholder="Twoje aktualne hasło"
                    >
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-md px-5 text-sm font-medium
                                   bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Zmień email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab: Zmień hasło -->
    <div x-show="tab === 'password'" role="tabpanel">
        <div class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold text-card-foreground">Zmień hasło</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">Hasło musi mieć co najmniej 12 znaków, w tym dużą i małą literę oraz cyfrę lub znak specjalny.</p>
            </div>
            <form method="POST" action="/profile/password" class="p-6 space-y-5" novalidate>
                <?= Csrf::hiddenInput() ?>

                <div class="space-y-1.5">
                    <label for="current_password" class="block text-sm font-medium text-foreground">
                        Aktualne hasło <span class="text-destructive" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="password" id="current_password" name="current_password"
                        required
                        class="w-full h-10 px-3 rounded-md border border-border bg-background
                               text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                               focus:border-transparent"
                        autocomplete="current-password"
                    >
                </div>

                <div class="space-y-1.5">
                    <label for="new_password" class="block text-sm font-medium text-foreground">
                        Nowe hasło <span class="text-destructive" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="password" id="new_password" name="new_password"
                        required minlength="12"
                        class="w-full h-10 px-3 rounded-md border border-border bg-background
                               text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                               focus:border-transparent"
                        autocomplete="new-password"
                    >
                </div>

                <div class="space-y-1.5">
                    <label for="confirm_password" class="block text-sm font-medium text-foreground">
                        Powtórz nowe hasło <span class="text-destructive" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="password" id="confirm_password" name="confirm_password"
                        required minlength="12"
                        class="w-full h-10 px-3 rounded-md border border-border bg-background
                               text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring
                               focus:border-transparent"
                        autocomplete="new-password"
                    >
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-md px-5 text-sm font-medium
                                   bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Zmień hasło
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
