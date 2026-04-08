<!DOCTYPE html>
<html lang="pl" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Genealog — Twoje drzewo rodzinne') ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Csrf::getToken()) ?>">
    <link rel="icon" href="data:,">
    <title><?= htmlspecialchars(($pageTitle ?? 'Dashboard') . ' — Genealog') ?></title>

    <!-- Tailwind CSS (lokalny vendor) -->
    <script src="/vendor/tailwind.js"></script>
    <link rel="stylesheet" href="/css/globals.css">

    <!-- Font Awesome 6 Free (lokalny vendor) -->
    <link rel="preload" href="/vendor/fontawesome/webfonts/fa-solid-900.woff2"
          as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">

    <!-- Alpine.js (lokalny vendor) -->
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
                        muted: {
                            DEFAULT:    'hsl(var(--muted))',
                            foreground: 'hsl(var(--muted-foreground))',
                        },
                        card: {
                            DEFAULT:    'hsl(var(--card))',
                            foreground: 'hsl(var(--card-foreground))',
                        },
                    },
                },
            },
        }
    </script>
</head>

<!--
  AppLayout — wireframe:

  ┌──────────────────────────────────────────────────────────────────────┐
  │ Header: [Logo Genealog]   [Drzewa] [Poszukiwania]   [Avatar ▾]      │
  ├──────────────────────────────────────────────────────────────────────┤
  │                                                                      │
  │  [Flash messages — jeśli są]                                        │
  │                                                                      │
  │  ┌── main content (slot) ────────────────────────────────────────┐  │
  │  │  Witaj, Jan!                                                   │  │
  │  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐        │  │
  │  │  │ Moje drzewa  │  │ Ostatnio     │  │ Statystyki   │        │  │
  │  │  │ [Nowe drzewo]│  │ aktywne      │  │              │        │  │
  │  │  └──────────────┘  └──────────────┘  └──────────────┘        │  │
  │  └────────────────────────────────────────────────────────────────┘  │
  │                                                                      │
  ├──────────────────────────────────────────────────────────────────────┤
  │ Footer                                                               │
  └──────────────────────────────────────────────────────────────────────┘
