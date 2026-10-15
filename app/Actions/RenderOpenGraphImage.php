<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class RenderOpenGraphImage
{
    private const int WIDTH = 1200;

    private const int HEIGHT = 630;

    /**
     * Everything is drawn at twice the size and scaled down, since GD only anti-aliases text.
     */
    private const int SCALE = 2;

    private const int SHELL = 0xF6F7F8;

    private const int INK = 0x121417;

    private const int INK_MUTED = 0x5B616B;

    private const int HAIRLINE = 0xE1E4E8;

    private const int RAIL = 0xD3D7DD;

    private const int WHITE = 0xFFFFFF;

    private const array LANES = [0xD9480F, 0x2F9E44, 0x9C36B5, 0x0C8599, 0xC2255C, 0xB7791F];

    private const int RAIL_X = 112;

    private const int CONTENT_X = 184;

    private const int CONTENT_RIGHT = 1104;

    private const int AVATAR_SIZE = 152;

    private const int FOOTER_RULE_Y = 456;

    private const int FOOTER_BASELINE = 524;

    private const int BIO_SIZE = 34;

    private const int BIO_LINE_HEIGHT = 50;

    private const int BIO_MAX_LINES = 3;

    public function handle(User $user): string
    {
        $user->loadPublicCounts();

        return Cache::remember(
            "open-graph-image:{$user->id}:{$user->openGraphImageVersion()}",
            now()->addHour(),
            fn (): string => $this->render($user),
        );
    }

    private function render(User $user): string
    {
        $image = $this->canvas(self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE, self::SHELL);
        $lane = self::LANES[$user->lane()];

        $name = $this->clean($user->name);

        if ($name === '') {
            $name = $user->username;
        }

        $bio = $this->wrap($this->clean((string) $user->bio), $this->font('Geist-Regular'), self::BIO_SIZE, self::CONTENT_RIGHT - self::CONTENT_X);

        $blockHeight = self::AVATAR_SIZE + ($bio === [] ? 0 : 48 + count($bio) * self::BIO_LINE_HEIGHT);
        $top = max(56, intdiv(self::FOOTER_RULE_Y - $blockHeight, 2));
        $avatarCenter = $top + intdiv(self::AVATAR_SIZE, 2);

        $this->rect($image, self::RAIL_X - 2, 0, self::RAIL_X + 2, $avatarCenter, self::RAIL);
        $this->rect($image, self::RAIL_X - 2, $avatarCenter, self::RAIL_X + 2, self::HEIGHT, $lane);
        $this->node($image, $avatarCenter, $lane);

        $this->avatar($image, $user, $name, self::CONTENT_X + 10, $top, $lane);

        $textX = self::CONTENT_X + 10 + self::AVATAR_SIZE + 10 + 36;
        $textWidth = self::CONTENT_RIGHT - $textX;

        $this->text($image, $this->truncate($name, $this->font('Geist-SemiBold'), 60, $textWidth), $this->font('Geist-SemiBold'), 60, $textX, $top + 74, self::INK);

        $this->circle($image, $textX + 7, $top + 113, 7, $lane);
        $this->text($image, $this->truncate('@'.$user->username, $this->font('GeistMono-Regular'), 32, $textWidth - 28), $this->font('GeistMono-Regular'), 32, $textX + 28, $top + 124, self::INK_MUTED);

        $bioTop = $top + self::AVATAR_SIZE + 48;

        if ($bio !== []) {
            $this->node($image, $bioTop + intdiv(self::BIO_LINE_HEIGHT, 2) - 2, $lane);
        }

        foreach ($bio as $index => $line) {
            $this->text($image, $line, $this->font('Geist-Regular'), self::BIO_SIZE, self::CONTENT_X, $bioTop + 36 + $index * self::BIO_LINE_HEIGHT, self::INK);
        }

        $this->rect($image, self::CONTENT_X, self::FOOTER_RULE_Y, self::CONTENT_RIGHT, self::FOOTER_RULE_Y + 2, self::HAIRLINE);
        $this->counts($image, $user);
        $this->wordmark($image);

        return $this->png($image);
    }

    private function counts(GdImage $image, User $user): void
    {
        $x = self::CONTENT_X;

        foreach ([
            Str::plural('commit', $user->commits_count) => $user->commits_count,
            'following' => $user->following_count,
            Str::plural('follower', $user->followers_count) => $user->followers_count,
        ] as $label => $count) {
            $number = number_format($count);

            $this->text($image, $number, $this->font('GeistMono-Medium'), 30, $x, self::FOOTER_BASELINE, self::INK);
            $x += $this->width($number, $this->font('GeistMono-Medium'), 30) + 10;

            $this->text($image, $label, $this->font('Geist-Regular'), 30, $x, self::FOOTER_BASELINE, self::INK_MUTED);
            $x += $this->width($label, $this->font('Geist-Regular'), 30) + 36;
        }
    }

    private function wordmark(GdImage $image): void
    {
        $font = $this->font('Geist-SemiBold');
        $label = '/dev/null';
        $x = self::CONTENT_RIGHT - $this->width($label, $font, 32);

        $this->text($image, $label, $font, 32, $x, self::FOOTER_BASELINE, self::INK);

        $size = 32;
        $left = $x - 8 - $size;
        $center = self::FOOTER_BASELINE - 11;
        $unit = $size / 24;

        $this->circle($image, $left + 12 * $unit, $center, 10 * $unit, self::INK);
        $this->circle($image, $left + 12 * $unit, $center, 7.5 * $unit, self::SHELL);

        $points = [];

        foreach ([[-8, -1.3], [8, -1.3], [8, 1.3], [-8, 1.3]] as [$along, $across]) {
            $points[] = (int) round(($left + 12 * $unit + ($along + $across) * $unit * M_SQRT1_2) * self::SCALE);
            $points[] = (int) round(($center + (-$along + $across) * $unit * M_SQRT1_2) * self::SCALE);
        }

        imagefilledpolygon($image, $points, self::INK);
    }

    private function avatar(GdImage $image, User $user, string $name, int $x, int $y, int $lane): void
    {
        $radius = self::AVATAR_SIZE / 2;
        $centerX = $x + $radius;
        $centerY = $y + $radius;

        $this->circle($image, $centerX, $centerY, $radius + 10, $lane);
        $this->circle($image, $centerX, $centerY, $radius + 6, self::SHELL);

        $size = self::AVATAR_SIZE * self::SCALE;
        $source = $this->download($user->avatar_url);

        if (! $source instanceof GdImage) {
            $this->circle($image, $centerX, $centerY, $radius, $lane);

            $initial = mb_strtoupper(mb_substr($name, 0, 1));
            $font = $this->font('Geist-SemiBold');
            $box = $this->box($initial, $font, 72);

            $this->text($image, $initial, $font, 72, (int) round($centerX - ($box['left'] + $box['right']) / 2), (int) round($centerY - ($box['top'] + $box['bottom']) / 2), self::WHITE);

            return;
        }

        $avatar = $this->canvas($size, $size, self::SHELL);
        imagecopyresampled($avatar, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

        $center = ($size - 1) / 2;

        for ($row = 0; $row < $size; $row++) {
            for ($column = 0; $column < $size; $column++) {
                $color = imagecolorat($avatar, $column, $row);

                if ($color !== false && ($column - $center) ** 2 + ($row - $center) ** 2 <= ($size / 2) ** 2) {
                    imagesetpixel($image, $x * self::SCALE + $column, $y * self::SCALE + $row, $color);
                }
            }
        }
    }

    private function download(?string $url): ?GdImage
    {
        if ($url === null) {
            return null;
        }

        try {
            $response = Http::timeout(3)->connectTimeout(2)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful() || @getimagesizefromstring($response->body()) === false) {
            return null;
        }

        $avatar = @imagecreatefromstring($response->body());

        return $avatar instanceof GdImage ? $avatar : null;
    }

    private function node(GdImage $image, int $y, int $lane): void
    {
        $this->circle($image, self::RAIL_X, $y, 16, $lane);
        $this->circle($image, self::RAIL_X, $y, 12, self::SHELL);
        $this->circle($image, self::RAIL_X, $y, 8, $lane);
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $width): array
    {
        if ($text === '') {
            return [];
        }

        $lines = [''];

        foreach (explode(' ', $text) as $word) {
            $current = array_key_last($lines);
            $candidate = mb_ltrim($lines[$current].' '.$word);

            if ($this->width($candidate, $font, $size) <= $width) {
                $lines[$current] = $candidate;

                continue;
            }

            if ($this->width($word, $font, $size) <= $width) {
                $lines[] = $word;

                continue;
            }

            if ($lines[$current] !== '') {
                $lines[$current] .= ' ';
            }

            foreach (mb_str_split($word) as $character) {
                $current = array_key_last($lines);

                if ($this->width($lines[$current].$character, $font, $size) > $width) {
                    $lines[] = '';
                    $current++;
                }

                $lines[$current] .= $character;
            }
        }

        $lines = array_values(array_filter($lines, fn (string $line): bool => $line !== ''));

        if (count($lines) <= self::BIO_MAX_LINES) {
            return $lines;
        }

        return [
            ...array_slice($lines, 0, self::BIO_MAX_LINES - 1),
            $this->truncate($lines[self::BIO_MAX_LINES - 1], $font, $size, $width, force: true),
        ];
    }

    private function truncate(string $text, string $font, int $size, int $width, bool $force = false): string
    {
        if (! $force && $this->width($text, $font, $size) <= $width) {
            return $text;
        }

        while ($text !== '' && $this->width($this->ellipsis($text), $font, $size) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return $this->ellipsis($text);
    }

    private function ellipsis(string $text): string
    {
        return mb_rtrim($text, ' ,;:.-').'…';
    }

    /**
     * Geist has no emoji, so they are dropped rather than rendered as empty boxes.
     */
    private function clean(string $text): string
    {
        $text = (string) preg_replace('/[\p{Extended_Pictographic}\x{FE0F}\x{200D}\x{20E3}]/u', '', $text);

        return mb_trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function text(GdImage $image, string $text, string $font, int $size, int $x, int $y, int $color): void
    {
        imagettftext($image, $this->points($size), 0, $x * self::SCALE, $y * self::SCALE, $color, $font, $text);
    }

    private function width(string $text, string $font, int $size): int
    {
        $box = $this->box($text, $font, $size);

        return $box['right'] - $box['left'];
    }

    /**
     * @return array{left: int, right: int, top: int, bottom: int}
     */
    private function box(string $text, string $font, int $size): array
    {
        $box = imagettfbbox($this->points($size), 0, $font, $text);

        throw_if($box === false, RuntimeException::class, "Unable to measure text with [{$font}].");

        return [
            'left' => intdiv(Arr::integer($box, 0), self::SCALE),
            'right' => intdiv(Arr::integer($box, 2), self::SCALE),
            'top' => intdiv(Arr::integer($box, 5), self::SCALE),
            'bottom' => intdiv(Arr::integer($box, 1), self::SCALE),
        ];
    }

    private function points(int $pixels): float
    {
        return $pixels * self::SCALE * 0.75;
    }

    private function font(string $name): string
    {
        return resource_path("fonts/{$name}.ttf");
    }

    private function circle(GdImage $image, float $x, float $y, float $radius, int $color): void
    {
        $diameter = (int) round($radius * 2 * self::SCALE);

        imagefilledellipse($image, (int) round($x * self::SCALE), (int) round($y * self::SCALE), $diameter, $diameter, $color);
    }

    private function rect(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $color): void
    {
        imagefilledrectangle($image, $x1 * self::SCALE, $y1 * self::SCALE, $x2 * self::SCALE - 1, $y2 * self::SCALE - 1, $color);
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private function canvas(int $width, int $height, int $background): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        assert($image instanceof GdImage);

        imagefill($image, 0, 0, $background);

        return $image;
    }

    private function png(GdImage $image): string
    {
        $output = $this->canvas(self::WIDTH, self::HEIGHT, self::SHELL);

        imagecopyresampled($output, $image, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, imagesx($image), imagesy($image));

        ob_start();
        imagepng($output);

        return (string) ob_get_clean();
    }
}
