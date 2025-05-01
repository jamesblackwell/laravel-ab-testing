<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - A/B testing dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
    {{-- The host application should ensure Tailwind CSS is loaded --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Add Alpine JS if needed --}}
    {{-- <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/tooltip@3.x.x/dist/cdn.min.js"></script> --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('A/B Testing') }}
                </h2>
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8 mt-2">
                <div class="space-y-8">
                    @forelse ($experiments as $experimentName => $experiment)
                        {{-- Card Replacement --}}
                        <div class="bg-white dark:bg-gray-800 -md sm:rounded-2xl overflow-hidden p-6">
                            {{-- Experiment Header --}}
                            <div class="flex flex-wrap justify-between items-start gap-4" x-data>
                                {{-- Left Side: Name & Info --}}
                                <div class="flex flex-col sm:flex-row sm:space-x-2 sm:items-center text-sm">
                                    <h3
                                        class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 order-1 sm:order-none">
                                        {{ $experimentName }}
                                    </h3>
                                    <span class="text-gray-500 dark:text-gray-400 order-3 sm:order-none">
                                        {{ $experiment['duration'] }}
                                    </span>
                                    <span
                                        class="hidden sm:inline text-gray-500 dark:text-gray-400 order-4 sm:order-none">
                                        -
                                    </span>
                                    @if ($experiment['active'])
                                        <span class="inline text-green-600 order-2 sm:order-none">Active</span>
                                    @else
                                        <span class="inline text-orange-500 order-2 sm:order-none">Inactive</span>
                                    @endif
                                </div>

                                {{-- Right Side: Significance Indicators --}}
                                <div class="flex space-x-3 cursor-help"
                                    x-tooltip="P-value = probability results are due to chance (lower is better). Confidence = 1 - P-value.">
                                    {{-- Primary Significance --}}
                                    <div class="flex flex-col items-end">
                                        <span class="text-xs  mb-1">Primary Goal</span>
                                        @if (isset($experiment['stats']['error']))
                                            <span class="bg-gray-500 text-white px-2 py-1 rounded text-xs font-bold">
                                                Error: {{ Str::limit($experiment['stats']['error'], 30) }}
                                            </span>
                                        @elseif ($experiment['stats']['significant'])
                                            <span class="bg-green-600 text-white px-2 py-1 rounded text-xs font-bold">
                                                Significant (p={{ number_format($experiment['stats']['p_value'], 4) }})
                                            </span>
                                            <span class="text-xxs text-gray-500 dark:text-gray-400 mt-1">
                                                {{ number_format((1 - $experiment['stats']['p_value']) * 100, 1) }}%
                                                conf.
                                            </span>
                                        @else
                                            <span class="bg-yellow-500 text-white px-2 py-1 rounded text-xs font-bold">
                                                Not Significant
                                                (p={{ number_format($experiment['stats']['p_value'], 4) }})
                                            </span>
                                            <span class="text-xxs text-gray-500 dark:text-gray-400 mt-1">
                                                {{ number_format((1 - $experiment['stats']['p_value']) * 100, 1) }}%
                                                conf.
                                            </span>
                                        @endif
                                    </div>
                                    {{-- Secondary Significance --}}
                                    @if (!isset($experiment['stats']['error']))
                                        {{-- Only show secondary if primary didn't error --}}
                                        <div class="flex flex-col items-end">
                                            <span class="text-xs  mb-1">Secondary
                                                Goal</span>
                                            @if ($experiment['stats']['significant_secondary'])
                                                <span
                                                    class="bg-green-600/80 text-white px-2 py-1 rounded text-xs font-bold">
                                                    Significant
                                                    (p={{ number_format($experiment['stats']['p_value_secondary'], 4) }})
                                                </span>
                                                <span class="text-xxs text-gray-500 dark:text-gray-400 mt-1">
                                                    {{ number_format((1 - $experiment['stats']['p_value_secondary']) * 100, 1) }}%
                                                    conf.
                                                </span>
                                            @else
                                                <span
                                                    class="bg-yellow-500/80 text-white px-2 py-1 rounded text-xs font-bold">
                                                    Not Significant
                                                    (p={{ number_format($experiment['stats']['p_value_secondary'], 4) }})
                                                </span>
                                                <span class="text-xxs text-gray-500 dark:text-gray-400 mt-1">
                                                    {{ number_format((1 - $experiment['stats']['p_value_secondary']) * 100, 1) }}%
                                                    conf.
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Variants Section --}}
                            @if (!isset($experiment['stats']['error']))
                                <div class="mt-6">
                                    <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2 font-semibold">
                                        Variants
                                    </div>

                                    <div class="space-y-4">
                                        @foreach ($experiment['stats']['variants'] as $variantName => $variant)
                                            {{-- Inner Card Replacement --}}
                                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-md p-3">
                                                <div class="flex flex-col">
                                                    {{-- Variant Name & Views --}}
                                                    <div class="flex justify-between items-center mb-3">
                                                        <span
                                                            class="font-medium text-gray-800 dark:text-gray-200">{{ $variantName }}</span>
                                                        <div class="text-right">
                                                            <span class="text-xs  block">Views</span>
                                                            <span
                                                                class="font-semibold block">{{ number_format($variant['views']) }}</span>
                                                        </div>
                                                    </div>

                                                    {{-- Primary Conversions --}}
                                                    <div class="border-t border-gray-200 dark:border-gray-600 pt-3">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <span class="text-xs ">Primary
                                                                Goal</span>
                                                            @if ($variantName !== 'control')
                                                                @php
                                                                    $improvement = $variant['improvement']; // Improvement is now pre-calculated
                                                                    if (is_infinite($improvement)) {
                                                                        $improvementText = '∞%';
                                                                        $textColor =
                                                                            'text-green-600 dark:text-green-400';
                                                                    } elseif ($improvement === null) {
                                                                        $improvementText = 'N/A';
                                                                        $textColor = 'text-gray-500 dark:text-gray-400';
                                                                    } else {
                                                                        $improvement = round($improvement, 1);
                                                                        $textColor =
                                                                            $improvement >= 0
                                                                                ? 'text-green-600 dark:text-green-400'
                                                                                : 'text-red-600 dark:text-red-400';
                                                                        $sign = $improvement >= 0 ? '+' : '';
                                                                        $improvementText = $sign . $improvement . '%';
                                                                    }
                                                                @endphp
                                                                <span class="{{ $textColor }} font-bold text-sm">
                                                                    {{ $improvementText }}
                                                                </span>
                                                            @else
                                                                <span class="text-xs  font-medium">Baseline</span>
                                                            @endif
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div class="flex flex-col">
                                                                <span class="text-xs ">Conversions</span>
                                                                <span
                                                                    class="font-semibold">{{ number_format($variant['conversions']) }}</span>
                                                            </div>
                                                            <div class="flex flex-col text-right">
                                                                <span class="text-xs ">Rate</span>
                                                                <span
                                                                    class="font-semibold">{{ number_format($variant['conversion_rate'] * 100, 2) }}%</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Secondary Conversions --}}
                                                    @if (isset($variant['secondary_conversions']))
                                                        <div
                                                            class="border-t border-gray-200 dark:border-gray-600 pt-3 mt-3">
                                                            <div class="flex justify-between items-center mb-2">
                                                                <span class="text-xs ">Secondary
                                                                    Goal</span>
                                                                @if ($variantName !== 'control')
                                                                    @php
                                                                        $secImprovement =
                                                                            $variant['secondary_improvement'];
                                                                        if (is_infinite($secImprovement)) {
                                                                            $secImprovementText = '∞%';
                                                                            $secTextColor =
                                                                                'text-green-600 dark:text-green-400';
                                                                        } elseif ($secImprovement === null) {
                                                                            $secImprovementText = 'N/A';
                                                                            $secTextColor =
                                                                                'text-gray-500 dark:text-gray-400';
                                                                        } else {
                                                                            $secImprovement = round($secImprovement, 1);
                                                                            $secTextColor =
                                                                                $secImprovement >= 0
                                                                                    ? 'text-green-600 dark:text-green-400'
                                                                                    : 'text-red-600 dark:text-red-400';
                                                                            $secSign = $secImprovement >= 0 ? '+' : '';
                                                                            $secImprovementText =
                                                                                $secSign . $secImprovement . '%';
                                                                        }
                                                                    @endphp
                                                                    <span
                                                                        class="{{ $secTextColor }} font-bold text-sm">
                                                                        {{ $secImprovementText }}
                                                                    </span>
                                                                @else
                                                                    <span class="text-xs  font-medium">Baseline</span>
                                                                @endif
                                                            </div>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                <div class="flex flex-col">
                                                                    <span class="text-xs ">Conversions</span>
                                                                    <span
                                                                        class="font-semibold">{{ number_format($variant['secondary_conversions']) }}</span>
                                                                </div>
                                                                <div class="flex flex-col text-right">
                                                                    <span class="text-xs ">Rate</span>
                                                                    <span
                                                                        class="font-semibold">{{ number_format($variant['secondary_conversion_rate'] * 100, 2) }}%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div> {{-- End Inner Card --}}
                                        @endforeach
                                    </div>
                                </div>
                            @endif {{-- End error check --}}
                        </div> {{-- End Outer Card --}}
                    @empty
                        {{-- Empty State Card Replacement --}}
                        <div class="bg-white dark:bg-gray-800 -md sm:rounded-2xl overflow-hidden p-6">
                            <p class="text-center text-gray-500 dark:text-gray-400 py-8">No A/B test data found.</p>
                        </div>
                    @endforelse
                </div>
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
</body>

</html>
