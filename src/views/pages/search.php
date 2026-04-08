<?php
declare(strict_types=1);
require_once __DIR__ . '/../atoms/icon.php';
/**
 * Page: Poszukiwania
 * Zaślepka — integracja z rejestrami zewnętrznymi w przygotowaniu.
 * Używa AppLayout (domyślny).
 */
?>

<div class="mx-auto max-w-2xl">

    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
        <span class="text-foreground font-medium">Poszukiwania</span>
    </nav>

    <!-- Hero placeholder -->
    <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
        <div class="border-b border-border px-6 py-4 flex items-center gap-2">
            <?php render_icon('magnifying-glass', 'solid', 'h-4.5 w-4.5 text-muted-foreground') ?>
            <h1 class="text-lg font-semibold text-card-foreground">Poszukiwania rodziny</h1>
        </div>

        <div class="flex flex-col items-center gap-6 px-6 py-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                <?php render_icon('hourglass-half', 'solid', 'h-7 w-7 text-muted-foreground') ?>
            </div>

            <div>
                <p class="text-base font-medium text-foreground">Funkcja wkrótce dostępna</p>
                <p class="mt-1.5 text-sm text-muted-foreground max-w-sm">
                    Wyszukiwanie osób w rejestrach zewnętrznych — Grobonet, eCmentarze,
                    Geneteka i inne — jest w trakcie przygotowania.
                </p>
            </div>

            <!-- Planowane rejestry -->
            <div class="w-full max-w-sm rounded-md border border-border bg-muted/40 p-4 text-left">
                <p class="mb-2.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Planowane rejestry
                </p>
                <ul class="space-y-2">
                    <?php
                    $registries = [
                        ['icon' => 'cross',          'name' => 'Grobonet',    'desc' => '600+ cmentarzy komunalnych i parafialnych'],
                        ['icon' => 'cross',          'name' => 'eCmentarze',  'desc' => '2,36 mln pochowanych w Polsce'],
                        ['icon' => 'scroll',         'name' => 'Geneteka',    'desc' => 'Indeks metryk PTG — urodzenia, śluby, zgony'],
                        ['icon' => 'globe',          'name' => 'FamilySearch','desc' => 'Globalna baza rekordów genealogicznych'],
                    ];
                    foreach ($registries as $reg):
                    ?>
                    <li class="flex items-start gap-2.5">
                        <?php render_icon($reg['icon'], 'solid', 'mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground') ?>
                        <span class="text-sm text-foreground">
                            <span class="font-medium"><?= htmlspecialchars($reg['name']) ?></span>
                            <span class="text-muted-foreground"> — <?= htmlspecialchars($reg['desc']) ?></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <p class="text-xs text-muted-foreground">
                Na razie wyszukiwanie w Twoich drzewach i dopasowania między drzewami
                dostępne są podczas
                <a href="/trees" class="underline underline-offset-2 hover:text-foreground">dodawania osoby</a>.
            </p>
        </div>
    </div>

</div>
