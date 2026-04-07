<?php
declare(strict_types=1);
use App\Core\Csrf;
// Variables: $stats (array), $logs (array[])
?>
<!-- Stats cards -->
<div class="grid grid-cols-2 gap-4 lg:grid-cols-5 mb-8">
    <?php
    $cards = [
        ['label' => 'Użytkownicy',   'value' => $stats['users'],         'color' => 'text-blue-600',   'bg' => 'bg-blue-50'],
        ['label' => 'Zablokowane',   'value' => $stats['blocked_users'],  'color' => 'text-red-600',    'bg' => 'bg-red-50'],
        ['label' => 'Drzewa',        'value' => $stats['trees'],          'color' => 'text-green-600',  'bg' => 'bg-green-50'],
        ['label' => 'Osoby',         'value' => $stats['persons'],        'color' => 'text-purple-600', 'bg' => 'bg-purple-50'],
        ['label' => 'Relacje',       'value' => $stats['relationships'],  'color' => 'text-amber-600',  'bg' => 'bg-amber-50'],
    ];
    foreach ($cards as $card):
    ?>
        <div class="bg-white rounded-lg border border-slate-200 px-5 py-4">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= $card['label'] ?></p>
            <p class="mt-1 text-2xl font-bold <?= $card['color'] ?>"><?= number_format($card['value']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Recent logs -->
<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="text-sm font-semibold text-slate-700">Ostatnie zdarzenia</h2>
    </div>

    <?php if (empty($logs)): ?>
        <div class="px-5 py-8 text-center text-sm text-slate-500">Brak wpisów w logu.</div>
    <?php else: ?>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left font-medium text-slate-500">Czas</th>
                    <th class="px-5 py-3 text-left font-medium text-slate-500">Admin</th>
                    <th class="px-5 py-3 text-left font-medium text-slate-500">Akcja</th>
                    <th class="px-5 py-3 text-left font-medium text-slate-500">Cel</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 text-slate-500 whitespace-nowrap">
                            <?= htmlspecialchars((new \DateTime($log['created_at']))->format('d.m.Y H:i')) ?>
                        </td>
                        <td class="px-5 py-3 text-slate-700 whitespace-nowrap">
                            <?= htmlspecialchars($log['admin_name'] ?? $log['admin_email'] ?? '—') ?>
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
                        <td class="px-5 py-3 text-slate-500 text-xs">
                            <?php
                            if ($log['target_type'] === 'user' && $log['target_id']) {
                                echo '<a href="/admin/users/' . htmlspecialchars($log['target_id']) . '" class="hover:underline text-slate-700">'
                                     . htmlspecialchars($log['target_type'] . ':' . substr($log['target_id'], 0, 8)) . '…</a>';
                            } else {
                                echo htmlspecialchars($log['target_type'] ?? '—');
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="px-5 py-3 border-t border-slate-200">
        <a href="/admin/logs" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
            Zobacz wszystkie logi →
        </a>
    </div>
</div>
