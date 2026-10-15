<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LinkPreview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkPreview>
 */
final class LinkPreviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => fake()->unique()->url(),
            'url_hash' => fn (array $attributes): string => hash('sha256', is_string($attributes['url']) ? $attributes['url'] : ''),
            'title' => null,
            'description' => null,
            'image_url' => null,
            'site_name' => null,
            'fetched_at' => null,
        ];
    }

    public function fetched(): self
    {
        return $this->state(fn (array $attributes): array => [
            'title' => fake()->sentence(),
            'description' => fake()->sentence(16),
            'image_url' => 'https://'.fake()->domainName().'/og-image.png',
            'site_name' => fake()->company(),
            'fetched_at' => now(),
        ]);
    }

    public function withoutTitle(): self
    {
        return $this->fetched()->state(fn (array $attributes): array => [
            'title' => null,
        ]);
    }
}
