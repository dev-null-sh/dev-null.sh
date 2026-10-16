<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<CommitImage>
 */
final class CommitImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'path' => 'commit-images/'.fake()->uuid().'.webp',
            'width' => 1200,
            'height' => 800,
            'position' => 0,
        ];
    }

    /**
     * Writes a gradient WebP of the image's size to the disk, so the image actually renders.
     */
    public function stored(): self
    {
        return $this->afterCreating(function (CommitImage $image): void {
            $width = max(1, $image->width);
            $height = max(1, $image->height);

            $canvas = imagecreatetruecolor($width, $height);
            assert($canvas !== false);

            $from = [random_int(0, 255), random_int(0, 255), random_int(0, 255)];
            $to = [random_int(0, 255), random_int(0, 255), random_int(0, 255)];

            for ($y = 0; $y < $height; $y++) {
                $ratio = $y / max(1, $height - 1);

                $color = imagecolorallocate(
                    $canvas,
                    $this->blend($from[0], $to[0], $ratio),
                    $this->blend($from[1], $to[1], $ratio),
                    $this->blend($from[2], $to[2], $ratio),
                );
                assert($color !== false);

                imageline($canvas, 0, $y, $width - 1, $y, $color);
            }

            ob_start();
            imagewebp($canvas, null, 80);

            Storage::disk()->put($image->path, (string) ob_get_clean());
        });
    }

    /**
     * @return int<0, 255>
     */
    private function blend(int $from, int $to, float $ratio): int
    {
        return min(255, max(0, (int) round($from + ($to - $from) * $ratio)));
    }
}
