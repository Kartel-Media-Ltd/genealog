<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../atoms/icon.php';

/** @var array[] $invitations */
$invitations ??= [];
?>

<div class="mx-auto max-w-2xl">

    <!-- Nagłówek -->
    <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100">
            <?php render_icon('users', 'solid', 'h-8 w-8 text-blue-600') ?>
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Oczekujące zaproszenia</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            Poniżej znajdziesz zaproszenia do drzew genealogicznych wysłane na Twój adres e-mail.
        </p>
    </div>

    <?php if (empty($invitations)): ?>
        <div class="rounded-lg border border-dashed border-border bg-card py-16 text-center">
            <p class="text-sm text-muted-foreground">Brak oczekujących zaproszeń.</p>
            <a href="/trees"
               class="mt-4 inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium
                      text-primary-foreground hover:bg-primary/90 transition-colors">
                Przejdź do moich drzew
            </a>
        </div>

    <?php else: ?>
        <ul class="space-y-4">
            <?php foreach ($invitations as $inv): ?>
                <li class="rounded-lg border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-foreground">
                                <?= htmlspecialchars($inv['tree_name'] ?? 'Drzewo genealogiczne') ?>
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Zaproszono Cię jako:
                                <span class="font-medium text-foreground">
                                    <?= match($inv['role']) {
                                        'editor' => 'Edytor',
                                        'viewer' => 'Przeglądający',
                                        default  => htmlspecialchars($inv['role']),
                                    } ?>
                                </span>
                                <?php if (!empty($inv['inviter_name'])): ?>
                                    &middot; przez <?= htmlspecialchars($inv['inviter_name']) ?>
                                <?php endif; ?>
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Wygasa: <?= date('j M Y', strtotime($inv['expires_at'])) ?>
                            </p>
                        </div>

                        <form method="POST"
                              action="/invite/<?= htmlspecialchars($inv['token']) ?>/accept">
                            <?= Csrf::hiddenInput() ?>
                            <button type="submit"
                                    class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm
                                           font-medium text-primary-foreground hover:bg-primary/90
                                           transition-colors whitespace-nowrap">
                                Dołącz do drzewa
                            </button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="mt-6 text-center">
            <a href="/trees"
               class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                Pomiń i przejdź do moich drzew →
            </a>
        </div>
    <?php endif; ?>

</div>
