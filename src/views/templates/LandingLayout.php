<!DOCTYPE html>
<html lang="pl" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Genealog — buduj drzewo genealogiczne swojej rodziny. Współpraca, GEDCOM, eksport do druku.') ?>">
    <link rel="icon" href="data:,">
    <title><?= htmlspecialchars(($pageTitle ?? 'Genealog') . ' — Odkryj historię swojej rodziny') ?></title>

    <!-- Tailwind CSS -->
    <script src="/vendor/tailwind.js"></script>
    <!-- Globalne style (tokeny shadcn) -->
    <link rel="stylesheet" href="/css/globals.css">
    <!-- Font Awesome 6 Free -->
    <link rel="preload" href="/vendor/fontawesome/webfonts/fa-solid-900.woff2"
          as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="/vendor/alpine.min.js"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        border:     'hsl(var(--border))',
                        input:      'hsl(var(--input))',
                        ring:       'hsl(var(--ring))',
                        background: 'hsl(var(--background))',
                        foreground: 'hsl(var(--foreground))',
                        primary: {
                            DEFAULT:    'hsl(var(--primary))',
                            foreground: 'hsl(var(--primary-foreground))',
                        },
                        secondary: {
                            DEFAULT:    'hsl(var(--secondary))',
                            foreground: 'hsl(var(--secondary-foreground))',
                        },
                        muted: {
                            DEFAULT:    'hsl(var(--muted))',
                            foreground: 'hsl(var(--muted-foreground))',
                        },
                        accent: {
                            DEFAULT:    'hsl(var(--accent))',
                            foreground: 'hsl(var(--accent-foreground))',
                        },
                        card: {
                            DEFAULT:    'hsl(var(--card))',
                            foreground: 'hsl(var(--card-foreground))',
                        },
                    },
                    borderRadius: {
                        lg: 'var(--radius)',
                        md: 'calc(var(--radius) - 2px)',
                        sm: 'calc(var(--radius) - 4px)',
                    },
                },
            },
        }
    </script>

    <style>
        /* Hero gradient */
        .hero-gradient {
            background: linear-gradient(135deg,
                hsl(221.2 83.2% 97%) 0%,
                hsl(210 40% 98%) 40%,
                hsl(221.2 83.2% 94%) 100%);
        }
        /* Dotted pattern overlay */
        .hero-dots {
            background-image: radial-gradient(hsl(221.2 83.2% 53.3% / 0.1) 1px, transparent 1px);
            background-size: 24px 24px;
        }
        /* Section fade-in */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fadeUp 0.6s ease-out forwards; }
        .animation-delay-100 { animation-delay: 0.1s; opacity: 0; }
        .animation-delay-200 { animation-delay: 0.2s; opacity: 0; }
        .animation-delay-300 { animation-delay: 0.3s; opacity: 0; }
        .animation-delay-400 { animation-delay: 0.4s; opacity: 0; }
        /* Smooth scroll */
        html { scroll-behavior: smooth; }
    </style>
</head>

