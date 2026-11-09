<!DOCTYPE html>
{{-- A chosen theme is set here, so it holds without JavaScript; only Auto asks the browser. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($page['props']['options']['theme'] ?? 'auto') === 'dark']) data-embed-theme="{{ $page['props']['options']['theme'] ?? 'auto' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        {{-- Links open on /dev/null or their own site, never inside the frame, with or without JavaScript. --}}
        <base target="_blank">
        <title>/dev/null</title>
        <script>
            (function () {
                const theme = document.documentElement.dataset.embedTheme;
                const dark = theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>
        <style>
            /* The page around the card's rounded corners is the framing site's: the app's own background stays out. */
            html, body { background: transparent !important; margin: 0; }
        </style>
        @viteReactRefresh
        @vite(['resources/js/embed.tsx'])
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
