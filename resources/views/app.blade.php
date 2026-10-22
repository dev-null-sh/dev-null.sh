<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="{{ $preview['description'] ?? 'A social network for developers. Sign in with GitHub. Commit messages to the log. Talk with other devs.' }}">
        @if (($appearance ?? 'system') === 'system')
            <meta name="theme-color" content="#f6f7f8" media="(prefers-color-scheme: light)">
            <meta name="theme-color" content="#111112" media="(prefers-color-scheme: dark)">
        @else
            <meta name="theme-color" content="{{ $appearance === 'dark' ? '#111112' : '#f6f7f8' }}">
        @endif

        @isset($preview)
            <meta name="robots" content="{{ $preview['robots'] }}">
            <link rel="canonical" href="{{ $preview['url'] }}">
            <meta property="og:type" content="{{ $preview['type'] }}">
            <meta property="og:site_name" content="/dev/null">
            <meta property="og:title" content="{{ $preview['title'] }}">
            <meta property="og:description" content="{{ $preview['description'] }}">
            <meta property="og:url" content="{{ $preview['url'] }}">
            @foreach ($preview['images'] as $image)
                <meta property="og:image" content="{{ $image['url'] }}">
                <meta property="og:image:type" content="{{ $image['type'] }}">
                <meta property="og:image:width" content="{{ $image['width'] }}">
                <meta property="og:image:height" content="{{ $image['height'] }}">
                <meta property="og:image:alt" content="{{ $image['alt'] }}">
            @endforeach
            @isset($preview['publishedAt'])
                <meta property="article:published_time" content="{{ $preview['publishedAt'] }}">
                <meta property="article:author" content="{{ $preview['author'] }}">
            @endisset
            @isset($preview['username'])
                <meta property="profile:username" content="{{ $preview['username'] }}">
            @endisset
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $preview['title'] }}">
            <meta name="twitter:description" content="{{ $preview['description'] }}">
            <meta name="twitter:image" content="{{ $preview['images'][0]['url'] }}">
            <meta name="twitter:image:alt" content="{{ $preview['images'][0]['alt'] }}">
            @isset($preview['feed'])
                <link rel="alternate" type="application/atom+xml" title="{{ $preview['feed']['title'] }}" href="{{ $preview['feed']['url'] }}">
            @endisset
            @isset($preview['structuredData'])
                <script type="application/ld+json">@json($preview['structuredData'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
            @endisset
        @else
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="/dev/null">
            <meta property="og:title" content="/dev/null">
            <meta property="og:description" content="A social network for developers. Sign in with GitHub. Commit messages to the log. Talk with other devs.">
            <meta property="og:image" content="{{ url('/og.png') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta name="twitter:card" content="summary_large_image">
        @endisset

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
                color-scheme: light;
            }

            html.dark {
                background-color: #111112;
                color-scheme: dark;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        {{-- Installed as an app: no browser chrome, and a status bar in the page's colors --}}
        <meta name="application-name" content="/dev/null">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="/dev/null">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        @fonts

        @production
            <!-- Fathom - beautiful, simple website analytics -->
            <script src="https://cdn.usefathom.com/script.js" data-site="{{ config('services.fathom.site_id') }}" defer></script>
            <!-- / Fathom -->
        @endproduction

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $preview['title'] ?? config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div class="pwa-status-bar" aria-hidden="true"></div>
        <x-inertia::app />
    </body>
</html>