-->
<body class="min-h-dvh bg-[hsl(var(--background))] flex flex-col"
      x-data="{ mobileMenuOpen: false, userMenuOpen: false }">

    <!-- ZAD-4.9 (D10) WCAG 2.4.1 — Skip link: przeskocz do głównej treści.
         Widoczny tylko po :focus — pierwsza opcja Tab dla keyboard users. -->
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100]
              focus:bg-[hsl(var(--background))] focus:text-[hsl(var(--foreground))]
              focus:px-4 focus:py-2 focus:rounded focus:shadow-lg
              focus:ring-2 focus:ring-[hsl(var(--ring))]">
        Przejdź do treści
    </a>

    <!-- ================================================================
         HEADER
         ================================================================ -->
    <header class="sticky top-0 z-50 w-full border-b border-[hsl(var(--border))]
                   bg-[hsl(var(--background)/0.95)] backdrop-blur-sm">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">

            <!-- Logo -->
            <a href="/dashboard"
               class="flex items-center gap-2 font-bold text-lg tracking-tight
                      focus-visible:outline-none focus-visible:ring-2
                      focus-visible:ring-[hsl(var(--ring))] rounded-md px-1">
                <svg class="h-7 w-7 text-[hsl(var(--primary))]"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22V12"/>
                    <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                    <path d="M12 12c0 0-3 1.5-3 5"/>
                    <path d="M12 12c0 0 3 1.5 3 5"/>
                </svg>
                <span>Genealog</span>
            </a>

            <!-- Nav desktop -->
            <nav class="hidden md:flex items-center gap-1" aria-label="Główna nawigacja">
                <?php
                require_once __DIR__ . '/../atoms/icon.php';
                $navItems = [
                    ['href' => '/dashboard',   'label' => 'Dashboard',     'icon' => 'house'],
                    ['href' => '/trees',        'label' => 'Moje drzewa',   'icon' => 'sitemap'],
                    ['href' => '/search',       'label' => 'Poszukiwania',  'icon' => 'magnifying-glass'],
                ];
                // PHP 8.1+: str_starts_with($null, ...) jest deprecated — fallback na '/'
                $currentPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

                foreach ($navItems as $item):
                    $isActive = str_starts_with($currentPath, $item['href']);
                    $activeClass = $isActive
                        ? 'bg-[hsl(var(--accent))] text-[hsl(var(--foreground))] font-medium'
                        : 'text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--foreground))]';
                ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>"
                       class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm transition-colors
                              focus-visible:outline-none focus-visible:ring-2
                              focus-visible:ring-[hsl(var(--ring))]
                              <?= $activeClass ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?php render_icon($item['icon'], 'solid', 'h-4 w-4') ?>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Prawa strona headera: Notification bell + Avatar + menu użytkownika -->
            <div class="flex items-center gap-3">

                <!-- Notification bell -->
                <div class="relative" x-data="notificationBell()" @click.outside="open = false">
                    <button type="button"
                            @click="toggle()"
                            class="relative flex h-9 w-9 items-center justify-center rounded-md
                                   text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))]"
                            :aria-label="'Powiadomienia' + (count > 0 ? ', ' + count + ' nowych' : '')">
                        <?php render_icon('bell', 'solid', 'h-5 w-5') ?>
                        <span x-show="count > 0" x-cloak
                              class="absolute -top-1 -right-1 inline-flex h-4 min-w-4 items-center justify-center
                                     rounded-full bg-[hsl(var(--destructive))] px-1 text-[10px] font-bold
                                     text-[hsl(var(--destructive-foreground))]"
                              x-text="count > 9 ? '9+' : count"></span>
                    </button>

                    <!-- Dropdown — ZAD-4.10 (D11) WCAG 4.1.3: aria-live dla ogłaszania nowych powiadomień -->
                    <div x-show="open" x-cloak x-transition
                         role="dialog"
                         aria-label="Powiadomienia"
                         aria-live="polite"
                         aria-atomic="false"
                         class="absolute right-0 mt-2 w-80 origin-top-right rounded-md border border-[hsl(var(--border))]
                                bg-[hsl(var(--popover))] shadow-lg focus:outline-none z-50">
                        <div class="flex items-center justify-between border-b border-[hsl(var(--border))] px-4 py-2.5">
                            <h3 class="text-sm font-semibold text-[hsl(var(--foreground))]">Powiadomienia</h3>
                            <button type="button" x-show="count > 0" @click="markAllRead()"
                                    class="text-xs text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                Oznacz wszystkie
                            </button>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <template x-if="loading">
                                <div class="px-4 py-6 text-sm text-center text-[hsl(var(--muted-foreground))]">
                                    Ładowanie…
                                </div>
                            </template>
                            <template x-if="!loading && items.length === 0">
                                <div class="px-4 py-6 text-sm text-center text-[hsl(var(--muted-foreground))]">
                                    Brak nowych powiadomień
                                </div>
                            </template>
                            <template x-for="n in items" :key="n.id">
                                <a :href="n.link || '#'"
                                   @click.prevent="clickNotification(n)"
                                   class="block border-b border-[hsl(var(--border))] px-4 py-3 hover:bg-[hsl(var(--accent))] cursor-pointer"
                                   :class="{ 'bg-[hsl(var(--accent)/0.3)]': !n.is_read }">
                                    <div class="text-sm font-medium text-[hsl(var(--foreground))]"
                                         x-text="n.title"></div>
                                    <div x-show="n.body" class="mt-0.5 text-xs text-[hsl(var(--muted-foreground))] line-clamp-2"
                                         x-text="n.body"></div>
                                    <div class="mt-1 text-xs text-[hsl(var(--muted-foreground))]"
                                         x-text="formatTime(n.created_at)"></div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Przycisk hamburger — mobile -->
                <button
                    type="button"
                    class="md:hidden flex h-9 w-9 items-center justify-center rounded-md
                           text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))]"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="mobile-menu"
                    aria-label="Otwórz menu"
                >
                    <?php render_icon('bars', 'solid', 'h-5 w-5') ?>
                </button>

                <!-- Menu użytkownika — desktop -->
                <div class="relative hidden md:block" @click.outside="userMenuOpen = false">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full
                               focus-visible:outline-none focus-visible:ring-2
                               focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2"
                        @click="userMenuOpen = !userMenuOpen"
                        :aria-expanded="userMenuOpen"
                        aria-haspopup="true"
                        aria-label="Menu użytkownika: <?= htmlspecialchars($currentUser['name'] ?? 'Użytkownik') ?>"
                    >
                        <?php
                        require_once __DIR__ . '/../atoms/avatar.php';
                        render_avatar(
                            $currentUser['name'] ?? 'Użytkownik',
                            $currentUser['avatar'] ?? '',
                            'md'
                        );
                        ?>
                        <?php render_icon('chevron-down', 'solid', 'h-3 w-3 text-[hsl(var(--muted-foreground))]') ?>
                    </button>

                    <!-- Dropdown menu -->
                    <div
                        x-show="userMenuOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-56 origin-top-right rounded-lg border
                               border-[hsl(var(--border))] bg-[hsl(var(--popover))] p-1 shadow-lg
                               focus:outline-none"
                        role="menu"
                        aria-orientation="vertical"
                    >
                        <!-- Nagłówek dropdown -->
                        <div class="px-3 py-2 border-b border-[hsl(var(--border))] mb-1">
                            <p class="text-sm font-medium text-[hsl(var(--foreground))] truncate">
                                <?= htmlspecialchars($currentUser['name'] ?? 'Użytkownik') ?>
                            </p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))] truncate">
                                <?= htmlspecialchars($currentUser['email'] ?? '') ?>
                            </p>
                        </div>

                        <a href="/profile" role="menuitem"
                           class="flex items-center gap-2 rounded-md px-3 py-2 text-sm
                                  text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]
                                  focus-visible:outline-none focus-visible:bg-[hsl(var(--accent))]">
                            <?php render_icon('user', 'solid', 'h-4 w-4') ?>
                            Mój profil
                        </a>
                        <a href="/settings" role="menuitem"
                           class="flex items-center gap-2 rounded-md px-3 py-2 text-sm
                                  text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]
                                  focus-visible:outline-none focus-visible:bg-[hsl(var(--accent))]">
                            <?php render_icon('gear', 'solid', 'h-4 w-4') ?>
                            Ustawienia
                        </a>

                        <?php if (\App\Core\Session::get('is_admin')): ?>
                        <div class="border-t border-[hsl(var(--border))] my-1"></div>
                        <a href="/admin" role="menuitem"
                           class="flex items-center gap-2 rounded-md px-3 py-2 text-sm
                                  text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]
                                  focus-visible:outline-none focus-visible:bg-[hsl(var(--accent))]">
                            <?php render_icon('shield-halved', 'solid', 'h-4 w-4') ?>
                            Panel admina
                        </a>
                        <?php endif; ?>

                        <div class="border-t border-[hsl(var(--border))] my-1"></div>

                        <form method="POST" action="/logout">
                            <?= \App\Core\Csrf::hiddenInput() ?>
                            <button type="submit" role="menuitem"
                                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm
                                           text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive)/0.1)]
                                           focus-visible:outline-none focus-visible:bg-[hsl(var(--destructive)/0.1)]">
                                <?php render_icon('arrow-right-from-bracket', 'solid', 'h-4 w-4') ?>
                                Wyloguj się
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div
            id="mobile-menu"
            x-show="mobileMenuOpen"
            x-transition
            class="md:hidden border-t border-[hsl(var(--border))] bg-[hsl(var(--background))] px-4 pb-4 pt-2"
        >
            <nav class="flex flex-col gap-1" aria-label="Mobile nawigacja">
                <?php foreach ($navItems as $item):
                    $isActive = str_starts_with($currentPath, $item['href']);
                    $activeClass = $isActive
                        ? 'bg-[hsl(var(--accent))] text-[hsl(var(--foreground))] font-medium'
                        : 'text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))]';
                ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>"
                       class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm transition-colors <?= $activeClass ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?php render_icon($item['icon'], 'solid', 'h-4 w-4') ?>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>

                <div class="border-t border-[hsl(var(--border))] my-2"></div>

                <div class="flex items-center gap-3 px-3 py-2">
                    <?php render_avatar($currentUser['name'] ?? 'Użytkownik', '', 'sm'); ?>
                    <div>
                        <p class="text-sm font-medium"><?= htmlspecialchars($currentUser['name'] ?? 'Użytkownik') ?></p>
                        <p class="text-xs text-[hsl(var(--muted-foreground))]"><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
                    </div>
                </div>

                <a href="/profile" class="rounded-md px-3 py-2.5 text-sm text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))]">
                    Mój profil
                </a>

                <?php if (\App\Core\Session::get('is_admin')): ?>
                <a href="/admin" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))]">
                    <?php render_icon('shield-halved', 'solid', 'h-4 w-4') ?>
                    Panel admina
                </a>
                <?php endif; ?>

                <form method="POST" action="/logout" class="mt-1">
                    <?= \App\Core\Csrf::hiddenInput() ?>
                    <button type="submit"
                            class="flex w-full rounded-md px-3 py-2.5 text-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive)/0.1)]">
                        Wyloguj się
                    </button>
                </form>
            </nav>
        </div>
    </header>

    <!-- ================================================================
         MAIN CONTENT
         ================================================================ -->
    <main id="main-content" class="flex-1" tabindex="-1">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

            <!-- Flash messages -->
            <?php
            require_once __DIR__ . '/../molecules/flash-messages.php';
            render_flash_messages();
            ?>

            <!-- Slot — zawartość strony -->
            <?= $content ?? '' ?>

        </div>
    </main>

    <!-- ================================================================
         FOOTER
         ================================================================ -->
    <footer class="border-t border-[hsl(var(--border))] bg-[hsl(var(--muted))]">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-[hsl(var(--muted-foreground))]">
                    &copy; <?= date('Y') ?> Genealog. Wszelkie prawa zastrzeżone.
                </p>
                <nav class="flex gap-4" aria-label="Linki pomocnicze">
                    <a href="/privacy"
                       class="text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] rounded">
                        Polityka prywatności
                    </a>
                    <a href="/terms"
                       class="text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] rounded">
                        Regulamin
                    </a>
                </nav>
            </div>
        </div>
    </footer>

    <!-- ================================================================
         IMPERSONATION BANNER (visible only during admin impersonation)
         ================================================================ -->
    <?php if (\App\Core\Session::has('_admin_user_id')): ?>
        <div class="fixed bottom-0 inset-x-0 z-50 bg-red-600 text-white shadow-lg">
            <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <?php render_icon('users', 'solid', 'h-4 w-4 flex-shrink-0') ?>
                    <span>
                        Impersonujesz konto:
                        <strong><?= htmlspecialchars(\App\Core\Session::get('user_name', 'Użytkownik') ?? 'Użytkownik') ?></strong>
                        (<?= htmlspecialchars(\App\Core\Session::get('user_email', '') ?? '') ?>)
                    </span>
                </div>
                <form method="POST" action="/admin/impersonate/exit" class="flex-shrink-0">
                    <?= \App\Core\Csrf::hiddenInput() ?>
                    <button type="submit"
                            class="rounded-md bg-white/20 hover:bg-white/30 px-4 py-1.5 text-sm font-semibold transition-colors">
                        Zakończ impersonację
                    </button>
                </form>
            </div>
        </div>
        <!-- Spacer so page content isn't hidden behind the banner -->
        <div class="h-14"></div>
    <?php endif; ?>

    <script>
    function notificationBell() {
        return {
            count: 0,
            items: [],
            open: false,
            loading: false,
            pollTimer: null,

            init() {
                this.fetchCount();
                this.startPolling();
                // N1: pauza polling gdy karta jest ukryta (mobile battery)
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.stopPolling();
                    } else {
                        this.fetchCount();
                        this.startPolling();
                    }
                });
            },

            startPolling() {
                if (this.pollTimer) return;
                this.pollTimer = setInterval(() => this.fetchCount(), 30000);
            },

            stopPolling() {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
            },

            async fetchCount() {
                try {
                    const r = await fetch('/api/notifications/count', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!r.ok) return;
                    const data = await r.json();
                    this.count = data.unread ?? 0;
                } catch (e) { /* network offline, ignore */ }
            },

            async fetchList() {
                this.loading = true;
                try {
                    const r = await fetch('/api/notifications', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    const data = await r.json();
                    this.items = data.data ?? [];
                    this.count = data.unread ?? this.count;
                } catch (e) {
                    console.warn('Notifications fetch failed:', e);
                } finally {
                    this.loading = false;
                }
            },

            async toggle() {
                this.open = !this.open;
                if (this.open) await this.fetchList();
            },

            getCsrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            },

            updateCsrfToken(data) {
                if (data && data.csrf) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = data.csrf;
                }
            },

            async markAllRead() {
                try {
                    const r = await fetch('/api/notifications/read-all', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: new URLSearchParams({ _csrf_token: this.getCsrfToken() }),
                    });
                    if (r.ok) {
                        this.updateCsrfToken(await r.json());
                        this.items = this.items.map(n => ({ ...n, is_read: 1 }));
                        this.count = 0;
                    }
                } catch (e) { /* ignore */ }
            },

            async clickNotification(n) {
                try {
                    const r = await fetch('/api/notifications/' + encodeURIComponent(n.id) + '/read', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: new URLSearchParams({ _csrf_token: this.getCsrfToken() }),
                    });
                    if (r.ok) this.updateCsrfToken(await r.json());
                } catch (e) { /* ignore */ }
                if (n.link) window.location.href = n.link;
            },

            formatTime(ts) {
                if (!ts) return '';
                const date = new Date(ts.replace(' ', 'T'));
                const diffMs = Date.now() - date.getTime();
                const diffMin = Math.floor(diffMs / 60000);
                if (diffMin < 1) return 'przed chwilą';
                if (diffMin < 60) return diffMin + ' min temu';
                const diffH = Math.floor(diffMin / 60);
                if (diffH < 24) return diffH + ' godz. temu';
                const diffD = Math.floor(diffH / 24);
                if (diffD < 7) return diffD + ' dni temu';
                return date.toLocaleDateString('pl-PL');
            },
        };
    }
    </script>
</body>
</html>
