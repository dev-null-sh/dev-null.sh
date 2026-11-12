<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GifProvider;
use App\Models\Commit;
use App\Models\CommitGif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitGif>
 */
final class CommitGifFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'provider' => GifProvider::Giphy->value,
            'provider_gif_id' => 'xT9IgzoKnwFNmISR8I',
            'url' => 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy-downsized-medium.gif?cid=abc123',
            'page_url' => 'https://giphy.com/gifs/deal-with-it-xT9IgzoKnwFNmISR8I',
            'title' => 'Deal With It GIF',
            'username' => 'giphy',
            'width' => 480,
            'height' => 270,
        ];
    }
}
