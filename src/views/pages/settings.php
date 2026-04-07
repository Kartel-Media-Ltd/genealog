<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\User $user */
$activeTab = $_GET['tab'] ?? 'notifications';
$localeLabels = ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'uk' => 'Українська'];
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/dashboard" class="hover:text-foreground transition-colors">Dashboard</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span class="text-foreground font-medium">Ustawienia</span>
</nav>

<div class="mx-auto max-w-2xl"
     x-data="{ tab: '<?= htmlspecialchars($activeTab) ?>' }">

    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Ustawienia konta</h1>
        <p class="mt-1 text-sm text-muted-foreground">Zarządzaj powiadomieniami, językiem i kontem.</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-border mb-6" role="tablist">
        <button type="button" role="tab"
                :aria-selected="tab === 'notifications'"
                @click="tab = 'notifications'"
                :class="tab === 'notifications'
                    ? 'border-b-2 border-foreground text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground'"
                class="-mb-px pb-3 pt-1 px-1 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t">
            Powiadomienia
        </button>
        <button type="button" role="tab"
                :aria-selected="tab === 'locale'"
                @click="tab = 'locale'"
                :class="tab === 'locale'
                    ? 'border-b-2 border-foreground text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground'"
                class="-mb-px pb-3 pt-1 px-4 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t">
            Język
        </button>
        <button type="button" role="tab"
                :aria-selected="tab === 'danger'"
                @click="tab = 'danger'"
                :class="tab === 'danger'
                    ? 'border-b-2 border-red-600 text-red-600 font-medium'
                    : 'text-muted-foreground hover:text-red-600'"
                class="-mb-px pb-3 pt-1 px-4 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t">
            Strefa niebezpieczna
        </button>
    </div>

    <!-- Tab: Powiadomienia -->
    <div x-show="tab === 'notifications'" role="tabpanel">
        <div class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold text-card-foreground">Powiadomienia email</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Otrzymuj emaile o aktywnościach w Twoich drzewach.
                </p>
            </div>
            <form method="POST" action="/settings/notifications" class="p-6">
                <?= Csrf::hiddenInput() ?>

                <label class="flex items-start gap-4 cursor-pointer group">
                    <div class="relative mt-0.5 flex-shrink-0">
                        <input
                            type="checkbox"
                            name="email_notifications"
                            value="1"
                            <?= $user->emailNotifications ? 'checked' : '' ?>
                            class="peer sr-only"
                            id="email_notifications"
                        >
                        <div class="h-6 w-11 rounded-full border-2 border-border bg-muted transition-colors
                                    peer-checked:bg-primary peer-checked:border-primary
                                    peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2">
                        </div>
                        <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform
                                    peer-checked:translate-x-5">
                        </div>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-foreground block">Powiadomienia email</span>
                        <span class="text-sm text-muted-foreground">
                            Zaproszenia do drzew, zmiany współdzielonych danych, dopasowania rodzinne.
                        </span>
                    </div>
                </label>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-md px-5 text-sm font-medium
                                   bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Zapisz ustawienia
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab: Język -->
    <div x-show="tab === 'locale'" role="tabpanel">
        <div class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold text-card-foreground">Język interfejsu</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Wybierz preferowany język aplikacji.
                </p>
            </div>
            <form method="POST" action="/settings/locale" class="p-6 space-y-5">
                <?= Csrf::hiddenInput() ?>

                <div class="space-y-3">
                    <?php foreach ($localeLabels as $code => $label): ?>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="radio"
                                name="locale"
                                value="<?= htmlspecialchars($code) ?>"
                                <?= $user->locale === $code ? 'checked' : '' ?>
                                class="h-4 w-4 border-border text-primary focus:ring-ring"
                            >
                            <span class="text-sm text-foreground"><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-md px-5 text-sm font-medium
                                   bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Zmień język
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab: Strefa niebezpieczna -->
    <div x-show="tab === 'danger'" role="tabpanel"
         x-data="{ confirm: false }">
        <div class="rounded-lg border border-red-200 bg-card shadow-sm">
            <div class="border-b border-red-200 bg-red-50 px-6 py-4 rounded-t-lg">
                <h2 class="text-base font-semibold text-red-800">Usuń konto</h2>
                <p class="mt-0.5 text-sm text-red-700">
                    Ta operacja jest nieodwracalna. Twoje konto zostanie dezaktywowane.
                    Dane genealogiczne w drzewach pozostaną zachowane.
                </p>
            </div>

            <div class="p-6" x-show="!confirm">
                <p class="text-sm text-muted-foreground mb-4">
                    Po usunięciu konta stracisz dostęp do wszystkich swoich drzew genealogicznych.
                    Jeśli jesteś jedynym właścicielem, nikt inny nie będzie mógł ich edytować.
                </p>
                <button type="button"
                        @click="confirm = true"
                        class="inline-flex h-10 items-center rounded-md px-5 text-sm font-medium
                               border border-red-300 text-red-700 hover:bg-red-50 transition-colors
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    Chcę usunąć swoje konto
                </button>
            </div>

            <div x-show="confirm" x-cloak>
                <form method="POST" action="/settings/delete" class="p-6 space-y-5">
                    <?= Csrf::hiddenInput() ?>

                    <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        Aby potwierdzić usunięcie konta, wpisz poniżej:
                        <strong>USUŃ KONTO</strong>
                    </div>

                    <div class="space-y-1.5">
                        <label for="confirmation" class="block text-sm font-medium text-foreground">
                            Wpisz potwierdzenie
                        </label>
                        <input
                            type="text" id="confirmation" name="confirmation"
                            placeholder="USUŃ KONTO"
                            required
                            class="w-full h-10 px-3 rounded-md border border-border bg-background
                                   text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-red-500
                                   focus:border-transparent"
                            autocomplete="off"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label for="delete_password" class="block text-sm font-medium text-foreground">
                            Hasło <span class="text-destructive" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="password" id="delete_password" name="password"
                            required
                            class="w-full h-10 px-3 rounded-md border border-border bg-background
                                   text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-red-500
                                   focus:border-transparent"
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit"
                                class="inline-flex h-10 items-center rounded-md px-5 text-sm font-medium
                                       bg-red-600 text-white hover:bg-red-700 transition-colors
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                            Trwale usuń konto
                        </button>
                        <button type="button"
                                @click="confirm = false"
                                class="inline-flex h-10 items-center rounded-md px-4 text-sm font-medium
                                       border border-border text-foreground hover:bg-accent transition-colors">
                            Anuluj
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
