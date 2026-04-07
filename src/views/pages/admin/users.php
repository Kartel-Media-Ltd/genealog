<?php
declare(strict_types=1);
use App\Core\Csrf;
// Variables: $users (array[]), $search (string), $page (int), $total (int), $limit (int)
$totalPages = (int)ceil($total / $limit);
$csrfToken  = Csrf::token();
?>
<div x-data="{ search: <?= json_encode($search) ?> }">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <form method="GET" action="/admin/users" class="flex-1">
            <div class="relative max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Szukaj po imieniu lub e-mailu…"
                    class="w-full rounded-md border border-slate-300 pl-9 pr-4 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>
        </form>
        <p class="text-sm text-slate-500 self-center">
            <?= number_format($total) ?> <?= $total === 1 ? 'użytkownik' : 'użytkowników' ?>
        </p>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <?php if (empty($users)): ?>
            <div class="px-5 py-12 text-center text-sm text-slate-500">
                Brak użytkowników spełniających kryteria wyszukiwania.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Użytkownik</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Status</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Drzewa</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Dołączył</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-800">
                                        <?= htmlspecialchars($user['name']) ?>
                                    </div>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($user['email']) ?></div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <?php if ($user['is_blocked']): ?>
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                                Zablokowany
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                Admin
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!$user['is_blocked'] && !$user['is_admin']): ?>
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                                Aktywny
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    <?= (int)$user['trees_count'] ?>
                                </td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars((new \DateTime($user['created_at']))->format('d.m.Y')) ?>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="/admin/users/<?= htmlspecialchars($user['id']) ?>"
                                       class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                        Szczegóły →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
            <p>Strona <?= $page ?> z <?= $totalPages ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                       class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">
                        ← Poprzednia
                    </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                       class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">
                        Następna →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
