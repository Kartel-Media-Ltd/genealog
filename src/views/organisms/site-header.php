<?php
/**
 * Organism: Site Header
 * Sticky top header: Logo | Nav | [Bell] Avatar▾
 *
 * Użycie:
 *   render_site_header($currentUser);
 *
 * @param array $user ['name' => '...', 'email' => '...', 'avatar' => '']
 */

require_once __DIR__ . '/../atoms/avatar.php';

function render_site_header(array $user): void
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $navItems = [
        ['href' => '/dashboard', 'label' => 'Dashboard'],
        ['href' => '/trees',     'label' => 'Moje drzewa'],
        ['href' => '/search',    'label' => 'Poszukiwania'],
    ];
    ?>
    <header class="sticky top-0 z-50 w-full border-b border-border bg-background/95 backdrop-blur-sm"
            x-data="{ mobileMenuOpen: false, userMenuOpen: false }">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">

            <!-- Logo -->
            <a href="/dashboard"
               class="flex items-center gap-2 font-bold text-lg tracking-tight rounded-md px-1
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <svg class="h-7 w-7 text-primary" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22V12"/>
                    <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                    <path d="M12 12c0 0-3 1.5-3 5"/>
                    <path d="M12 12c0 0 3 1.5 3 5"/>
                </svg>
                <span>Genealog</span>
            </a>

            <!-- Nav desktop -->
            <nav class="hidden md:flex items-center gap-1" aria-label="Główna nawigacja">
                <?php foreach ($navItems as $item):
                    $isActive = str_starts_with($currentPath, $item['href']);
                    $cls = $isActive
                        ? 'bg-accent text-foreground font-medium'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground';
                ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>"
                       class="rounded-md px-3 py-2 text-sm transition-colors
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring
                              <?= $cls ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Right side -->
            <div class="flex items-center gap-2">

                <!-- Bell placeholder -->
                <button type="button"
                        class="hidden md:flex h-9 w-9 items-center justify-center rounded-md
                               text-muted-foreground hover:bg-accent
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label="Powiadomienia">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" aria-hidden="true">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </button>

                <!-- Mobile hamburger -->
                <button type="button"
                        class="md:hidden flex h-9 w-9 items-center justify-center rounded-md
                               text-muted-foreground hover:bg-accent
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        :aria-expanded="mobileMenuOpen"
                        aria-controls="mobile-nav"
                        aria-label="Menu">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="3" y1="6"  x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>

                <!-- User menu desktop -->
                <div class="relative hidden md:block" @click.outside="userMenuOpen = false">
                    <button type="button"
                            class="flex items-center gap-2 rounded-full
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            @click="userMenuOpen = !userMenuOpen"
                            :aria-expanded="userMenuOpen"
                            aria-haspopup="menu"
                            aria-label="Menu użytkownika">
                        <?php render_avatar($user['name'], $user['avatar'] ?? '', 'md') ?>
                        <svg class="h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <!-- Dropdown -->
                    <div x-show="userMenuOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-56 origin-top-right rounded-lg border border-border
                                bg-popover p-1 shadow-lg"
                         role="menu" aria-orientation="vertical">

                        <div class="px-3 py-2 border-b border-border mb-1">
                            <p class="text-sm font-medium text-foreground truncate">
                                <?= htmlspecialchars($user['name']) ?>
                            </p>
                            <p class="text-xs text-muted-foreground truncate">
                                <?= htmlspecialchars($user['email'] ?? '') ?>
                            </p>
                        </div>

                        <a href="/profile" role="menuitem"
                           class="flex items-center gap-2 rounded-md px-3 py-2 text-sm
                                  text-foreground hover:bg-accent focus-visible:outline-none focus-visible:bg-accent">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Mój profil
                        </a>
                        <a href="/settings" role="menuitem"
                           class="flex items-center gap-2 rounded-md px-3 py-2 text-sm
                                  text-foreground hover:bg-accent focus-visible:outline-none focus-visible:bg-accent">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
                            </svg>
                            Ustawienia
                        </a>

                        <div class="border-t border-border my-1"></div>

                        <form method="POST" action="/logout">
                            <?php
                            if (!function_exists('render_csrf_input')) {
                                function render_csrf_input(): void {
                                    echo '<input type="hidden" name="_csrf_token" value="'
                                        . htmlspecialchars($_SESSION['_csrf_token'] ?? '') . '">';
                                }
                            }
                            render_csrf_input();
                            ?>
                            <button type="submit" role="menuitem"
                                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm
                                           text-destructive hover:bg-destructive/10
                                           focus-visible:outline-none focus-visible:bg-destructive/10">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/>
                                    <line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                                Wyloguj się
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile nav -->
        <div id="mobile-nav"
             x-show="mobileMenuOpen"
             x-transition
             class="md:hidden border-t border-border bg-background px-4 pb-4 pt-2">
            <nav class="flex flex-col gap-1" aria-label="Mobile nawigacja">
                <?php foreach ($navItems as $item):
                    $isActive = str_starts_with($currentPath, $item['href']);
                    $cls = $isActive
                        ? 'bg-accent text-foreground font-medium'
                        : 'text-muted-foreground hover:bg-accent';
                ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>"
                       class="rounded-md px-3 py-2.5 text-sm transition-colors <?= $cls ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>

                <div class="border-t border-border my-2"></div>
                <div class="flex items-center gap-3 px-3 py-2">
                    <?php render_avatar($user['name'], '', 'sm') ?>
                    <div>
                        <p class="text-sm font-medium"><?= htmlspecialchars($user['name']) ?></p>
                        <p class="text-xs text-muted-foreground"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                    </div>
                </div>
                <form method="POST" action="/logout" class="mt-1">
                    <?php render_csrf_input() ?>
                    <button type="submit"
                            class="flex w-full rounded-md px-3 py-2.5 text-sm
                                   text-destructive hover:bg-destructive/10">
                        Wyloguj się
                    </button>
                </form>
            </nav>
        </div>
    </header>
    <?php
}
