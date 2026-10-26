<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityGraphTheme;
use App\Enums\ActivityScale;
use App\Models\User;
use App\Queries\ActivityBucketsQuery;
use App\Queries\UserActivityQuery;
use App\Queries\UserStreakQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Draws the year grid of the profile's activity graph as a standalone SVG, from what guests may see.
 *
 * @phpstan-import-type ActivityBucket from ActivityBucketsQuery
 *
 * @phpstan-type Palette array{card: string, border: string, ink: string, muted: string, empty: string, lanes: list<string>}
 */
final readonly class CreateUserActivityGraph
{
    private const int CELL = 10;

    private const int STEP = 13;

    private const int PADDING = 20;

    private const int GRID_X = 48;

    private const int GRID_Y = 66;

    private const int HEIGHT = 196;

    private const int LEGEND_LABEL = 28;

    private const array LEVEL_MIX = [1 => 0.32, 2 => 0.56, 3 => 0.8, 4 => 1.0];

    private const array MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private const array WEEKDAYS = [1 => 'Mon', 3 => 'Wed', 5 => 'Fri'];

    /**
     * The colors of `resources/css/app.css`.
     *
     * @var Palette
     */
    private const array LIGHT = [
        'card' => '#ffffff',
        'border' => '#e1e4e8',
        'ink' => '#121417',
        'muted' => '#5b616b',
        'empty' => '#eef0f2',
        'lanes' => ['#d9480f', '#2f9e44', '#9c36b5', '#0c8599', '#c2255c', '#b7791f'],
    ];

    /**
     * @var Palette
     */
    private const array DARK = [
        'card' => '#18181a',
        'border' => '#2a2a2d',
        'ink' => '#ececec',
        'muted' => '#a1a1a6',
        'empty' => '#232326',
        'lanes' => ['#ff8a4c', '#51cf66', '#da77f2', '#3bc9db', '#f06595', '#fcc419'],
    ];

    public function __construct(private UserActivityQuery $activity, private UserStreakQuery $streaks)
    {
        //
    }

    public function handle(User $user, ActivityGraphTheme $theme): string
    {
        ['buckets' => $buckets] = $this->activity->get($user, null, ActivityScale::Year);
        ['longest' => $longestStreak, 'current' => $currentStreak] = $this->streaks->get($user, null);

        $counts = array_column($buckets, 'count');
        $total = array_sum($counts);
        $max = max(1, ...$counts);
        $columns = (int) ceil(count($buckets) / 7);
        $width = self::GRID_X + $columns * self::STEP - (self::STEP - self::CELL) + self::PADDING;

        return view('svg.activity-graph', [
            'mention' => $user->mention(),
            'width' => $width,
            'height' => self::HEIGHT,
            'padding' => self::PADDING,
            'cellSize' => self::CELL,
            'styles' => $this->styles($theme, $user->lane()),
            'total' => number_format($total),
            'commits' => Str::plural('commit', $total),
            'longestStreak' => $longestStreak,
            'currentStreak' => $currentStreak,
            'days' => Str::plural('day', $longestStreak),
            'months' => $this->months($buckets),
            'weekdays' => array_map(
                fn (string $label, int $row): array => ['label' => $label, 'y' => self::GRID_Y + $row * self::STEP + self::CELL / 2 + 3.5],
                self::WEEKDAYS,
                array_keys(self::WEEKDAYS),
            ),
            'cells' => array_map(
                fn (array $bucket, int $index): array => [
                    'x' => self::GRID_X + intdiv($index, 7) * self::STEP,
                    'y' => self::GRID_Y + $index % 7 * self::STEP,
                    'level' => $bucket['count'] === 0 ? 0 : max(1, (int) ceil($bucket['count'] / $max * 4)),
                ],
                $buckets,
                array_keys($buckets),
            ),
            'legend' => array_map(
                fn (int $level): array => ['x' => $width - self::PADDING - self::LEGEND_LABEL - self::CELL - (4 - $level) * self::STEP, 'level' => $level],
                range(0, 4),
            ),
        ])->render();
    }

    /**
     * The month of each week that starts one, skipping the first one when the next follows too closely to fit.
     *
     * @param  list<ActivityBucket>  $buckets
     * @return list<array{label: string, x: int}>
     */
    private function months(array $buckets): array
    {
        $months = array_map(
            fn (array $bucket): int => CarbonImmutable::parse($bucket['date'])->month,
            array_values(array_filter($buckets, fn (int $index): bool => $index % 7 === 0, ARRAY_FILTER_USE_KEY)),
        );

        $labels = [];

        foreach ($months as $column => $month) {
            $changesMonth = $column === 0 || $month !== $months[$column - 1];
            $nextLabelIsClose = $column === 0 && array_diff(array_slice($months, 1, 2), [$month]) !== [];

            if ($changesMonth && ! $nextLabelIsClose) {
                $labels[] = ['label' => self::MONTHS[$month - 1], 'x' => self::GRID_X + $column * self::STEP];
            }
        }

        return $labels;
    }

    private function styles(ActivityGraphTheme $theme, int|string $lane): string
    {
        return match ($theme) {
            ActivityGraphTheme::Light => $this->palette(self::LIGHT, $lane),
            ActivityGraphTheme::Dark => $this->palette(self::DARK, $lane),
            ActivityGraphTheme::Auto => $this->palette(self::LIGHT, $lane).'@media (prefers-color-scheme: dark){'.$this->palette(self::DARK, $lane).'}',
        };
    }

    /**
     * @param  Palette  $palette
     */
    private function palette(array $palette, int|string $lane): string
    {
        $color = is_string($lane) ? $lane : $palette['lanes'][$lane];

        $rules = [
            '.card' => "fill:{$palette['card']};stroke:{$palette['border']}",
            '.ink' => "fill:{$palette['ink']}",
            '.muted' => "fill:{$palette['muted']}",
            '.lane' => "fill:{$color}",
            '.l0' => "fill:{$palette['empty']}",
        ];

        foreach (self::LEVEL_MIX as $level => $amount) {
            $rules[".l{$level}"] = 'fill:'.$this->mix($color, $palette['card'], $amount);
        }

        return implode('', array_map(
            fn (string $selector, string $declarations): string => "{$selector}{{$declarations}}",
            array_keys($rules),
            $rules,
        ));
    }

    /**
     * Mixes two colors in Oklab, like `color-mix(in oklab, …)` does on the profile.
     */
    private function mix(string $color, string $base, float $amount): string
    {
        $from = $this->toOklab($base);
        $to = $this->toOklab($color);

        return $this->fromOklab(array_map(
            fn (float $start, float $end): float => $start + ($end - $start) * $amount,
            $from,
            $to,
        ));
    }

    /**
     * @return list<float>
     */
    private function toOklab(string $hex): array
    {
        [$red, $green, $blue] = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            mb_str_split(mb_substr($hex, 1), 2),
        );

        [$long, $medium, $short] = array_map(
            fn (float $cone): float => $cone ** (1 / 3),
            [
                0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue,
                0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue,
                0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue,
            ],
        );

        return [
            0.2104542553 * $long + 0.7936177850 * $medium - 0.0040720468 * $short,
            1.9779984951 * $long - 2.4285922050 * $medium + 0.4505937099 * $short,
            0.0259040371 * $long + 0.7827717662 * $medium - 0.8086757660 * $short,
        ];
    }

    /**
     * @param  list<float>  $oklab
     */
    private function fromOklab(array $oklab): string
    {
        [$lightness, $a, $b] = $oklab;

        [$long, $medium, $short] = array_map(
            fn (float $cone): float => $cone ** 3,
            [
                $lightness + 0.3963377774 * $a + 0.2158037573 * $b,
                $lightness - 0.1055613458 * $a - 0.0638541728 * $b,
                $lightness - 0.0894841775 * $a - 1.2914855480 * $b,
            ],
        );

        $channels = array_map(
            function (float $value): string {
                $value = max(0.0, min(1.0, $value));
                $encoded = $value <= 0.0031308 ? $value * 12.92 : 1.055 * $value ** (1 / 2.4) - 0.055;

                return mb_str_pad(dechex((int) round($encoded * 255)), 2, '0', STR_PAD_LEFT);
            },
            [
                4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short,
                -1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short,
                -0.0041960863 * $long - 0.7034186147 * $medium + 1.7076147010 * $short,
            ],
        );

        return '#'.implode('', $channels);
    }
}
