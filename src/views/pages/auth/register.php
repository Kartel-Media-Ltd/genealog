<?php
declare(strict_types=1);
use App\Core\Csrf;
require_once __DIR__ . '/../../atoms/icon.php';
?>
<h2 class="text-xl font-semibold text-foreground mb-1">Utwórz konto</h2>
<p class="text-sm text-muted-foreground mb-6">Wprowadź dane, aby założyć konto</p>

<form method="POST" action="/register" novalidate>
    <?= Csrf::hiddenInput() ?>

    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-foreground mb-1">
                Imię i nazwisko <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <input
                type="text"
                id="name"
                name="name"
                required
                autocomplete="name"
                placeholder="Jan Kowalski"
                class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background text-foreground text-sm
                       placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
            >
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-foreground mb-1">
                Adres e-mail <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <input
                type="email"
                id="email"
                name="email"
                required
                autocomplete="email"
                placeholder="jan@kowalski.pl"
                class="w-full h-10 px-3 py-2 rounded-md border border-border bg-background text-foreground text-sm
                       placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
            >
        </div>

        <div x-data="{
                show: false,
                pw: '',
                get checks() {
                    return {
                        length:  this.pw.length >= 12,
                        upper:   /[A-Z]/.test(this.pw),
                        lower:   /[a-z]/.test(this.pw),
                        digit:   /[0-9]/.test(this.pw),
                        special: /[^A-Za-z0-9]/.test(this.pw),
                    };
                },
                get score() {
                    return Object.values(this.checks).filter(Boolean).length;
                },
                get label() {
                    if (!this.pw) return '';
                    return ['', 'Bardzo słabe', 'Słabe', 'Przeciętne', 'Dobre', 'Silne'][this.score] || 'Silne';
                },
                get labelColor() {
                    return ['', 'text-red-500', 'text-orange-500', 'text-amber-600', 'text-lime-600', 'text-emerald-600'][this.score] || 'text-emerald-600';
                },
                segmentColor(i) {
                    if (this.score === 0 || i >= this.score) return 'bg-muted';
                    return ['', 'bg-red-500', 'bg-orange-500', 'bg-amber-500', 'bg-lime-500', 'bg-emerald-500'][this.score];
                },
            }">
            <label for="password" class="block text-sm font-medium text-foreground mb-1">
                Hasło <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <div class="relative">
                <input
                    :type="show ? 'text' : 'password'"
                    x-model="pw"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    aria-describedby="password-strength-desc"
                    class="w-full h-10 px-3 py-2 pr-10 rounded-md border border-border bg-background text-foreground text-sm
                           placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                >
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-muted-foreground hover:text-foreground"
                        :aria-label="show ? 'Ukryj hasło' : 'Pokaż hasło'">
                    <span x-show="!show"><?php render_icon('eye', 'solid', 'h-4 w-4') ?></span>
                    <span x-show="show"><?php render_icon('eye-slash', 'solid', 'h-4 w-4') ?></span>
                </button>
            </div>

            <!-- Pasek siły hasła -->
            <div class="mt-2" id="password-strength-desc" aria-live="polite">
                <div class="flex gap-1 mb-1" role="presentation" aria-hidden="true">
                    <template x-for="i in 5" :key="i">
                        <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                             :class="segmentColor(i)"></div>
                    </template>
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-medium transition-colors duration-200"
                       :class="pw ? labelColor : 'text-muted-foreground'"
                       x-text="pw ? label : 'Wpisz hasło'"></p>
                    <p class="text-[11px] text-muted-foreground" x-show="pw"
                       x-text="score + '/5'"></p>
                </div>

                <!-- Wymagania -->
                <ul class="mt-2 space-y-1" x-show="pw" x-transition>
                    <template x-for="(item, idx) in [
                        { key: 'length',  label: 'Min. 12 znaków' },
                        { key: 'upper',   label: 'Wielka litera (A–Z)' },
                        { key: 'lower',   label: 'Mała litera (a–z)' },
                        { key: 'digit',   label: 'Cyfra (0–9)' },
                        { key: 'special', label: 'Znak specjalny (!@#$…)' },
                    ]" :key="idx">
                        <li class="flex items-center gap-1.5 text-[11px] transition-colors duration-150"
                            :class="checks[item.key] ? 'text-emerald-600' : 'text-muted-foreground'">
                            <span class="flex-shrink-0 h-3.5 w-3.5 rounded-full flex items-center justify-center transition-colors duration-150"
                                  :class="checks[item.key] ? 'bg-emerald-100' : 'bg-muted'">
                                <svg x-show="checks[item.key]" class="h-2 w-2 text-emerald-600" fill="currentColor" viewBox="0 0 12 12" aria-hidden="true">
                                    <path d="M10 3L5 8.5 2 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                </svg>
                                <svg x-show="!checks[item.key]" class="h-1.5 w-1.5 text-muted-foreground/50" fill="currentColor" viewBox="0 0 6 6" aria-hidden="true">
                                    <circle cx="3" cy="3" r="2"/>
                                </svg>
                            </span>
                            <span x-text="item.label"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div>
            <label for="password_confirm" class="block text-sm font-medium text-foreground mb-1">
                Potwierdź hasło <span class="text-destructive" aria-hidden="true">*</span>
            </label>
            <div class="relative" x-data="{ show: false }">
                <input
                    :type="show ? 'text' : 'password'"
                    id="password_confirm"
                    name="password_confirm"
                    required
                    autocomplete="new-password"
                    class="w-full h-10 px-3 py-2 pr-10 rounded-md border border-border bg-background text-foreground text-sm
                           placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                >
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-muted-foreground hover:text-foreground"
                        :aria-label="show ? 'Ukryj hasło' : 'Pokaż hasło'">
                    <span x-show="!show"><?php render_icon('eye', 'solid', 'h-4 w-4') ?></span>
                    <span x-show="show"><?php render_icon('eye-slash', 'solid', 'h-4 w-4') ?></span>
                </button>
            </div>
        </div>

        <!-- ZAD-1.3 (K3): Consent flow — wymagane RODO Art. 6/7 i Art. 13 -->
        <!-- ZAD-2.10 (P10): aria-describedby → WCAG 3.3.2 Labels or Instructions -->
        <div class="flex items-start gap-2 text-sm">
            <input type="checkbox"
                   id="consent"
                   name="consent"
                   value="1"
                   required
                   aria-describedby="consent-desc"
                   class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring"
            >
            <label for="consent" id="consent-desc" class="text-muted-foreground select-none">
                Akceptuję
                <a href="/terms" target="_blank" rel="noopener"
                   class="font-medium text-foreground hover:underline">Regulamin</a>
                i zapoznałem/am się z
                <a href="/privacy" target="_blank" rel="noopener"
                   class="font-medium text-foreground hover:underline">Polityką prywatności</a>.
                <span class="text-destructive" aria-hidden="true">*</span>
            </label>
        </div>

        <button type="submit"
                class="w-full h-10 px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium
                       hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Utwórz konto
        </button>
    </div>
</form>

<div class="mt-6 text-center text-sm text-muted-foreground">
    Masz już konto?
    <a href="/login" class="font-medium text-foreground hover:underline">Zaloguj się</a>
</div>
