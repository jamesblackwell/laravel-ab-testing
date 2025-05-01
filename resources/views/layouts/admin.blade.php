<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - A/B Testing</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
    {{-- The host application should ensure Tailwind CSS is loaded --}}
    <script src="https://cdn.tailwindcss.com"></script> {{-- Added Tailwind CDN --}}

    {{-- Add Alpine JS if needed --}}
    {{-- <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/tooltip@3.x.x/dist/cdn.min.js"></script> --}} {{-- Removed Alpine Tooltip CDN --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> {{-- Uncommented Alpine Core CDN --}}
    {{-- The host application should ensure Alpine.js is loaded if tooltips are desired --}}

    <!-- Tippy.js -->
    <!-- https://atomiks.github.io/tippyjs/v6 -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>

    <style>
        /* Optional: Add Tailwind config customizations here if needed via CDN */
        /* Example:
        tailwind.config = {
          theme: {
            extend: {
              colors: {
                clifford: '#da373d',
              }
            }
          }
        }
        */
        [x-cloak] {
            display: none !important;
        }

        .text-xxs {
            font-size: 0.65rem;
            line-height: 0.9rem;
        }
    </style>
</head>

<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-300">
    <div class="min-h-screen">
        {{-- Placeholder for potential navigation if needed --}}
        {{-- <nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
            Navigation Content
        </nav> --}}

        <!-- Page Heading -->
        <header class="bg-white dark:bg-gray-800 ">
            <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                @yield('header')
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8 mt-2">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Tippy.js Alpine Integration -->
    <script>
        document.addEventListener('alpine:init', () => {
            // Magic: $tooltip
            Alpine.magic('tooltip', el => message => {
                let instance = tippy(el, {
                    content: message,
                    trigger: 'manual'
                })

                instance.show()

                setTimeout(() => {
                    instance.hide()

                    setTimeout(() => instance.destroy(), 150)
                }, 2000)
            })

            // Directive: x-tooltip
            Alpine.directive('tooltip', (el, {
                expression
            }) => {
                tippy(el, {
                    content: expression
                })
            })
        })
    </script>
    @stack('scripts')
</body>

</html>
