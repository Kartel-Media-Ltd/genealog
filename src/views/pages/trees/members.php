<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\Tree $tree */
/** @var array $members */
/** @var array $pendingInvitations */
/** @var array|null $currentUser */

$members            = $members            ?? [];
$pendingInvitations = $pendingInvitations ?? [];
$currentUser        = $currentUser        ?? ['name' => '', 'email' => ''];

$pageTitle = 'Zarządzaj dostępem — ' . htmlspecialchars($tree->name);

// Helper: inicjały z imienia
function member_initials(string $name): string {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $initials ?: '?';
}

// Helper: etykieta roli po polsku
function role_label(string $role): string {
    return match ($role) {
        'owner'  => 'Właściciel',
        'editor' => 'Edytor',
        'viewer' => 'Widz',
        default  => ucfirst($role),
    };
}

// Helper: klasy badge roli
function role_badge_classes(string $role): string {
    return match ($role) {
        'owner'  => 'bg-primary/10 text-primary',
        'editor' => 'bg-blue-100 text-blue-800',
        default  => 'bg-muted text-muted-foreground',
    };
}

// Oblicz dni do wygaśnięcia zaproszenia
function days_until(string $expiresAt): int {
    $diff = (new \DateTime($expiresAt))->diff(new \DateTime());
    return max(0, (int) $diff->days * ($diff->invert ? 1 : -1) + (int) $diff->days);
}
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <a href="/trees/<?= htmlspecialchars((string)$tree->id) ?>"
       class="hover:text-foreground transition-colors">
        <?= htmlspecialchars($tree->name) ?>
    </a>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span class="text-foreground font-medium">Zarządzaj dostępem</span>
</nav>

<!-- Nagłówek strony -->
<div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Zarządzaj dostępem</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            Kontroluj kto ma dostęp do drzewa <strong class="text-foreground font-medium"><?= htmlspecialchars($tree->name) ?></strong>
        </p>
    </div>
</div>

<!-- ================================================================
     SEKCJA 1: Aktualni członkowie
     ================================================================ -->