<body class="min-h-dvh bg-background text-foreground antialiased" x-data="{ mobileMenu: false }">

    <!-- ================================================================
         NAVIGATION
         ================================================================ -->
    <header class="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="Nawigacja główna">
            <div class="flex h-16 items-center justify-between">

                <!-- Logo -->
                <a href="/" class="flex items-center gap-2.5 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring p-1 -ml-1">
                    <svg class="h-7 w-7 text-primary" xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22V12"/>
                        <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                        <path d="M12 12c0 0-3 1.5-3 5"/>
                        <path d="M12 12c0 0 3 1.5 3 5"/>
                    </svg>
                    <span class="text-xl font-bold tracking-tight text-foreground">Genealog</span>
                </a>

                <!-- Desktop nav links -->
                <div class="hidden md:flex items-center gap-6">
                    <a href="/#features"    class="text-sm text-muted-foreground hover:text-foreground transition-colors">Funkcje</a>
                    <a href="/#how-it-works" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Jak to działa</a>
                    <a href="/#privacy"     class="text-sm text-muted-foreground hover:text-foreground transition-colors">Prywatność</a>
                </div>

                <!-- Desktop CTA buttons -->
                <div class="hidden md:flex items-center gap-3">
                    <a href="/login"
                       class="rounded-md px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent transition-colors">
                        Zaloguj się
                    </a>
                    <a href="/register"
                       class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Zacznij za darmo
                    </a>
                </div>

                <!-- Mobile menu button -->
                <button type="button"
                        @click="mobileMenu = !mobileMenu"
                        class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                        :aria-expanded="mobileMenu"
                        aria-label="Otwórz menu">
                    <i class="fa-solid fa-bars h-5 w-5" x-show="!mobileMenu" aria-hidden="true"></i>
                    <i class="fa-solid fa-xmark h-5 w-5" x-show="mobileMenu" aria-hidden="true"></i>
                </button>

            </div>

            <!-- Mobile menu -->
            <div x-show="mobileMenu"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="md:hidden border-t border-border py-4 space-y-1">
                <a href="/#features"     @click="mobileMenu=false" class="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">Funkcje</a>
                <a href="/#how-it-works" @click="mobileMenu=false" class="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">Jak to działa</a>
                <a href="/#privacy"      @click="mobileMenu=false" class="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">Prywatność</a>
                <div class="border-t border-border pt-3 mt-3 flex flex-col gap-2">
                    <a href="/login"    class="block rounded-md px-3 py-2 text-sm font-medium text-center border border-border hover:bg-accent transition-colors">Zaloguj się</a>
                    <a href="/register" class="block rounded-md px-3 py-2 text-sm font-medium text-center bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">Zacznij za darmo</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- ================================================================
         PAGE CONTENT
         ================================================================ -->
    <main id="main-content">
        <?= $content ?? '' ?>
    </main>

    <!-- ================================================================
         FOOTER
         ================================================================ -->
    <footer class="border-t border-border bg-muted/30">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22V12"/>
                            <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                            <path d="M12 12c0 0-3 1.5-3 5"/>
                            <path d="M12 12c0 0 3 1.5 3 5"/>
                        </svg>
                        <span class="font-bold text-foreground">Genealog</span>
                    </div>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        Platforma do budowania i współdzielenia drzew genealogicznych.
                        Odkryj historię swojej rodziny.
                    </p>
                </div>

                <!-- Linki aplikacji -->
                <div>
                    <h3 class="text-sm font-semibold text-foreground mb-3">Aplikacja</h3>
                    <ul class="space-y-2">
                        <li><a href="/register" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Zarejestruj się</a></li>
                        <li><a href="/login"    class="text-sm text-muted-foreground hover:text-foreground transition-colors">Zaloguj się</a></li>
                        <li><a href="#how-it-works" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Jak to działa</a></li>
                        <li><a href="#features" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Funkcje</a></li>
                    </ul>
                </div>

                <!-- Prawne -->
                <div>
                    <h3 class="text-sm font-semibold text-foreground mb-3">Informacje prawne</h3>
                    <ul class="space-y-2 mb-5">
                        <li>
                            <a href="/privacy" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                                Polityka prywatności
                            </a>
                        </li>
                        <li>
                            <a href="/terms" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                                Regulamin
                            </a>
                        </li>
                    </ul>

                    <!-- Compliance badges -->
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/60 mb-2.5">Zgodność i certyfikaty</p>
                    <div class="flex flex-wrap gap-2" role="list" aria-label="Certyfikaty zgodności">

                        <!-- RODO / GDPR -->
                        <div role="listitem" class="group relative" x-data="{ show: false }"
                             @mouseenter="show=true" @mouseleave="show=false"
                             @focusin="show=true"   @focusout="show=false">
                            <button type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700 hover:bg-emerald-100 hover:border-emerald-300 transition-colors cursor-default focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-describedby="badge-gdpr">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse flex-shrink-0" aria-hidden="true"></span>
                                RODO
                            </button>
                            <div id="badge-gdpr" role="tooltip"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 w-56 rounded-lg border border-border bg-popover shadow-lg p-3 pointer-events-none">
                                <div class="flex items-start gap-2">
                                    <span class="text-emerald-500 mt-0.5 flex-shrink-0" aria-hidden="true">✓</span>
                                    <div>
                                        <p class="text-xs font-semibold text-foreground">RODO / GDPR</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5 leading-relaxed">Rozporządzenie UE 2016/679. Przetwarzamy dane zgodnie z Art. 5, 13–14 i 25 (privacy by design). Prawo do dostępu, sprostowania i usunięcia danych.</p>
                                    </div>
                                </div>
                                <!-- Arrow -->
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-border" aria-hidden="true"></div>
                            </div>
                        </div>

                        <!-- NIS2 -->
                        <div role="listitem" class="group relative" x-data="{ show: false }"
                             @mouseenter="show=true" @mouseleave="show=false"
                             @focusin="show=true"   @focusout="show=false">
                            <button type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-300 transition-colors cursor-default focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-describedby="badge-nis2">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse flex-shrink-0" aria-hidden="true"></span>
                                NIS2
                            </button>
                            <div id="badge-nis2" role="tooltip"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 w-56 rounded-lg border border-border bg-popover shadow-lg p-3 pointer-events-none">
                                <div class="flex items-start gap-2">
                                    <span class="text-blue-500 mt-0.5 flex-shrink-0" aria-hidden="true">✓</span>
                                    <div>
                                        <p class="text-xs font-semibold text-foreground">NIS2</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5 leading-relaxed">Dyrektywa UE 2022/2555 o cyberbezpieczeństwie. Stosujemy zarządzanie ryzykiem, uwierzytelnianie, szyfrowanie i procedury reagowania na incydenty.</p>
                                    </div>
                                </div>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-border" aria-hidden="true"></div>
                            </div>
                        </div>

                        <!-- WCAG 2.2 AA -->
                        <div role="listitem" class="group relative" x-data="{ show: false }"
                             @mouseenter="show=true" @mouseleave="show=false"
                             @focusin="show=true"   @focusout="show=false">
                            <button type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-violet-200 bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-700 hover:bg-violet-100 hover:border-violet-300 transition-colors cursor-default focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-describedby="badge-wcag">
                                <span class="h-1.5 w-1.5 rounded-full bg-violet-500 animate-pulse flex-shrink-0" aria-hidden="true"></span>
                                WCAG 2.2
                            </button>
                            <div id="badge-wcag" role="tooltip"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 w-56 rounded-lg border border-border bg-popover shadow-lg p-3 pointer-events-none">
                                <div class="flex items-start gap-2">
                                    <span class="text-violet-500 mt-0.5 flex-shrink-0" aria-hidden="true">✓</span>
                                    <div>
                                        <p class="text-xs font-semibold text-foreground">WCAG 2.2 AA</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5 leading-relaxed">Web Content Accessibility Guidelines (najnowsza wersja). Kontrast ≥ 4.5:1, pełna obsługa klawiatury, etykiety ARIA, responsywność. Dostępność dla osób z niepełnosprawnościami.</p>
                                    </div>
                                </div>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-border" aria-hidden="true"></div>
                            </div>
                        </div>

                        <!-- OWASP -->
                        <div role="listitem" class="group relative" x-data="{ show: false }"
                             @mouseenter="show=true" @mouseleave="show=false"
                             @focusin="show=true"   @focusout="show=false">
                            <button type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-[10px] font-semibold text-orange-700 hover:bg-orange-100 hover:border-orange-300 transition-colors cursor-default focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-describedby="badge-owasp">
                                <span class="h-1.5 w-1.5 rounded-full bg-orange-500 animate-pulse flex-shrink-0" aria-hidden="true"></span>
                                OWASP
                            </button>
                            <div id="badge-owasp" role="tooltip"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 w-56 rounded-lg border border-border bg-popover shadow-lg p-3 pointer-events-none">
                                <div class="flex items-start gap-2">
                                    <span class="text-orange-500 mt-0.5 flex-shrink-0" aria-hidden="true">✓</span>
                                    <div>
                                        <p class="text-xs font-semibold text-foreground">OWASP Top 10</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5 leading-relaxed">Zabezpieczenia według OWASP Top 10 (2021). Ochrona przed SQLi, XSS, CSRF, IDOR, Broken Auth. Prepared statements, token rotacja, rate limiting.</p>
                                    </div>
                                </div>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-border" aria-hidden="true"></div>
                            </div>
                        </div>

                        <!-- ISO 27001 inspired -->
                        <div role="listitem" class="group relative" x-data="{ show: false }"
                             @mouseenter="show=true" @mouseleave="show=false"
                             @focusin="show=true"   @focusout="show=false">
                            <button type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-100 hover:border-slate-300 transition-colors cursor-default focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-describedby="badge-iso">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-pulse flex-shrink-0" aria-hidden="true"></span>
                                ISO 27001
                            </button>
                            <div id="badge-iso" role="tooltip"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute bottom-full left-0 mb-2 z-50 w-60 rounded-lg border border-border bg-popover shadow-lg p-3 pointer-events-none">
                                <div class="flex items-start gap-2">
                                    <span class="text-slate-500 mt-0.5 flex-shrink-0" aria-hidden="true">✓</span>
                                    <div>
                                        <p class="text-xs font-semibold text-foreground">ISO/IEC 27001 — Ready</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5 leading-relaxed">Architektura aplikacji przygotowana pod certyfikację ISO/IEC 27001. Zarządzanie ryzykiem, kontrola dostępu, audyt logów i procedury bezpieczeństwa zgodne z wymaganiami normy.</p>
                                    </div>
                                </div>
                                <div class="absolute top-full left-4 border-4 border-transparent border-t-border" aria-hidden="true"></div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Bottom bar -->
            <div class="border-t border-border mt-8 pt-6 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-xs text-muted-foreground">
                    &copy; <?= date('Y') ?> Genealog. Wszelkie prawa zastrzeżone.
                </p>
                <div class="flex items-center gap-4 text-xs text-muted-foreground">
                    <a href="/privacy" class="hover:text-foreground transition-colors">Prywatność</a>
                    <span aria-hidden="true">·</span>
                    <a href="/terms"   class="hover:text-foreground transition-colors">Regulamin</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
