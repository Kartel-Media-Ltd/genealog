<?php
declare(strict_types=1);
use App\Core\Csrf;

/** @var \App\Models\Tree $tree */
?>

<div class="mx-auto max-w-xl">

    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground" aria-label="Nawigacja">
        <a href="/trees" class="hover:text-foreground transition-colors">Moje drzewa</a>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
        <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
           class="hover:text-foreground transition-colors"><?= htmlspecialchars($tree->name) ?></a>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
        <span class="text-foreground font-medium">Ustawienia</span>
    </nav>

    <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h1 class="text-lg font-semibold text-card-foreground">Ustawienia drzewa</h1>
        </div>

        <form method="POST" action="/trees/<?= htmlspecialchars($tree->id) ?>/edit"
              class="p-6 space-y-5" novalidate>
            <?= Csrf::hiddenInput() ?>

            <div class="space-y-1.5">
                <label for="name" class="block text-sm font-medium text-foreground">
                    Nazwa drzewa <span class="text-destructive" aria-hidden="true">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    maxlength="150"
                    value="<?= htmlspecialchars($tree->name) ?>"
                    class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background
                           text-foreground text-sm placeholder:text-muted-foreground
                           focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                >
            </div>

            <div class="space-y-1.5">
                <label for="description" class="block text-sm font-medium text-foreground">
                    Opis <span class="text-xs text-muted-foreground font-normal">(opcjonalnie)</span>
                </label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="1000"
                    class="w-full px-3 py-2 rounded-md border border-border bg-background
                           text-foreground text-sm placeholder:text-muted-foreground resize-none
                           focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                ><?= htmlspecialchars($tree->description ?? '') ?></textarea>
            </div>

            <div class="flex items-start gap-3 rounded-md border border-border bg-muted/40 p-4">
                <input
                    type="checkbox"
                    id="is_public"
                    name="is_public"
                    value="1"
                    <?= $tree->isPublic ? 'checked' : '' ?>
                    class="mt-0.5 h-4 w-4 rounded border-border text-primary
                           focus:ring-2 focus:ring-ring focus:ring-offset-2"
                >
                <div>
                    <label for="is_public" class="block text-sm font-medium text-foreground cursor-pointer">
                        Drzewo publiczne
                    </label>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Widoczne w globalnym wyszukiwaniu. Prywatność osób chroniona niezależnie.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="/trees/<?= htmlspecialchars($tree->id) ?>"
                   class="inline-flex h-10 items-center rounded-md border border-input px-4
                          text-sm font-medium text-foreground hover:bg-accent transition-colors
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Anuluj
                </a>
                <button type="submit"
                        class="inline-flex h-10 items-center rounded-md px-4 text-sm font-medium
                               bg-primary text-primary-foreground hover:bg-primary/90 transition-colors
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Zapisz zmiany
                </button>
            </div>
        </form>
    </div>
</div>