<div class="mb-6 rounded-lg border border-border bg-card shadow-sm">
    <div class="border-b border-border px-6 py-4 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
             class="text-muted-foreground">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <h2 class="text-base font-semibold text-card-foreground">
            Aktualni członkowie
        </h2>
        <span class="ml-auto inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                     bg-muted text-muted-foreground">
            <?= count($members) ?>
        </span>
    </div>

    <?php if (empty($members)): ?>
        <div class="px-6 py-10 text-center text-sm text-muted-foreground">
            Brak członków w tym drzewie.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border bg-muted/40">
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                            Użytkownik
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide hidden sm:table-cell">
                            Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                            Rola
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide hidden md:table-cell">
                            Dołączył
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wide">
                            Akcje
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($members as $member): ?>
                        <?php
                        $initials    = member_initials($member['name'] ?? '');
                        $role        = $member['role'] ?? 'viewer';
                        $isOwner     = $role === 'owner';
                        $joinedAt    = !empty($member['invited_at'])
                            ? date('j M Y', strtotime($member['invited_at']))
                            : '—';
                        ?>
                        <tr class="hover:bg-muted/30 transition-colors"
                            x-data="{ confirmDelete: false, roleDropdownOpen: false }">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <!-- Avatar z inicjałami -->
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center
                                                rounded-full bg-muted text-xs font-semibold
                                                text-muted-foreground select-none"
                                         aria-hidden="true">
                                        <?= htmlspecialchars($initials) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-foreground truncate">
                                            <?= htmlspecialchars($member['name'] ?? '—') ?>
                                        </p>
                                        <!-- Email tylko mobile -->
                                        <p class="text-xs text-muted-foreground truncate sm:hidden">
                                            <?= htmlspecialchars($member['email'] ?? '') ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground hidden sm:table-cell">
                                <?= htmlspecialchars($member['email'] ?? '—') ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5
                                             text-xs font-medium <?= role_badge_classes($role) ?>">
                                    <?php if ($isOwner): ?>
                                        <!-- Crown icon dla właściciela -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11"
                                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2.5" aria-hidden="true">
                                            <path d="M2 20h20"/>
                                            <path d="m2 10 5 7 5-9 5 9 5-7"/>
                                        </svg>
                                    <?php endif; ?>
                                    <?= role_label($role) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground hidden md:table-cell">
                                <?= $joinedAt ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($isOwner): ?>
                                    <!-- Właściciel — brak akcji -->
                                    <span class="text-xs text-muted-foreground italic">—</span>
                                <?php else: ?>
                                    <div class="flex items-center justify-end gap-2">

                                        <!-- Dropdown zmiany roli -->
                                        <div class="relative" @click.outside="roleDropdownOpen = false">
                                            <button
                                                type="button"
                                                @click="roleDropdownOpen = !roleDropdownOpen"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border
                                                       px-3 text-xs font-medium text-foreground
                                                       hover:bg-accent transition-colors
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                aria-haspopup="true"
                                                :aria-expanded="roleDropdownOpen">
                                                Zmień rolę
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                     stroke-width="2" aria-hidden="true">
                                                    <polyline points="6 9 12 15 18 9"/>
                                                </svg>
                                            </button>

                                            <!-- Dropdown menu -->
                                            <div
                                                x-show="roleDropdownOpen"
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="opacity-100 scale-100"
                                                x-transition:leave-end="opacity-0 scale-95"
                                                style="display:none"
                                                class="absolute right-0 z-10 mt-1 w-44 rounded-md border border-border
                                                       bg-popover shadow-md overflow-hidden">
                                                <?php foreach (['editor' => 'Edytor', 'viewer' => 'Widz'] as $newRole => $newLabel): ?>
                                                    <?php if ($newRole !== $role): ?>
                                                        <form method="POST"
                                                              action="/trees/<?= htmlspecialchars((string)$tree->id) ?>/members/<?= htmlspecialchars((string)$member['user_id']) ?>/role">
                                                            <?= Csrf::hiddenInput() ?>
                                                            <input type="hidden" name="role" value="<?= htmlspecialchars($newRole) ?>">
                                                            <button type="submit"
                                                                    class="w-full px-4 py-2.5 text-left text-sm text-foreground
                                                                           hover:bg-accent transition-colors flex items-center gap-2">
                                                                <?php if ($newRole === 'editor'): ?>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                         stroke-width="2" aria-hidden="true">
                                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                                    </svg>
                                                                <?php else: ?>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                         stroke-width="2" aria-hidden="true">
                                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                                        <circle cx="12" cy="12" r="3"/>
                                                                    </svg>
                                                                <?php endif; ?>
                                                                <?= htmlspecialchars($newLabel) ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Przycisk Usuń z potwierdzeniem -->
                                        <div x-show="!confirmDelete">
                                            <button
                                                type="button"
                                                @click="confirmDelete = true"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border
                                                       px-3 text-xs font-medium text-destructive
                                                       hover:bg-destructive/10 transition-colors
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                     stroke-width="2" aria-hidden="true">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                                    <path d="M10 11v6"/>
                                                    <path d="M14 11v6"/>
                                                </svg>
                                                Usuń
                                            </button>
                                        </div>

                                        <!-- Potwierdzenie usunięcia (inline) -->
                                        <div x-show="confirmDelete" style="display:none"
                                             class="flex items-center gap-2">
                                            <span class="text-xs text-muted-foreground">Na pewno?</span>
                                            <form method="POST"
                                                  action="/trees/<?= htmlspecialchars((string)$tree->id) ?>/members/<?= htmlspecialchars((string)$member['user_id']) ?>/remove">
                                                <?= Csrf::hiddenInput() ?>
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button type="submit"
                                                        class="inline-flex h-8 items-center rounded-md px-3 text-xs font-medium
                                                               bg-destructive text-destructive-foreground
                                                               hover:bg-destructive/90 transition-colors
                                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                    Tak, usuń
                                                </button>
                                            </form>
                                            <button type="button"
                                                    @click="confirmDelete = false"
                                                    class="inline-flex h-8 items-center rounded-md border border-border
                                                           px-3 text-xs font-medium text-foreground
                                                           hover:bg-accent transition-colors
                                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                Anuluj
                                            </button>
                                        </div>

                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ================================================================
     SEKCJA 2: Oczekujące zaproszenia
     ================================================================ -->
