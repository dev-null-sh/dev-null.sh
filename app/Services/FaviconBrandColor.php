<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Picks a saturated primary colour from a domain's favicon for verified branch chrome.
 */
final readonly class FaviconBrandColor
{
    /**
     * @return non-empty-string|null #rrggbb
     */
    public function forDomain(string $domain): ?string
    {
        $bytes = $this->faviconBytes($domain);

        return $bytes === null ? null : $this->fromImage($bytes);
    }

    private function faviconBytes(string $domain): ?string
    {
        $urls = [
            'https://www.google.com/s2/favicons?domain='.urlencode($domain).'&sz=64',
            'https://icons.duckduckgo.com/ip3/'.$domain.'.ico',
            'https://'.$domain.'/favicon.ico',
            'https://'.$domain.'/favicon.png',
        ];

        foreach ($urls as $url) {
            try {
                $response = Http::withUserAgent(sprintf(
                    'Mozilla/5.0 (compatible; devnull-branch/1.0; +%s)',
                    config()->string('app.url'),
                ))
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get($url);
            } catch (ConnectionException) {
                continue;
            }

            if (! $response->successful() || $response->body() === '') {
                continue;
            }

            if (@getimagesizefromstring($response->body()) === false) {
                continue;
            }

            return $response->body();
        }

        return null;
    }

    /**
     * @return non-empty-string|null
     */
    private function fromImage(string $bytes): ?string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 1 || $height < 1) {
            return null;
        }

        /** @var array<string, array{r: int, g: int, b: int, count: int, score: float}> $buckets */
        $buckets = [];
        $stepX = max(1, intdiv($width, 16));
        $stepY = max(1, intdiv($height, 16));

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                [$r, $g, $b, $alpha] = $this->pixel($image, $x, $y);

                if ($alpha > 64) {
                    continue;
                }

                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $delta = $max - $min;
                $lightness = ($max + $min) / 2 / 255;
                $saturation = $max === 0 ? 0.0 : $delta / $max;

                // Skip near-white, near-black, and grey pixels.
                if ($lightness > 0.92 || $lightness < 0.08 || $saturation < 0.18) {
                    continue;
                }

                $key = sprintf(
                    '%02x%02x%02x',
                    intdiv($r, 24) * 24,
                    intdiv($g, 24) * 24,
                    intdiv($b, 24) * 24,
                );

                $buckets[$key] ??= ['r' => 0, 'g' => 0, 'b' => 0, 'count' => 0, 'score' => 0.0];
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
                $buckets[$key]['count']++;
                $buckets[$key]['score'] += 1 + ($saturation * 2);
            }
        }

        if ($buckets === []) {
            // Near-black / grey marks (e.g. Vercel): fall back to the most common opaque pixel.
            return $this->fallbackColor($image, $width, $height, $stepX, $stepY);
        }

        uasort($buckets, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $winner = array_values($buckets)[0];

        return sprintf(
            '#%02x%02x%02x',
            (int) round($winner['r'] / $winner['count']),
            (int) round($winner['g'] / $winner['count']),
            (int) round($winner['b'] / $winner['count']),
        );
    }

    /**
     * @return non-empty-string|null
     */
    private function fallbackColor(GdImage $image, int $width, int $height, int $stepX, int $stepY): ?string
    {
        /** @var array<string, array{r: int, g: int, b: int, count: int}> $buckets */
        $buckets = [];

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                [$r, $g, $b, $alpha] = $this->pixel($image, $x, $y);

                if ($alpha > 64) {
                    continue;
                }

                $key = sprintf('%02x%02x%02x', $r, $g, $b);
                $buckets[$key] ??= ['r' => $r, 'g' => $g, 'b' => $b, 'count' => 0];
                $buckets[$key]['count']++;
            }
        }

        if ($buckets === []) {
            return null;
        }

        uasort($buckets, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $winner = array_values($buckets)[0];

        return sprintf('#%02x%02x%02x', $winner['r'], $winner['g'], $winner['b']);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function pixel(GdImage $image, int $x, int $y): array
    {
        $value = imagecolorat($image, $x, $y);

        if (imageistruecolor($image)) {
            return [
                ($value >> 16) & 0xFF,
                ($value >> 8) & 0xFF,
                $value & 0xFF,
                ($value & 0x7F000000) >> 24,
            ];
        }

        $color = imagecolorsforindex($image, $value);

        return [
            $color['red'],
            $color['green'],
            $color['blue'],
            $color['alpha'],
        ];
    }
}
