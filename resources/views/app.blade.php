<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $preview['description'] ?? 'A social network for developers. Sign in with GitHub, commit plain text to a shared log.' }}">
        <meta name="theme-color" content="#f6f7f8" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#111112" media="(prefers-color-scheme: dark)">

        @isset($preview)
            <meta property="og:type" content="{{ $preview['type'] }}">
            <meta property="og:site_name" content="/dev/null">
            <meta property="og:title" content="{{ $preview['title'] }}">
            <meta property="og:description" content="{{ $preview['description'] }}">
            <meta property="og:url" content="{{ $preview['url'] }}">
            <meta property="og:image" content="{{ $preview['image'] }}">
            <meta property="og:image:alt" content="{{ $preview['title'] }}">
        @else
            <meta property="og:type" content="website">
            <meta property="og:title" content="devnull">
            <meta property="og:description" content="A social network for developers. Sign in with GitHub, commit plain text to a shared log.">
            <meta property="og:image" content="{{ url('/og.png') }}">
        @endisset
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f6f7f8;
            }

            html.dark {
                background-color: #111112;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        @fonts

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $preview['title'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