<?php if (!empty($pendingInvitations)): ?>
<div class="mb-6 rounded-lg border border-border bg-card shadow-sm">
    <div class="border-b border-border px-6 py-4 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
             class="text-muted-foreground">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
        </svg>
        <h2 class="text-base font-semibold text-card-foreground">
            Oczekujące zaproszenia
        </h2>
        <span class="ml-auto inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                     bg-yellow-100 text-yellow-800">
            <?= count($pendingInvitations) ?>
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                        Email
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                        Rola
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                        Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                        Wygasa za
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <?php foreach ($pendingInvitations as $invitation): ?>
                    <?php
                    $expiresAt = $invitation['expires_at'] ?? '';
                    $daysLeft  = $expiresAt ? (int) ceil((strtotime($expiresAt) - time()) / 86400) : null;
                    $daysText  = $daysLeft !== null
                        ? ($daysLeft <= 0 ? 'Wygasło' : $daysLeft . ' ' . ($daysLeft === 1 ? 'dzień' : 'dni'))
                        : '—';
                    $expired   = $daysLeft !== null && $daysLeft <= 0;
                    ?>
                    <tr class="hover:bg-muted/30 transition-colors">
                        <td class="px-6 py-4 text-foreground font-medium">
                            <?= htmlspecialchars($invitation['invited_email'] ?? '—') ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5
                                         text-xs font-medium <?= role_badge_classes($invitation['role'] ?? 'viewer') ?>">
                                <?= role_label($invitation['role'] ?? 'viewer') ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5
                                         text-xs font-medium
                                         <?= $expired ? 'bg-muted text-muted-foreground' : 'bg-yellow-100 text-yellow-800' ?>">
                                <?= $expired ? 'Wygasło' : 'Oczekuje' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm <?= $expired ? 'text-destructive' : 'text-muted-foreground' ?>">
                            <?= htmlspecialchars($daysText) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================
     SEKCJA 3: Zaproś osobę
     ================================================================ -->
<div class="rounded-lg border border-border bg-card shadow-sm">
    <div class="border-b border-border px-6 py-4 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
             class="text-muted-foreground">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="16"/>
            <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        <h2 class="text-base font-semibold text-card-foreground">Zaproś osobę</h2>
    </div>

    <div class="p-6">
        <p class="mb-5 text-sm text-muted-foreground">
            Wyślij zaproszenie e-mailem. Osoba otrzyma link ważny przez 7 dni.
        </p>

        <form method="POST"
              action="/trees/<?= htmlspecialchars((string)$tree->id) ?>/invite"
              class="flex flex-col gap-4 sm:flex-row sm:items-end"
              x-data="{ loading: false }"
              @submit="loading = true">
            <?= Csrf::hiddenInput() ?>

            <!-- Email -->
            <div class="flex-1 space-y-1.5">
                <label for="invite-email"
                       class="block text-sm font-medium text-foreground">
                    Adres e-mail <span class="text-destructive" aria-hidden="true">*</span>
                </label>
                <input
                    type="email"
                    id="invite-email"
                    name="email"
                    required
                    placeholder="anna@example.com"
                    autocomplete="off"
                    class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                           text-foreground text-sm placeholder:text-muted-foreground
                           focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent
                           disabled:cursor-not-allowed disabled:opacity-50">
            </div>

            <!-- Rola -->
            <div class="space-y-1.5 sm:w-48">
                <label for="invite-role"
                       class="block text-sm font-medium text-foreground">
                    Rola
                </label>
                <select
                    id="invite-role"
                    name="role"
                    class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                           text-foreground text-sm
                           focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent
                           disabled:cursor-not-allowed disabled:opacity-50">
                    <option value="editor">Edytor — może edytować</option>
                    <option value="viewer">Widz — tylko podgląd</option>
                </select>
            </div>

            <!-- Przycisk Zaproś -->
            <button
                type="submit"
                :disabled="loading"
                class="h-10 inline-flex items-center justify-center gap-2 rounded-md px-5
                       text-sm font-medium bg-primary text-primary-foreground
                       hover:bg-primary/90 transition-colors
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring
                       disabled:pointer-events-none disabled:opacity-50 shrink-0">
                <!-- Spinner podczas wysyłania -->
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
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                <span x-text="loading ? 'Wysyłanie...' : 'Zaproś'">Zaproś</span>
            </button>

        </form>
    </div>
</div>
