<!DOCTYPE html>
<html lang="pl" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Genealog — aplikacja genealogiczna') ?>">
    <link rel="icon" href="data:,">
    <title><?= htmlspecialchars(($pageTitle ?? 'Genealog') . ' — Genealog') ?></title>

    <!-- Tailwind CSS (lokalny vendor) -->
    <script src="/vendor/tailwind.js"></script>
    <!-- Globalne style (tokeny shadcn, animacje) -->
    <link rel="stylesheet" href="/css/globals.css">

    <!-- Font Awesome 6 Free (lokalny vendor) -->
    <link rel="preload" href="/vendor/fontawesome/webfonts/fa-solid-900.woff2"
          as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">

    <!-- Alpine.js (lokalny vendor) -->
    <script defer src="/vendor/alpine.min.js"></script>

    <!-- Tailwind config — CSS variables -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        border:      'hsl(var(--border))',
                        input:       'hsl(var(--input))',
                        ring:        'hsl(var(--ring))',
                        background:  'hsl(var(--background))',
                        foreground:  'hsl(var(--foreground))',
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
                        destructive: {
                            DEFAULT:    'hsl(var(--destructive))',
                            foreground: 'hsl(var(--destructive-foreground))',
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
</head>

<!--
  AuthLayout — wireframe:

  ┌──────────────────────────────────────────────────────────────┐
  │                                                              │
  │                    [Logo + Tagline]                          │
  │                                                              │
  │          ┌──────────────────────────────────┐               │
  │          │ CardHeader: Tytuł strony          │               │
  │          │ CardContent: Formularz            │               │
  │          │ CardFooter: Link pomocniczy       │               │
  │          └──────────────────────────────────┘               │
  │                                                              │
  │                  [Footer: © Genealog 2026]                   │
  └──────────────────────────────────────────────────────────────┘

  Mobile: pełna szerokość, padding 16px, brak cienia karty
  Tablet+: karta wyśrodkowana, max-w-xl, shadow-md
-->
<body class="min-h-dvh bg-[hsl(var(--muted))] flex flex-col">

    <!-- Główny kontener — wypełnia ekran, centruje pionowo -->
    <main
        class="flex flex-1 flex-col items-center justify-center
               px-4 py-12 sm:px-6 lg:px-8"
    >
        <!-- Logo -->
        <div class="mb-8 flex flex-col items-center gap-2">
            <a href="/" class="flex items-center gap-2 focus-visible:outline-none
               focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] rounded-md p-1">
                <!-- Logo SVG — drzewo genealogiczne -->
                <svg class="h-10 w-10 text-[hsl(var(--primary))]"
                     xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M12 22V12"/>
                    <path d="M12 12C12 12 7 10 7 6a5 5 0 0 1 10 0c0 4-5 6-5 6z"/>
                    <path d="M12 12c0 0-3 1.5-3 5"/>
                    <path d="M12 12c0 0 3 1.5 3 5"/>
                </svg>
                <span class="text-2xl font-bold tracking-tight text-[hsl(var(--foreground))]">
                    Genealog
                </span>
            </a>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">
                Odkryj swoje korzenie
            </p>
        </div>

        <!-- Flash messages (po przekierowaniu) -->
        <div class="w-full max-w-2xl">
            <?php require_once __DIR__ . '/../molecules/flash-messages.php'; ?>
            <?php render_flash_messages(); ?>
        </div>

        <!-- Karta formularza -->
        <div class="w-full max-w-2xl">
            <?= $content ?? '' ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 text-center text-xs text-[hsl(var(--muted-foreground))]">
        <p>&copy; <?= date('Y') ?> Genealog. Wszelkie prawa zastrzeżone.</p>
    </footer>

</body>
</html>
