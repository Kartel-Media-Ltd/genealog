<?php
declare(strict_types=1);
use App\Core\DateHelper;
/** @var \App\Models\Tree $tree */
/** @var \App\Models\Person[] $persons */
/** @var string $treeId */
/** @var string $mode */
/** @var array|null $personsTree */
$mode        ??= 'list';
$personsTree ??= null;
?>

<!-- Pasek narzędziowy (ukryty przy druku) -->
<div class="no-print flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2">
    <span class="mr-2 text-sm font-medium text-gray-700">
        Lista osób — <?= htmlspecialchars($tree->name) ?>
        <span class="text-gray-500">(<?= count($persons) ?>)</span>
    </span>
    <div class="ml-auto flex gap-2">
        <button
            type="button"
            id="btn-print"
            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Drukuj listę
        </button>
        <a href="/trees/<?= htmlspecialchars($treeId) ?>/persons"
           class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            Zamknij
        </a>
    </div>
</div>

<!-- Zawartość -->
<div class="p-4">
    <!-- Nagłówek widoczny przy druku — pomaga zorientować się w długich listach -->
    <header class="mb-4 hidden print:block">
        <h1 class="text-lg font-semibold text-gray-900">Lista osób — <?= htmlspecialchars($tree->name) ?></h1>
        <p class="text-xs text-gray-600">
            Liczba osób: <strong><?= count($persons) ?></strong>
            &bull; Wydrukowano: <?= date('d.m.Y H:i') ?>
        </p>
    </header>

    <?php if (empty($persons)): ?>
        <p class="py-8 text-center text-sm text-gray-500">Drzewo nie zawiera żadnych osób.</p>
    <?php elseif ($mode === 'hierarchy' && $personsTree !== null): ?>
        <!-- Hierarchia -->
        <table class="print-table w-full text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="w-12 text-left">Nr</th>
                    <th class="text-left">Imię i nazwisko</th>
                    <th class="text-left">Data ur.</th>
                    <th class="text-left">Data śm.</th>
                    <th class="text-left">Miejsce ur.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($personsTree as $i => $entry):
                    $p      = $entry['person'];
                    $depth  = $entry['depth'];
                    $number = $entry['number'];
                    $pad    = str_repeat('    ', $depth);
                ?>
                    <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                        <td class="text-gray-500 font-mono text-xs"><?= htmlspecialchars($number) ?></td>
                        <td style="padding-left: <?= $depth * 16 ?>px">
                            <?php if (!$p->isLiving): ?><span class="text-gray-400">†</span> <?php endif; ?>
                            <span class="font-medium"><?= htmlspecialchars($p->fullName()) ?></span>
                            <?php if ($p->maidenName): ?>
                                <span class="text-gray-500 text-xs">(z d. <?= htmlspecialchars($p->maidenName) ?>)</span>
                            <?php endif; ?>
                            <?php $age = DateHelper::ageInYears($p->birthDate, $p->deathDate); ?>
                            <?php if ($age !== null): ?>
                                <span class="text-gray-400 text-xs">l.&nbsp;<?= $age ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-gray-600 text-xs"><?= htmlspecialchars($p->birthDate ?? '') ?: '—' ?></td>
                        <td class="text-gray-600 text-xs"><?= htmlspecialchars($p->deathDate ?? '') ?: '—' ?></td>
                        <td class="text-gray-600 text-xs"><?= htmlspecialchars($p->birthPlace ?? '') ?: '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <!-- Lista alfabetyczna -->
        <table class="print-table w-full text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="w-8 text-center">Lp.</th>
                    <th class="text-left">Imię</th>
                    <th class="text-left">Nazwisko</th>
                    <th class="text-left">Nazwisko panieńskie</th>
                    <th class="text-left">Data ur.</th>
                    <th class="text-left">Miejsce ur.</th>
                    <th class="text-left">Data śm.</th>
                    <th class="text-left">Miejsce śm.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($persons as $i => $p): ?>
                    <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                        <td class="text-center text-gray-500"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($p->firstName ?? '') ?: '—' ?></td>
                        <td class="font-medium"><?= htmlspecialchars($p->lastName ?? '') ?: '—' ?></td>
                        <td class="text-gray-600"><?= htmlspecialchars($p->maidenName ?? '') ?: '—' ?></td>
                        <td class="text-gray-600"><?= htmlspecialchars($p->birthDate ?? '') ?: '—' ?></td>
                        <td class="text-gray-600"><?= htmlspecialchars($p->birthPlace ?? '') ?: '—' ?></td>
                        <td class="text-gray-600"><?= htmlspecialchars($p->deathDate ?? '') ?: '—' ?></td>
                        <td class="text-gray-600"><?= htmlspecialchars($p->deathPlace ?? '') ?: '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <footer class="no-screen mt-6 border-t border-gray-300 pt-3 text-xs text-gray-500">
        Drzewo: <strong><?= htmlspecialchars($tree->name) ?></strong>
        &bull; Liczba osób: <strong><?= count($persons) ?></strong>
        &bull; Wydrukowano: <?= date('d.m.Y H:i') ?>
    </footer>
</div>

<script src="/js/print-helper.js" defer></script>
