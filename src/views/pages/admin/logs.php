<?php
declare(strict_types=1);
// Variables: $logs, $page, $total, $limit, $filters, $availableActions
$totalPages = (int)ceil($total / $limit);
$filters    = $filters ?? [];
$availableActions = $availableActions ?? [];

// Helper: czy jakikolwiek filtr aktywny?
$hasActiveFilters = !empty(array_filter($filters, fn($v) => $v !== ''));
?>
<!-- Filtry -->
<form method="GET" action="/admin/logs" class="mb-4 rounded-lg border border-slate-200 bg-white p-4">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Akcja</label>
            <select name="action" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">— wszystkie —</option>
                <?php foreach ($availableActions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Admin ID</label>
            <input type="text" name="admin_id"
                   value="<?= htmlspecialchars($filters['admin_id'] ?? '') ?>"
                   placeholder="UUID..."
                   class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm font-mono">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Target ID</label>
            <input type="text" name="target_id"
                   value="<?= htmlspecialchars($filters['target_id'] ?? '') ?>"
                   placeholder="UUID..."
                   class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm font-mono">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Od daty</label>
            <input type="date" name="date_from"
                   value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>"
                   class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Do daty</label>
            <input type="date" name="date_to"
                   value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>"
                   class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
        </div>
    </div>
    <div class="mt-3 flex gap-2">
        <button type="submit"
                class="rounded-md bg-slate-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
            Filtruj
        </button>
        <?php if ($hasActiveFilters): ?>
            <a href="/admin/logs" class="rounded-md border border-slate-300 px-4 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Wyczyść
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500"><?= number_format($total) ?> wpisów<?= $hasActiveFilters ? ' (filtrowanych)' : '' ?></p>
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <?php if (empty($logs)): ?>
        <div class="px-5 py-12 text-center text-sm text-slate-500">Brak wpisów w logu.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Czas</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Admin</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Akcja</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Typ celu</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">ID celu</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Metadane</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">
                                <?= htmlspecialchars((new \DateTime($log['created_at']))->format('d.m.Y H:i:s')) ?>
                            </td>
                            <td class="px-5 py-3">
                                <div class="text-slate-700"><?= htmlspecialchars($log['admin_name'] ?? '—') ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($log['admin_email'] ?? '') ?></div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    <?= match($log['action']) {
                                        'block'             => 'bg-red-100 text-red-700',
                                        'unblock'           => 'bg-green-100 text-green-700',
                                        'promote'           => 'bg-blue-100 text-blue-700',
                                        'demote'            => 'bg-orange-100 text-orange-700',
                                        'impersonate_start' => 'bg-purple-100 text-purple-700',
                                        'impersonate_end'   => 'bg-slate-100 text-slate-700',
                                        default             => 'bg-slate-100 text-slate-600',
                                    } ?>">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-500">
                                <?= htmlspecialchars($log['target_type'] ?? '—') ?>
                            </td>
                            <td class="px-5 py-3">
                                <?php if ($log['target_type'] === 'user' && $log['target_id']): ?>
                                    <a href="/admin/users/<?= htmlspecialchars($log['target_id']) ?>"
                                       class="font-mono text-xs text-blue-600 hover:underline">
                                        <?= htmlspecialchars(substr($log['target_id'], 0, 8)) ?>…
                                    </a>
                                <?php else: ?>
                                    <span class="font-mono text-xs text-slate-400">
                                        <?= $log['target_id'] ? htmlspecialchars(substr($log['target_id'], 0, 8)) . '…' : '—' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-400 max-w-xs truncate">
                                <?php if ($log['meta']): ?>
                                    <?php $meta = json_decode((string)$log['meta'], true); ?>
                                    <?= $meta ? htmlspecialchars(implode(', ', array_map(
                                        fn($k, $v) => $k . ': ' . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v),
                                        array_keys($meta), array_values($meta)
                                    ))) : htmlspecialchars((string)$log['meta']) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
        <p>Strona <?= $page ?> z <?= $totalPages ?></p>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">← Poprzednia</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Następna →</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
