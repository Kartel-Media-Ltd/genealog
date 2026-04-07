<?php
declare(strict_types=1);
// Variables: $trees (array[]), $page (int), $total (int), $limit (int)
$totalPages = (int)ceil($total / $limit);
?>
<div class="mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500"><?= number_format($total) ?> <?= $total === 1 ? 'drzewo' : 'drzew' ?></p>
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <?php if (empty($trees)): ?>
        <div class="px-5 py-12 text-center text-sm text-slate-500">Brak drzew w systemie.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Drzewo</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Właściciel</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Osoby</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Widoczność</th>
                        <th class="px-5 py-3 text-left font-medium text-slate-500">Aktualizacja</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($trees as $tree): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <div class="font-medium text-slate-800"><?= htmlspecialchars($tree['name']) ?></div>
                                <?php if ($tree['description']): ?>
                                    <div class="text-xs text-slate-400 truncate max-w-xs">
                                        <?= htmlspecialchars($tree['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3">
                                <div class="text-slate-700"><?= htmlspecialchars($tree['owner_name']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($tree['owner_email']) ?></div>
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
                            <td class="px-5 py-3 text-right">
                                <a href="/trees/<?= htmlspecialchars($tree['id']) ?>"
                                   target="_blank"
                                   class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                    Otwórz →
                                </a>
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
