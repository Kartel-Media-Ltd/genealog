<?php
declare(strict_types=1);
use App\Core\Csrf;

/**
 * Page: Akceptacja zaproszenia do drzewa
 *
 * Zmienne z kontrolera:
 *   $invitation  — array (invited_email, role, tree_id, invited_by, expires_at)
 *   $treeName    — string
 *   $inviterName — string
 *   $token       — string
 *   $isLoggedIn  — bool
 *   $currentUser — array|null
 *
 * Layout decyduje kontroler:
 *   - niezalogowany: templates/AuthLayout
 *   - zalogowany:    templates/AppLayout
 */

$invitation  = $invitation  ?? [];
$treeName    = $treeName    ?? '';
$inviterName = $inviterName ?? '';
$token       = $token       ?? '';
$isLoggedIn  = $isLoggedIn  ?? false;
$currentUser = $currentUser ?? null;

$role        = $invitation['role'] ?? 'viewer';
$expiresAt   = $invitation['expires_at'] ?? null;

$roleLabel   = match ($role) {
    'editor' => 'Edytor',
    'viewer' => 'Widz',
    default  => ucfirst($role),
};

$roleDescription = match ($role) {
    'editor' => 'Możesz przeglądać i edytować osoby w tym drzewie.',
    'viewer' => 'Możesz przeglądać drzewo bez możliwości edycji.',
    default  => '',
};

$daysLeft = null;
if ($expiresAt) {
    $daysLeft = (int) ceil((strtotime($expiresAt) - time()) / 86400);
}

$pageTitle = 'Zaproszenie do drzewa — ' . htmlspecialchars($treeName);
?>

<?php if ($isLoggedIn): ?>
<!-- ────────────────────────────────────────────────────────────────
     WARIANT: Zalogowany użytkownik (AppLayout)
     ──────────────────────────────────────────────────────────────── -->
