<svg xmlns="http://www.w3.org/2000/svg" width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="/dev/null: {{ $message }}">
<title>/dev/null: {{ $message }}</title>
<style>text{font-family:Geist,-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif}.mono{font-family:Geist Mono,ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}{{ $styles }}</style>
<rect class="card" x="0.5" y="0.5" width="{{ $width - 1 }}" height="{{ $height - 1 }}" rx="4"/>
<path class="ink" transform="translate(8, 5) scale(0.4167)" fill-rule="evenodd" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2.5c1.6 0 3.08.5 4.3 1.37L5.87 16.3A7.5 7.5 0 0 1 12 4.5Zm0 15a7.47 7.47 0 0 1-4.3-1.37L18.13 7.7A7.5 7.5 0 0 1 12 19.5Z"/>
<text class="ink" x="22" y="14" font-size="11" font-weight="600">/dev/null</text>
<circle class="lane" cx="{{ $dotX }}" cy="10" r="2.5"/>
<text class="ink mono" x="{{ $messageX }}" y="14" font-size="11">{{ $message }}</text>
</svg>
