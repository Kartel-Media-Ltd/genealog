<?php
declare(strict_types=1);
use App\Core\Csrf;
use App\Core\Session;
// Variables: $user (array), $trees (array[])
$csrfToken  = Csrf::token();
$currentAdminId = Session::get('user_id');
$isCurrentAdmin = ($user['id'] === $currentAdminId);
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- User card -->
    <div class="lg:col-span-1 space-y-4">
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <!-- Avatar + info -->
            <div class="flex items-center gap-4 mb-5">
                <div class="h-14 w-14 rounded-full bg-slate-200 flex items-center justify-center text-xl font-bold text-slate-600">
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-slate-800">
                        <?= htmlspecialchars($user['name']) ?>
                    </h2>
                    <p class="text-sm text-slate-500"><?= htmlspecialchars($user['email']) ?></p>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        <?php if ($user['is_admin']): ?>
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Admin</span>
                        <?php endif; ?>
                        <?php if ($user['is_blocked']): ?>
                            <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Zablokowany</span>
                        <?php elseif (!$user['is_admin']): ?>
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktywny</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Meta -->
            <dl class="space-y-2 text-sm border-t border-slate-100 pt-4">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Dołączył</dt>
                    <dd class="text-slate-700"><?= htmlspecialchars((new \DateTime($user['created_at']))->format('d.m.Y')) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Drzewa</dt>
                    <dd class="text-slate-700"><?= (int)$user['trees_count'] ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">ID</dt>
                    <dd class="text-slate-400 text-xs font-mono"><?= htmlspecialchars(substr($user['id'], 0, 8)) ?>…</dd>
                </div>
            </dl>
        </div>

        <?php if (!$isCurrentAdmin): ?>
        <!-- Actions -->
        <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-2">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Akcje</h3>

            <!-- Block / Unblock -->
            <?php if ($user['is_blocked']): ?>
                <form method="POST" action="/admin/users/<?= htmlspecialchars($user['id']) ?>/unblock">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit"
                            class="w-full rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700 hover:bg-green-100 transition-colors">
                        Odblokuj konto
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="/admin/users/<?= htmlspecialchars($user['id']) ?>/block"
                      x-data onsubmit="return confirm('Na pewno zablokować konto?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit"
                            class="w-full rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 hover:bg-red-100 transition-colors">
                        Zablokuj konto
                    </button>
                </form>
            <?php endif; ?>

            <!-- Promote / Demote -->
            <?php if ($user['is_admin']): ?>
                <form method="POST" action="/admin/users/<?= htmlspecialchars($user['id']) ?>/demote"
                      onsubmit="return confirm('Cofnąć uprawnienia admina?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit"
                            class="w-full rounded-md border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-700 hover:bg-orange-100 transition-colors">
                        Cofnij uprawnienia admina
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="/admin/users/<?= htmlspecialchars($user['id']) ?>/promote"
                      onsubmit="return confirm('Mianować użytkownika adminem?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit"
                            class="w-full rounded-md border border-blue-300 bg-blue-50 px-3 py-2 text-sm text-blue-700 hover:bg-blue-100 transition-colors">
                        Mianuj administratorem
                    </button>
                </form>

                <!-- Impersonate — only for non-blocked, non-admin -->
                <?php if (!$user['is_blocked']): ?>
                    <form method="POST" action="/admin/users/<?= htmlspecialchars($user['id']) ?>/impersonate"
                          onsubmit="return confirm('Zalogować się jako ten użytkownik? Sesja zostanie przełączona.')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit"
                                class="w-full rounded-md border border-purple-300 bg-purple-50 px-3 py-2 text-sm text-purple-700 hover:bg-purple-100 transition-colors">
                            Zaloguj jako ten użytkownik
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($isCurrentAdmin): ?>
        <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            To jest Twoje własne konto — akcje niedostępne.
        </div>
        <?php endif; ?>
    </div>

    <!-- Trees list -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-sm font-semibold text-slate-700">
                    Drzewa genealogiczne (<?= count($trees) ?>)
                </h3>
            </div>

            <?php if (empty($trees)): ?>
                <div class="px-5 py-8 text-center text-sm text-slate-500">
                    Użytkownik nie ma jeszcze żadnych drzew.
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Nazwa</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Osoby</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Widoczność</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Aktualizacja</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($trees as $tree): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <a href="/trees/<?= htmlspecialchars($tree['id']) ?>"
                                       target="_blank"
                                       class="font-medium text-blue-600 hover:text-blue-700">
                                        <?= htmlspecialchars($tree['name']) ?>
                                    </a>
                                    <?php if ($tree['description']): ?>
                                        <p class="text-xs text-slate-400 truncate max-w-xs">
                                            <?= htmlspecialchars($tree['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-slate-600"><?= (int)$tree['persons_count'] ?></td>
                                <td class="px-5 py-3">
                                    <?php if ($tree['is_public']): ?>
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Publiczne</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Prywatne</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars((new \DateTime($tree['updated_at'] ?? $tree['created_at']))->format('d.m.Y')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
