<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../../atoms/icon.php';

/** @var \App\Models\Tree $tree */
/** @var bool $ownerOptIn */
?>
<div class="mx-auto max-w-2xl">

    <nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
        <a href="/trees/<?= htmlspecialchars($tree->id) ?>" class="hover:text-foreground transition-colors">
            <?= htmlspecialchars($tree->name) ?>
        </a>
        <?php render_icon('chevron-right', 'solid', 'h-3.5 w-3.5') ?>
        <span class="text-foreground font-medium">Odkrywanie / Cross-tree</span>
    </nav>

    <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h1 class="text-lg font-semibold text-card-foreground">
                Ustawienia odkrywania (cross-tree)
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Drzewo: <strong><?= htmlspecialchars($tree->name) ?></strong>
            </p>
        </div>

        <form method="POST"
              action="/trees/<?= htmlspecialchars($tree->id) ?>/settings/discovery"
              class="p-6 space-y-6">
            <?= Csrf::hiddenInput() ?>

            <!-- Info RODO -->
            <div class="rounded-md border border-primary/30 bg-primary/5 p-4">
                <h2 class="text-sm font-semibold text-foreground mb-2">Jak to działa?</h2>
                <p class="text-sm text-muted-foreground leading-relaxed">
                    Gdy włączysz odkrywanie, Twoje <strong>osoby historyczne</strong> (zmarłe lub
                    urodzone ponad 100 lat temu, bez statusu „prywatne") trafią do anonimowego
                    globalnego indeksu. Inni użytkownicy mogą dopasować swoje osoby do Twoich
                    i znaleźć potencjalne powiązania rodzinne.
                </p>
                <ul class="mt-2 text-sm text-muted-foreground space-y-1 list-disc ml-5">
                    <li><strong>Żyjące osoby nigdy</strong> nie są udostępniane (RODO)</li>
                    <li>Inni widzą tylko <strong>rok urodzenia i województwo</strong> — bez daty, bez miejsca, bez zdjęć</li>
                    <li>Nazwa Twojego drzewa nie jest ujawniana — tylko anonimowy identyfikator</li>
                    <li>Możesz w każdej chwili wyłączyć odkrywanie — wpisy zostaną natychmiast usunięte</li>
                </ul>
            </div>

            <!-- Zgoda usera (konto) -->
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox"
                           name="user_opt_in"
                           value="1"
                           <?= $ownerOptIn ? 'checked' : '' ?>
                           class="mt-1 h-4 w-4 rounded border-border text-primary">
                    <span class="text-sm text-foreground">
                        <strong>Zgoda konta:</strong> wyrażam zgodę na udział w globalnym indeksie
                        odkrywania (RODO Art. 6(1)(f) — uzasadniony interes genealogiczny).
                        Bez tej zgody żadne drzewo nie będzie indeksowane.
                    </span>
                </label>
            </div>

            <!-- Flaga drzewa -->
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox"
                           name="tree_indexed"
                           value="1"
                           <?= $tree->isIndexedGlobally ? 'checked' : '' ?>
                           class="mt-1 h-4 w-4 rounded border-border text-primary">
                    <span class="text-sm text-foreground">
                        <strong>Udostępnij to drzewo:</strong> włącz globalny indeks dla drzewa
                        „<?= htmlspecialchars($tree->name) ?>". Po zapisaniu kwalifikujące się osoby
                        zostaną natychmiast zaindeksowane.
                    </span>
                </label>
            </div>

            <?php if ($tree->discoveryConsentAt !== null): ?>
                <p class="text-xs text-muted-foreground">
                    Zgoda udzielona: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($tree->discoveryConsentAt))) ?>
                </p>
            <?php endif; ?>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-border">
                <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
                   class="inline-flex h-10 items-center rounded-md border border-input px-4
                          text-sm font-medium text-foreground hover:bg-accent transition-colors">
                    Anuluj
                </a>
                <button type="submit"
                        class="inline-flex h-10 items-center rounded-md px-4 text-sm font-medium
                               bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                    Zapisz ustawienia
                </button>
            </div>
        </form>
    </div>
</div>
