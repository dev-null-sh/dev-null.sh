<?php

declare(strict_types=1);

namespace App\Omarchy;

use Illuminate\Support\Str;

final class ResolveOmarchyTheme
{
    private ?OmarchyTheme $theme = null;

    private bool $resolved = false;

    public function handle(): ?OmarchyTheme
    {
        if ($this->resolved) {
            return $this->theme;
        }

        $this->resolved = true;

        return $this->theme = $this->resolve();
    }

    private function resolve(): ?OmarchyTheme
    {
        if (! config()->boolean('omarchy.enabled')) {
            return null;
        }

        $path = $this->locate($this->setting('omarchy.path'));

        if ($path === null) {
            return null;
        }

        $colors = $this->parse((string) file_get_contents($path));

        if (! isset($colors['background'], $colors['foreground'])) {
            return null;
        }

        $mode = ($colors['mode'] ?? 'dark') === 'light' ? 'light' : 'dark';

        return new OmarchyTheme(
            name: $this->name($path),
            mode: $mode,
            tokens: $this->tokens($colors, $mode),
        );
    }

    private function name(string $path): string
    {
        $file = dirname($path).'/theme.name';

        $slug = is_file($file) ? mb_trim((string) file_get_contents($file)) : '';

        return Str::headline($slug !== '' ? $slug : 'Omarchy');
    }

    private function locate(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (! str_contains($path, '*')) {
            return is_file($path) ? $path : null;
        }

        $matches = glob($path);

        if ($matches === false || $matches === []) {
            return null;
        }

        return $matches[0];
    }

    private function setting(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $contents): array
    {
        $colors = [];

        foreach (explode("\n", $contents) as $line) {
            $line = mb_trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $position = mb_strpos($line, '=');

            if ($position === false) {
                continue;
            }

            $key = mb_trim(mb_substr($line, 0, $position));
            $value = mb_trim(mb_substr($line, $position + 1), " \t\"'");

            if ($key === '' || $value === '') {
                continue;
            }

            $colors[$key] = $value;
        }

        return $colors;
    }

    /**
     * @param  array<string, string>  $colors
     * @return array<string, string>
     */
    private function tokens(array $colors, string $mode): array
    {
        $background = $colors['background'];
        $foreground = $colors['foreground'];
        $primary = $this->color($colors, 'accent', $foreground);
        $card = $this->color($colors, 'lighter_background', $background);

        // A theme's selection colour usually sits a shade away from its surfaces, which reads as no
        // border at all. Mixing the foreground into the surface keeps an edge visible on any palette.
        $edge = fn (int $percentage): string => "color-mix(in oklab, {$foreground} {$percentage}%, {$card})";

        // The theme's muted colour is a dim UI grey that is unreadable as text (1.6:1 on some
        // palettes), so secondary text is mixed from the foreground instead, at the ratio the app
        // itself uses for the same job.
        $secondary = "color-mix(in oklab, {$foreground} 70%, {$background})";

        return [
            '--background' => $background,
            '--foreground' => $foreground,
            '--card' => $card,
            '--card-foreground' => $foreground,
            '--popover' => $this->color($colors, 'lighter_background', $background),
            '--popover-foreground' => $this->color($colors, 'bright_foreground', $foreground),
            '--primary' => $primary,
            '--primary-foreground' => $background,
            '--primary-hover' => $mode === 'dark'
                ? 'color-mix(in oklab, var(--primary) 82%, #ffffff)'
                : 'color-mix(in oklab, var(--primary) 88%, #000000)',
            '--primary-soft' => $this->color($colors, 'selection', $background),
            '--secondary' => $this->color($colors, 'lighter_background', $background),
            '--secondary-foreground' => $foreground,
            '--muted' => $this->color($colors, 'lighter_background', $background),
            '--muted-foreground' => $secondary,
            '--accent' => $this->color($colors, 'lighter_background', $background),
            '--accent-foreground' => $foreground,
            '--destructive' => $this->color($colors, 'red', $foreground),
            '--destructive-foreground' => $this->color($colors, 'bright_red', $this->color($colors, 'red', $foreground)),
            '--border' => $this->color($colors, 'hyprland_inactive_border', $edge(22)),
            '--input' => $edge(32),
            '--ring' => $primary,
            '--rail' => $edge(28),
            '--lane-0' => $this->color($colors, 'bright_red', $this->color($colors, 'red', $foreground)),
            '--lane-1' => $this->color($colors, 'bright_green', $this->color($colors, 'green', $foreground)),
            '--lane-2' => $this->color($colors, 'bright_magenta', $this->color($colors, 'magenta', $foreground)),
            '--lane-3' => $this->color($colors, 'bright_cyan', $this->color($colors, 'cyan', $foreground)),
            '--lane-4' => $this->color($colors, 'bright_yellow', $this->color($colors, 'yellow', $foreground)),
            '--lane-5' => $this->color($colors, 'bright_blue', $this->color($colors, 'blue', $foreground)),
        ];
    }

    /**
     * @param  array<string, string>  $colors
     */
    private function color(array $colors, string $key, string $default): string
    {
        $value = $colors[$key] ?? '';

        return $value !== '' ? $value : $default;
    }
}
