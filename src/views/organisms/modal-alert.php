<?php
/**
 * Organism: Modal Alert
 *
 * Globalny modal zastępujący natywny alert(). Sterowany przez Alpine.js Store.
 *
 * Użycie z PHP (w szablonach Alpine):
 *   $store.modal.show('Treść komunikatu', 'Tytuł', 'error')
 *
 * Użycie z czystego JS (np. print-helper.js):
 *   window.showModal('Treść komunikatu', 'Tytuł', 'error')
 *
 * Typy: 'info' | 'success' | 'warning' | 'error'
 */
?>
<div
    x-data
    x-show="$store.modal.open"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="$store.modal.hide()"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="'modal-title-' + $store.modal.type"
>
    <!-- Backdrop -->
    <div
        class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        @click="$store.modal.hide()"
        aria-hidden="true"
    ></div>

    <!-- Dialog panel -->
    <div
        x-show="$store.modal.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        class="relative w-full max-w-md rounded-xl border bg-white shadow-2xl"
        @click.stop
    >
        <!-- Header z ikoną -->
        <div class="flex items-start gap-4 p-6 pb-4">
            <!-- Ikona zależna od typu -->
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                :class="{
                    'bg-red-100':    $store.modal.type === 'error',
                    'bg-amber-100':  $store.modal.type === 'warning',
                    'bg-green-100':  $store.modal.type === 'success',
                    'bg-blue-100':   $store.modal.type === 'info',
                }"
            >
                <i
                    class="fa-solid fa-fw text-base"
                    :class="{
                        'fa-circle-xmark text-red-600':      $store.modal.type === 'error',
                        'fa-triangle-exclamation text-amber-600': $store.modal.type === 'warning',
                        'fa-circle-check text-green-600':    $store.modal.type === 'success',
                        'fa-circle-info text-blue-600':      $store.modal.type === 'info',
                    }"
                    aria-hidden="true"
                ></i>
            </div>

            <div class="min-w-0 flex-1 pt-0.5">
                <h2
                    :id="'modal-title-' + $store.modal.type"
                    class="text-base font-semibold text-foreground"
                    x-text="$store.modal.title"
                ></h2>
                <p
                    class="mt-1.5 text-sm text-muted-foreground leading-relaxed"
                    x-text="$store.modal.message"
                ></p>
            </div>

            <!-- Przycisk X -->
            <button
                @click="$store.modal.hide()"
                class="shrink-0 rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground
                       focus:outline-none focus:ring-2 focus:ring-ring transition-colors"
                aria-label="Zamknij"
            >
                <i class="fa-solid fa-xmark fa-fw h-4 w-4" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
            <!-- Anuluj — tylko w trybie confirm -->
            <button
                x-show="$store.modal.isConfirm"
                @click="$store.modal.hide()"
                class="inline-flex h-9 items-center justify-center rounded-md border border-border
                       bg-background px-5 text-sm font-medium text-foreground hover:bg-muted
                       transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            >
                Anuluj
            </button>

            <button
                @click="$store.modal.confirm()"
                class="inline-flex h-9 items-center justify-center rounded-md px-6 text-sm font-medium
                       transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                :class="{
                    'bg-red-600 text-white hover:bg-red-700':      $store.modal.type === 'error',
                    'bg-amber-500 text-white hover:bg-amber-600':  $store.modal.type === 'warning',
                    'bg-green-600 text-white hover:bg-green-700':  $store.modal.type === 'success',
                    'bg-primary text-primary-foreground hover:bg-primary/90': $store.modal.type === 'info',
                }"
                x-ref="confirmBtn"
                @modal-opened.window="$nextTick(() => $refs.confirmBtn && $refs.confirmBtn.focus())"
            >
                OK
            </button>
        </div>
    </div>
</div>
