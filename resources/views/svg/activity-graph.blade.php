<svg xmlns="http://www.w3.org/2000/svg" width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-labelledby="title">
<title id="title">{{ $mention }} on /dev/null: {{ $total }} {{ $commits }} in the last year</title>
<style>text{font-family:Geist,-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif}.mono{font-family:Geist Mono,ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}{{ $styles }}</style>
<rect class="card" x="0.5" y="0.5" width="{{ $width - 1 }}" height="{{ $height - 1 }}" rx="6"/>
<circle class="lane" cx="{{ $padding + 4 }}" cy="27.5" r="4"/>
<text class="ink mono" x="{{ $padding + 14 }}" y="32" font-size="13">{{ $mention }}</text>
<path class="ink" transform="translate({{ $width - $padding - 80 }} 20.5) scale(0.5833)" fill-rule="evenodd" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2.5c1.6 0 3.08.5 4.3 1.37L5.87 16.3A7.5 7.5 0 0 1 12 4.5Zm0 15a7.47 7.47 0 0 1-4.3-1.37L18.13 7.7A7.5 7.5 0 0 1 12 19.5Z"/>
<text class="ink" x="{{ $width - $padding }}" y="32" font-size="13" font-weight="600" text-anchor="end" textLength="60" lengthAdjust="spacingAndGlyphs">/dev/null</text>
<g class="muted" font-size="10">
@foreach ($months as $month)
<text x="{{ $month['x'] }}" y="58">{{ $month['label'] }}</text>
@endforeach
@foreach ($weekdays as $weekday)
<text x="{{ $padding }}" y="{{ $weekday['y'] }}">{{ $weekday['label'] }}</text>
@endforeach
</g>
<g class="cells">
@foreach ($cells as $cell)
<rect class="l{{ $cell['level'] }}" x="{{ $cell['x'] }}" y="{{ $cell['y'] }}" width="{{ $cellSize }}" height="{{ $cellSize }}" rx="2"/>
@endforeach
</g>
<text class="muted mono" x="{{ $padding }}" y="180" font-size="11">
<tspan class="ink">{{ $total }}</tspan> {{ $commits }} in the last year
@if ($longestStreak > 0)
· longest streak <tspan class="ink">{{ $longestStreak }}</tspan> {{ $days }}
@if ($currentStreak > 0)
· <tspan class="lane">●</tspan> current <tspan class="ink">{{ $currentStreak }}</tspan>
@endif
@endif
</text>
<g class="muted" font-size="10">
<text x="{{ $legend[0]['x'] - 4 }}" y="180" text-anchor="end">Less</text>
<text x="{{ $legend[4]['x'] + $cellSize + 4 }}" y="180">More</text>
</g>
@foreach ($legend as $item)
<rect class="l{{ $item['level'] }}" x="{{ $item['x'] }}" y="171" width="{{ $cellSize }}" height="{{ $cellSize }}" rx="2"/>
@endforeach
</svg>
