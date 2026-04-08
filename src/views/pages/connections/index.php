<?php
declare(strict_types=1);
use App\Core\Csrf;
// Variables: $incoming (array[]), $sent (array[]), $accepted (array[])

$csrfToken = Csrf::getToken();
?>
<div class="space-y-6" x-data="connectionsPage('<?= htmlspecialchars($csrfToken) ?>')">

    <!-- Modal szczegółów prośby -->
    <div x-show="modal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="modal.open = false">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="modal.open = false"></div>

        <div class="relative w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl"
             @click.stop>

            <!-- Nagłówek -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-800">Prośba o powiązanie osób</h2>
                <button type="button" @click="modal.open = false"
                        class="text-slate-400 hover:text-slate-600">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/></svg>
                </button>
            </div>

            <!-- Treść -->
            <div class="px-5 py-4 space-y-4">

                <!-- Wnioskodawca -->
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Od użytkownika</p>
                <p class="text-sm text-slate-700" x-text="modal.requesterName"></p>

                <!-- Porównanie osób -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-purple-100 bg-purple-50 px-4 py-3">
                        <p class="text-[10px] font-semibold text-purple-500 uppercase tracking-wide mb-1.5">Jego/jej osoba</p>
                        <p class="text-sm font-semibold text-slate-800" x-text="modal.reqName"></p>
                        <p class="text-xs text-slate-500 mt-0.5" x-show="modal.reqBirthYear">
                            ur. <span x-text="modal.reqBirthYear"></span>
                            <span x-show="modal.reqBirthPlace"> · <span x-text="modal.reqBirthPlace"></span></span>
                        </p>
                        <p class="text-xs text-slate-500 mt-0.5" x-show="modal.reqDeathYear">
                            zm. <span x-text="modal.reqDeathYear"></span>
                        </p>
                        <p class="text-xs text-purple-600 mt-1.5" x-text="modal.reqTreeName"></p>
                    </div>
                    <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
                        <p class="text-[10px] font-semibold text-blue-500 uppercase tracking-wide mb-1.5">Twoja osoba</p>
                        <p class="text-sm font-semibold text-slate-800" x-text="modal.tgtName"></p>
                        <p class="text-xs text-slate-500 mt-0.5" x-show="modal.tgtBirthYear">
                            ur. <span x-text="modal.tgtBirthYear"></span>
                            <span x-show="modal.tgtBirthPlace"> · <span x-text="modal.tgtBirthPlace"></span></span>
                        </p>
                        <p class="text-xs text-slate-500 mt-0.5" x-show="modal.tgtDeathYear">
                            zm. <span x-text="modal.tgtDeathYear"></span>
                        </p>
                    </div>
                </div>

                <!-- Wiadomość -->
                <div x-show="modal.note" class="rounded-md bg-slate-50 border border-slate-200 px-4 py-3">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Wiadomość od wnioskodawcy</p>
                    <p class="text-sm text-slate-700 italic">„<span x-text="modal.note"></span>"</p>
                </div>

                <!-- Poziom widoczności -->
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>Żądany dostęp: </span>
                    <span class="font-medium text-slate-700" x-text="modal.visibilityLabel"></span>
                </div>

                <!-- Błąd akcji -->
                <p x-show="modal.error" x-text="modal.error"
                   class="text-xs text-red-600 rounded-md bg-red-50 border border-red-200 px-3 py-2"></p>
            </div>

            <!-- Stopka -->
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button type="button" @click="modal.open = false"
                        class="inline-flex h-8 items-center rounded-md border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                    Zamknij
                </button>
                <button type="button" @click="doAction('reject')"
                        :disabled="modal.acting"
                        class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 transition-colors">
                    <span x-show="modal.acting === 'reject'">Odrzucam…</span>
                    <span x-show="modal.acting !== 'reject'">Odrzuć</span>
                </button>
                <button type="button" @click="doAction('accept')"
                        :disabled="modal.acting"
                        class="inline-flex h-8 items-center rounded-md bg-green-600 px-3 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors">
                    <span x-show="modal.acting === 'accept'">Akceptuję…</span>
                    <span x-show="modal.acting !== 'accept'">Akceptuj</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Sekcja: Przychodzące prośby -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center gap-2">
            <h2 class="text-sm font-semibold text-slate-700">Przychodzące prośby</h2>
            <?php if (!empty($incoming)): ?>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                    <?= count($incoming) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (empty($incoming)): ?>
            <div class="px-5 py-8 text-center text-sm text-slate-500">
                Brak oczekujących próśb o powiązanie.
            </div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($incoming as $link): ?>
                    <?php
                    $linkJson = htmlspecialchars(json_encode([
                        'id'             => $link['id'],
                        'requesterName'  => $link['requester_name'],
                        'reqName'        => $link['req_first_name'] . ' ' . $link['req_last_name'],
                        'reqBirthYear'   => $link['req_birth_date'] ? substr($link['req_birth_date'], 0, 4) : null,
                        'reqBirthPlace'  => $link['req_birth_place'] ?? null,
                        'reqDeathYear'   => isset($link['req_death_date']) && $link['req_death_date'] ? substr($link['req_death_date'], 0, 4) : null,
                        'reqTreeName'    => $link['req_tree_name'],
                        'tgtName'        => $link['tgt_first_name'] . ' ' . $link['tgt_last_name'],
                        'tgtBirthYear'   => $link['tgt_birth_date'] ? substr($link['tgt_birth_date'], 0, 4) : null,
                        'tgtBirthPlace'  => $link['tgt_birth_place'] ?? null,
                        'tgtDeathYear'   => isset($link['tgt_death_date']) && $link['tgt_death_date'] ? substr($link['tgt_death_date'], 0, 4) : null,
                        'note'           => $link['note'] ?? null,
                        'visibilityLevel'=> $link['visibility_level'],
                        'createdAt'      => (new \DateTime($link['created_at']))->format('d.m.Y'),
                    ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <li class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-800">
                                    <?= htmlspecialchars($link['requester_name']) ?> prosi o połączenie
                                </p>
                                <p class="mt-0.5 text-sm text-slate-600">
                                    <span class="font-medium">
                                        <?= htmlspecialchars($link['req_first_name'] . ' ' . $link['req_last_name']) ?>
                                    </span>
                                    <?php if ($link['req_birth_date']): ?>
                                        <span class="text-slate-400 text-xs">(ur. <?= htmlspecialchars(substr($link['req_birth_date'], 0, 4)) ?>)</span>
                                    <?php endif; ?>
                                    <span class="text-slate-400 mx-1">↔</span>
                                    <span class="font-medium">
                                        <?= htmlspecialchars($link['tgt_first_name'] . ' ' . $link['tgt_last_name']) ?>
                                    </span>
                                    <?php if ($link['tgt_birth_date']): ?>
                                        <span class="text-slate-400 text-xs">(ur. <?= htmlspecialchars(substr($link['tgt_birth_date'], 0, 4)) ?>)</span>
                                    <?php endif; ?>
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    <?= htmlspecialchars((new \DateTime($link['created_at']))->format('d.m.Y')) ?>
                                    &bull;
                                    <?= $link['visibility_level'] === 'shared_full' ? 'Pełne dane' : 'Podstawowe dane' ?>
                                    <?php if (!empty($link['note'])): ?>
                                        &bull; <span class="italic">"<?= htmlspecialchars(mb_strimwidth($link['note'], 0, 60, '…')) ?>"</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <button type="button"
                                    @click="openModal(<?= $linkJson ?>)"
                                    class="shrink-0 inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                Sprawdź
                            </button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Sekcja: Zaakceptowane połączenia -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-700">
                Aktywne powiązania (<?= count($accepted) ?>)
            </h2>
        </div>

        <?php if (empty($accepted)): ?>
            <div class="px-5 py-8 text-center text-sm text-slate-500">
                <p>Nie masz jeszcze żadnych zaakceptowanych powiązań między drzewami.</p>
                <p class="mt-1 text-slate-400 text-xs">
                    Powiązania powstają gdy dwoje właścicieli drzew zaakceptuje połączenie tej samej osoby historycznej.
                </p>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($accepted as $link): ?>
                    <?php
                    $myPersonName    = htmlspecialchars($link['req_first_name'] . ' ' . $link['req_last_name']);
                    $myTreeName      = htmlspecialchars($link['req_tree_name']);
                    $theirPersonName = htmlspecialchars($link['tgt_first_name'] . ' ' . $link['tgt_last_name']);
                    $theirTreeName   = htmlspecialchars($link['tgt_tree_name']);
                    $otherUser       = htmlspecialchars($link['target_user_name']);
                    ?>
                    <li class="px-5 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-800">
                                    <span class="font-medium"><?= $myPersonName ?></span>
                                    <span class="text-slate-400 mx-1.5">↔</span>
                                    <span class="font-medium"><?= $theirPersonName ?></span>
                                    <span class="text-slate-500 text-xs ml-1">(<?= $otherUser ?>)</span>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    <?= $myTreeName ?> &bull; <?= $link['visibility_level'] === 'shared_full' ? 'Pełne dane' : 'Podstawowe dane' ?>
                                    &bull; od <?= htmlspecialchars((new \DateTime($link['updated_at']))->format('d.m.Y')) ?>
                                </p>
                            </div>
                            <div class="shrink-0">
                                <form method="POST" action="/connections/<?= htmlspecialchars($link['id']) ?>/remove"
                                      x-data @submit.prevent="showConfirm('Usunąć to powiązanie? Osoba zdalnego drzewa zniknie z Twojego widoku drzewa.', () => $el.submit(), 'Usunięcie powiązania', 'error')">
                                    <?= Csrf::hiddenInput() ?>
                                    <button type="submit"
                                            class="inline-flex h-8 items-center rounded-md border border-red-200 bg-white px-3 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors">
                                        Usuń
                                    </button>
                                </form>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Sekcja: Wysłane prośby -->
    <?php if (!empty($sent)): ?>
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-700">Wysłane prośby</h2>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($sent as $link): ?>
                <li class="px-5 py-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-800">
                                <span class="font-medium">
                                    <?= htmlspecialchars($link['req_first_name'] . ' ' . $link['req_last_name']) ?>
                                </span>
                                <span class="text-slate-400 mx-1.5">&rarr;</span>
                                <span class="font-medium">
                                    <?= htmlspecialchars($link['tgt_first_name'] . ' ' . $link['tgt_last_name']) ?>
                                </span>
                                <span class="text-slate-500 text-xs ml-1">(<?= htmlspecialchars($link['target_user_name']) ?>)</span>
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400 flex items-center gap-1.5">
                                <?php
                                [$badgeColor, $badgeText] = match($link['status']) {
                                    'pending'   => ['bg-amber-100 text-amber-700', 'Oczekuje'],
                                    'rejected'  => ['bg-red-100 text-red-700', 'Odrzucone'],
                                    'cancelled' => ['bg-slate-100 text-slate-600', 'Anulowane'],
                                    default     => ['bg-slate-100 text-slate-600', $link['status']],
                                };
                                ?>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?= $badgeColor ?>">
                                    <?= $badgeText ?>
                                </span>
                                <?= htmlspecialchars((new \DateTime($link['created_at']))->format('d.m.Y')) ?>
                                &bull; <?= htmlspecialchars($link['tgt_tree_name']) ?>
                            </p>
                        </div>
                        <?php if ($link['status'] === 'pending'): ?>
                        <form method="POST" action="/connections/<?= htmlspecialchars($link['id']) ?>/cancel"
                              class="shrink-0">
                            <?= Csrf::hiddenInput() ?>
                            <button type="submit"
                                    class="inline-flex h-8 items-center rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                                Anuluj
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>

<script>
function connectionsPage(csrfToken) {
    return {
        csrfToken,
        modal: {
            open: false,
            id: null,
            requesterName: '',
            reqName: '',
            reqBirthYear: null,
            reqBirthPlace: null,
            reqDeathYear: null,
            reqTreeName: '',
            tgtName: '',
            tgtBirthYear: null,
            tgtBirthPlace: null,
            tgtDeathYear: null,
            note: null,
            visibilityLabel: '',
            acting: false,
            error: '',
        },

        openModal(link) {
            this.modal = {
                open: true,
                id:              link.id,
                requesterName:   link.requesterName,
                reqName:         link.reqName,
                reqBirthYear:    link.reqBirthYear,
                reqBirthPlace:   link.reqBirthPlace,
                reqDeathYear:    link.reqDeathYear,
                reqTreeName:     link.reqTreeName,
                tgtName:         link.tgtName,
                tgtBirthYear:    link.tgtBirthYear,
                tgtBirthPlace:   link.tgtBirthPlace,
                tgtDeathYear:    link.tgtDeathYear,
                note:            link.note || null,
                visibilityLabel: link.visibilityLevel === 'shared_full' ? 'Pełne dane (bez notatek)' : 'Podstawowe (imię, nazwisko, rok ur., płeć)',
                acting: false,
                error: '',
            };
        },

        async doAction(action) {
            if (this.modal.acting) return;
            this.modal.acting = action;
            this.modal.error  = '';
            try {
                const res = await fetch(`/connections/${this.modal.id}/${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf_token: this.csrfToken }),
                });
                // Kontroler robi redirect — przy fetch dostaniemy 200 po redirect
                // Jeśli nie było błędu, przeładuj stronę żeby odświeżyć listy
                if (res.ok || res.redirected) {
                    window.location.reload();
                } else {
                    this.modal.error = `Błąd serwera (${res.status}). Spróbuj ponownie.`;
                    this.modal.acting = false;
                }
            } catch (e) {
                this.modal.error  = 'Błąd sieci. Spróbuj ponownie.';
                this.modal.acting = false;
            }
        },
    };
}
</script>