<div class="mx-auto max-w-lg py-8">

    <!-- Karta zaproszenia -->
    <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">

        <!-- Ikonka + nagłówek -->
        <div class="border-b border-border bg-muted/30 px-6 py-8 flex flex-col items-center text-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full
                        bg-primary/10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
            </div>

            <div>
                <h1 class="text-xl font-bold text-foreground">
                    <?= htmlspecialchars($inviterName) ?> zaprasza Cię do drzewa
                </h1>
                <p class="mt-1 text-lg font-semibold text-primary">
                    <?= htmlspecialchars($treeName) ?>
                </p>
            </div>
        </div>

        <!-- Szczegóły zaproszenia -->
        <div class="px-6 py-6 space-y-5">

            <!-- Rola -->
            <div class="flex items-start gap-4 rounded-md border border-border bg-muted/20 px-4 py-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center
                            rounded-full bg-primary/10 text-primary">
                    <?php if ($role === 'editor'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="text-sm font-medium text-foreground">
                        Twoja rola: <span class="text-primary"><?= htmlspecialchars($roleLabel) ?></span>
                    </p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        <?= htmlspecialchars($roleDescription) ?>
                    </p>
                </div>
            </div>

            <!-- Czas wygaśnięcia -->
            <?php if ($daysLeft !== null): ?>
                <div class="flex items-center gap-2 text-sm <?= $daysLeft <= 1 ? 'text-destructive' : 'text-muted-foreground' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <?php if ($daysLeft <= 0): ?>
                        Zaproszenie wygasło.
                    <?php elseif ($daysLeft === 1): ?>
                        Zaproszenie wygasa <strong>jutro</strong> — przyjmij je dzisiaj.
                    <?php else: ?>
                        Zaproszenie wygasa za <strong><?= $daysLeft ?> dni</strong>
                        (<?= htmlspecialchars(date('j M Y', strtotime($expiresAt))) ?>).
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Formularz akceptacji -->
            <?php if ($daysLeft === null || $daysLeft > 0): ?>
                <form method="POST" action="/invite/<?= htmlspecialchars($token) ?>/accept"
                      x-data="{ loading: false }" @submit="loading = true">
                    <?= Csrf::hiddenInput() ?>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full inline-flex h-11 items-center justify-center gap-2
                               rounded-md px-4 text-sm font-medium
                               bg-primary text-primary-foreground
                               hover:bg-primary/90 transition-colors
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring
                               disabled:pointer-events-none disabled:opacity-50">
                        <template x-if="loading">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg"
                                 fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <svg x-show="!loading" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span x-text="loading ? 'Dołączanie...' : 'Dołącz do drzewa'">Dołącz do drzewa</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                    To zaproszenie wygasło i nie może już zostać przyjęte. Poproś właściciela drzewa o nowe zaproszenie.
                </div>
            <?php endif; ?>

        </div>

        <!-- Stopka karty -->
        <div class="border-t border-border bg-muted/20 px-6 py-4 text-center">
            <a href="/trees"
               class="text-sm text-muted-foreground hover:text-foreground transition-colors
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded">
                Wróć do moich drzew
            </a>
        </div>
    </div>

</div>

<?php else: ?>
<!-- ────────────────────────────────────────────────────────────────
     WARIANT: Niezalogowany użytkownik (AuthLayout)
     ──────────────────────────────────────────────────────────────── -->

<div class="rounded-lg border border-[hsl(var(--border))]
            bg-[hsl(var(--card))] text-[hsl(var(--card-foreground))] shadow-sm">

    <!-- Ikonka + nagłówek -->
    <div class="flex flex-col gap-1.5 p-6 items-center text-center border-b border-[hsl(var(--border))]">
        <div class="mb-2 flex h-14 w-14 items-center justify-center rounded-full
                    bg-[hsl(var(--primary)/0.1)] text-[hsl(var(--primary))]">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        </div>

        <h1 class="text-xl font-semibold text-[hsl(var(--foreground))]">
            <?= htmlspecialchars($inviterName) ?> zaprasza Cię do drzewa
        </h1>
        <p class="text-base font-bold text-[hsl(var(--primary))]">
            <?= htmlspecialchars($treeName) ?>
        </p>
    </div>

    <!-- Szczegóły -->
    <div class="p-6 space-y-4">

        <!-- Rola -->
        <div class="flex items-start gap-3 rounded-md border border-[hsl(var(--border))]
                    bg-[hsl(var(--muted)/0.5)] px-4 py-3">
            <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center
                        rounded-full bg-[hsl(var(--primary)/0.1)] text-[hsl(var(--primary))]">
                <?php if ($role === 'editor'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-sm font-medium text-[hsl(var(--foreground))]">
                    Rola: <span class="text-[hsl(var(--primary))]"><?= htmlspecialchars($roleLabel) ?></span>
                </p>
                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">
                    <?= htmlspecialchars($roleDescription) ?>
                </p>
            </div>
        </div>

        <!-- Czas wygaśnięcia -->
        <?php if ($daysLeft !== null): ?>
            <p class="flex items-center gap-2 text-sm
                      <?= $daysLeft <= 1 ? 'text-[hsl(var(--destructive))]' : 'text-[hsl(var(--muted-foreground))]' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <?php if ($daysLeft <= 0): ?>
                    Zaproszenie wygasło.
                <?php elseif ($daysLeft === 1): ?>
                    Zaproszenie wygasa <strong>jutro</strong>.
                <?php else: ?>
                    Zaproszenie ważne jeszcze przez <strong><?= $daysLeft ?> dni</strong>.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <!-- Info o zapisaniu tokenu -->
        <div class="rounded-md border border-[hsl(var(--border))]
                    bg-[hsl(var(--muted))] px-4 py-3 text-sm text-[hsl(var(--muted-foreground))]
                    flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5"
                 aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            Po zalogowaniu lub rejestracji automatycznie dołączysz do drzewa.
        </div>

        <!-- Przyciski logowania / rejestracji -->
        <?php if ($daysLeft === null || $daysLeft > 0): ?>
            <div class="flex flex-col gap-3 pt-1">

                <!-- Mam konto — zaloguj się -->
                <a href="/login?redirect=<?= urlencode('/invite/' . $token . '/accept') ?>"
                   class="w-full inline-flex h-11 items-center justify-center gap-2
                          rounded-md px-4 text-sm font-medium
                          bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]
                          hover:bg-[hsl(var(--primary)/0.9)] transition-colors
                          focus-visible:outline-none focus-visible:ring-2
                          focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Mam już konto — Zaloguj się
                </a>

                <!-- Separator -->
                <div class="flex items-center gap-3">
                    <div class="flex-1 border-t border-[hsl(var(--border))]"></div>
                    <span class="text-xs text-[hsl(var(--muted-foreground))]">lub</span>
                    <div class="flex-1 border-t border-[hsl(var(--border))]"></div>
                </div>

                <!-- Utwórz konto -->
                <a href="/register?redirect=<?= urlencode('/invite/' . $token . '/accept') ?>"
                   class="w-full inline-flex h-11 items-center justify-center gap-2
                          rounded-md border border-[hsl(var(--border))] px-4 text-sm font-medium
                          text-[hsl(var(--foreground))] bg-[hsl(var(--background))]
                          hover:bg-[hsl(var(--accent))] transition-colors
                          focus-visible:outline-none focus-visible:ring-2
                          focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Utwórz konto
                </a>

            </div>
        <?php else: ?>
            <div class="rounded-md border border-[hsl(var(--destructive)/0.3)]
                        bg-[hsl(var(--destructive)/0.05)] px-4 py-3
                        text-sm text-[hsl(var(--destructive))]">
                To zaproszenie wygasło. Poproś właściciela drzewa o nowe zaproszenie.
            </div>
        <?php endif; ?>

    </div>

</div>

<?php endif; ?>
