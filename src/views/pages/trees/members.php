<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../atoms/icon.php';

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
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars((string)$tree->id) ?>"
       class="hover:text-foreground transition-colors">
        <?= htmlspecialchars($tree->name) ?>
    </a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
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
        <?php render_icon('users', 'solid', 'h-4.5 w-4.5 text-muted-foreground') ?>
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
                                        <?php render_icon('crown', 'solid', 'h-2.5 w-2.5') ?>
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
                                                <?php render_icon('chevron-down', 'solid', 'h-3 w-3') ?>
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
                                                                    <?php render_icon('pen-to-square', 'solid', 'h-3.5 w-3.5') ?>
                                                                <?php else: ?>
                                                                    <?php render_icon('eye', 'solid', 'h-3.5 w-3.5') ?>
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
                                                <?php render_icon('trash', 'solid', 'h-3 w-3') ?>
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
        <?php render_icon('envelope', 'solid', 'h-4.5 w-4.5 text-muted-foreground') ?>
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
        <?php render_icon('user-plus', 'solid', 'h-4.5 w-4.5 text-muted-foreground') ?>
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
                    <?php render_icon('spinner', 'solid', 'fa-spin h-4 w-4') ?>
                </template>
                <span x-show="!loading"><?php render_icon('paper-plane', 'solid', 'h-4 w-4') ?></span>
                <span x-text="loading ? 'Wysyłanie...' : 'Zaproś'">Zaproś</span>
            </button>

        </form>
    </div>
</div>
