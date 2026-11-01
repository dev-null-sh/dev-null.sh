<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityGraphTheme;
use App\Models\User;

/**
 * Generates a compact SVG follow badge for a user's GitHub profile README.
 *
 * @phpstan-type Palette array{card: string, border: string, ink: string, muted: string, empty: string, lanes: list<string>}
 */
final readonly class CreateUserBadge
{
    private const int HEIGHT = 20;

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

    public function handle(
        User $user,
        ActivityGraphTheme $theme = ActivityGraphTheme::Light,
    ): string {
        $message = $this->message($user);
        $messageWidth = $this->textWidth($message);
        $dotX = 77;
        $messageX = 86;
        $width = $messageX + $messageWidth + 10;

        return view('svg.user-badge', [
            'width' => $width,
            'height' => self::HEIGHT,
            'dotX' => $dotX,
            'messageX' => $messageX,
            'message' => $message,
            'styles' => $this->styles($theme, $user->lane()),
        ])->render();
    }

    public function message(User $user): string
    {
        return "follow @{$user->handle}";
    }

    public function textWidth(string $text): int
    {
        return (int) ceil(mb_strlen($text) * 6.6);
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

        return implode('', array_map(
            fn (string $selector, string $declarations): string => "{$selector}{{$declarations}}",
            array_keys($rules),
            $rules,
        ));
    }
}
