<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../atoms/icon.php';

/** @var \App\Models\Tree $tree */
/** @var array $currentUser */

$tree        = $tree        ?? null;
$currentUser = $currentUser ?? ['name' => '', 'email' => ''];
$treeId    = $tree?->id ?? '';
?>

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
    <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <a href="/trees/<?= htmlspecialchars((string)$treeId) ?>"
       class="hover:text-foreground transition-colors">
        <?= htmlspecialchars($tree?->name ?? '') ?>
    </a>
    <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
    <span class="text-foreground">GEDCOM</span>
</nav>

<!-- Page header -->
<div class="mb-8 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Import / eksport GEDCOM</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            Standard GEDCOM 5.5.1 — kompatybilny z Ancestry, MyHeritage, FamilySearch i Gramps.
        </p>
    </div>
    <a href="/trees/<?= htmlspecialchars((string)$treeId) ?>"
       class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md border border-input px-3
              text-sm font-medium text-foreground hover:bg-accent transition-colors
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        <?php render_icon('arrow-left', 'solid', 'h-3.5 w-3.5') ?>
        Wróć do drzewa
    </a>
</div>

<div class="grid gap-6 lg:grid-cols-2">

    <!-- ── Import ─────────────────────────────────────────────────── -->
    <div class="rounded-xl border bg-card p-6 shadow-sm">
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                <?php render_icon('upload', 'solid', 'h-5 w-5 text-primary') ?>
            </div>
            <div>
                <h2 class="text-lg font-semibold">Importuj drzewo</h2>
                <p class="text-sm text-muted-foreground">Wczytaj plik .ged z innego programu</p>
            </div>
        </div>

        <form method="POST"
              action="/trees/<?= htmlspecialchars((string)$treeId) ?>/gedcom/import"
              enctype="multipart/form-data"
              x-data="{ fileName: '', dragging: false }"
              @dragover.prevent="dragging = true"
              @dragleave.prevent="dragging = false"
              @drop.prevent="
                  dragging = false;
                  const f = $event.dataTransfer.files[0];
                  if (f) { fileName = f.name; $refs.fileInput.files = $event.dataTransfer.files; }
              ">

            <?= Csrf::hiddenInput() ?>

            <!-- Dropzone -->
            <label
                class="mb-4 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors"
                :class="dragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50 hover:bg-muted/30'"
            >
                <?php render_icon('file', 'solid', 'mb-3 h-8 w-8 text-muted-foreground') ?>

                <template x-if="fileName">
                    <p class="text-sm font-medium text-foreground" x-text="fileName"></p>
                </template>
                <template x-if="!fileName">
                    <div>
                        <p class="text-sm font-medium text-foreground">Kliknij lub przeciągnij plik .ged</p>
                        <p class="mt-1 text-xs text-muted-foreground">Maksymalny rozmiar: 50 MB</p>
                    </div>
                </template>

                <input x-ref="fileInput" type="file" name="gedcom_file" accept=".ged" class="sr-only"
                       @change="fileName = $event.target.files[0]?.name ?? ''">
            </label>

            <!-- Conflict strategy -->
            <fieldset class="mb-6">
                <legend class="mb-2 text-sm font-medium">Jeśli osoba już istnieje w drzewie:</legend>
                <div class="flex flex-col gap-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="radio" name="conflict_strategy" value="skip" checked
                               class="h-4 w-4 accent-primary">
                        <span>Pomiń — zachowaj istniejące dane <span class="text-muted-foreground">(domyślnie)</span></span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="radio" name="conflict_strategy" value="update"
                               class="h-4 w-4 accent-primary">
                        <span>Nadpisz — zastąp danymi z pliku</span>
                    </label>
                </div>
            </fieldset>

            <button type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!fileName">
                <?php render_icon('upload', 'solid', 'h-4 w-4') ?>
                Importuj plik GEDCOM
            </button>
        </form>

        <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5">
            <p class="text-xs text-amber-800">
                <strong>Uwaga:</strong> import może potrwać kilka sekund dla dużych plików (&gt;1000 osób).
                Nie zamykaj przeglądarki podczas importu.
            </p>
        </div>
    </div>

    <!-- ── Eksport ────────────────────────────────────────────────── -->
    <div class="rounded-xl border bg-card p-6 shadow-sm">
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                <?php render_icon('download', 'solid', 'h-5 w-5 text-primary') ?>
            </div>
            <div>
                <h2 class="text-lg font-semibold">Eksportuj drzewo</h2>
                <p class="text-sm text-muted-foreground">Pobierz plik .ged do użycia w innych programach</p>
            </div>
        </div>

        <p class="mb-4 text-sm text-muted-foreground">
            Eksportuje wszystkie osoby i relacje z drzewa
            <strong class="text-foreground"><?= htmlspecialchars($tree?->name ?? '') ?></strong>
            do formatu GEDCOM 5.5.1 kompatybilnego z:
        </p>

        <ul class="mb-6 space-y-1.5 text-sm text-muted-foreground">
            <li class="flex items-center gap-2">
                <?php render_icon('check', 'solid', 'h-3.5 w-3.5 shrink-0 text-green-600') ?>
                Gramps, MacFamilyTree, Legacy Family Tree
            </li>
            <li class="flex items-center gap-2">
                <?php render_icon('check', 'solid', 'h-3.5 w-3.5 shrink-0 text-green-600') ?>
                Ancestry.com, MyHeritage, FamilySearch
            </li>
            <li class="flex items-center gap-2">
                <?php render_icon('check', 'solid', 'h-3.5 w-3.5 shrink-0 text-green-600') ?>
                Inne programy genealogiczne obsługujące GEDCOM 5.5.1
            </li>
        </ul>

        <a href="/trees/<?= htmlspecialchars((string)$treeId) ?>/gedcom/export"
           class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground">
            <?php render_icon('download', 'solid', 'h-4 w-4') ?>
            Pobierz plik GEDCOM (.ged)
        </a>

        <div class="mt-4 rounded-lg bg-muted/50 border border-border px-3 py-2.5">
            <p class="text-xs text-muted-foreground">
                Eksport obejmuje imię, nazwisko, daty i miejsca urodzenia/śmierci, płeć oraz relacje rodzinne.
                Zdjęcia i notatki prywatne <strong>nie są</strong> eksportowane w tej wersji.
            </p>
        </div>

        <div class="mt-3 rounded-lg bg-muted/50 border border-border px-3 py-2.5">
            <p class="text-xs text-muted-foreground">
                Plik GEDCOM zawiera dane wszystkich osób, w tym żyjących.
                Udostępniaj go tylko zaufanym osobom.
            </p>
        </div>
    </div>
</div>

<!-- Info block -->
<div class="mt-6 rounded-xl border bg-card p-6 shadow-sm">
    <h3 class="mb-3 text-sm font-semibold">Co to jest GEDCOM?</h3>
    <p class="text-sm text-muted-foreground leading-relaxed">
        GEDCOM (Genealogical Data Communication) to otwarty standard wymiany danych genealogicznych stworzony przez
        The Church of Jesus Christ of Latter-day Saints w 1984 roku. Wersja 5.5.1 jest obecnie najszerzej obsługiwanym
        formatem przez programy genealogiczne i serwisy internetowe. Pliki GEDCOM mają rozszerzenie <code class="font-mono bg-muted px-1 rounded">.ged</code>.
    </p>
</div>
