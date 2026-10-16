<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    Queue::fake();
});

it('stores mixed attachments in their original order with their descriptions', function (string $surface): void {
    $user = User::factory()->create();
    $route = match ($surface) {
        'commit', 'quote' => route('commit.store'),
        'reply' => route('commit-reply.store', Commit::factory()->create()),
        'series' => route('commit-series.store'),
    };
    $payload = match ($surface) {
        'commit', 'reply' => ['body' => 'An animated attachment'],
        'quote' => ['body' => 'An animated attachment', 'quoted_commit_id' => Commit::factory()->create()->id],
        'series' => ['bodies' => ['An animated attachment', 'The second part']],
    };

    $response = $this->actingAs($user)->post($route, [
        ...$payload,
        'images' => [
            UploadedFile::fake()->image('first.png', 40, 30),
            UploadedFile::fake()->createWithContent('animation.webm', mediaFixture('animated.webm')),
            UploadedFile::fake()->image('last.jpg', 20, 10),
        ],
        'image_posters' => [1 => UploadedFile::fake()->createWithContent('poster.png', mediaFixture('poster.png'))],
        'image_descriptions' => ['First still', 'A colorful animation', 'Last still'],
    ]);

    $response->assertSessionHasNoErrors();
    $surface === 'reply' ? $response->assertCreated() : $response->assertRedirect();
    $commit = $user->commits()->where('body', 'An animated attachment')->sole();
    $images = $commit->images;

    expect($images->pluck('position')->all())->toBe([0, 1, 2])
        ->and($images->pluck('description')->all())->toBe(['First still', 'A colorful animation', 'Last still'])
        ->and($images->pluck('mime_type')->all())->toBe(['image/webp', 'video/webm', 'image/webp'])
        ->and($images[1]->duration_ms)->toBeNull()
        ->and($images[1]->width)->toBe(32)
        ->and($images[1]->height)->toBe(24)
        ->and(Storage::get($images[1]->path))->toBe(mediaFixture('animated.webm'))
        ->and(getimagesizefromstring(Storage::get($images[1]->previewPath()))['mime'])->toBe('image/webp')
        ->and(Storage::allFiles('commit-images'))->toHaveCount(4);

    if ($surface === 'series') {
        expect($user->commits()->where('body', 'The second part')->sole()->images)->toBeEmpty();
    }
})->with(['commit', 'quote', 'reply', 'series']);

it('keeps multiple animations and their posters associated with their attachment positions', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'Two animated attachments',
        'images' => [
            UploadedFile::fake()->createWithContent('first.webm', mediaFixture('animated.webm')),
            UploadedFile::fake()->image('still.png', 10, 10),
            UploadedFile::fake()->createWithContent('last.webm', mediaFixture('animated.webm')),
        ],
        'image_posters' => [
            0 => UploadedFile::fake()->image('red.png', 32, 24),
            2 => UploadedFile::fake()->image('blue.png', 32, 24),
        ],
        'image_descriptions' => ['First animation', 'Still image', 'Last animation'],
    ])->assertSessionHasNoErrors();

    $images = $user->commits()->sole()->images;

    expect($images->pluck('mime_type')->all())->toBe(['video/webm', 'image/webp', 'video/webm'])
        ->and($images->pluck('position')->all())->toBe([0, 1, 2])
        ->and($images->pluck('description')->all())->toBe(['First animation', 'Still image', 'Last animation'])
        ->and(Storage::exists($images[0]->previewPath()))->toBeTrue()
        ->and(Storage::exists($images[2]->previewPath()))->toBeTrue()
        ->and(Storage::allFiles('commit-images'))->toHaveCount(5);
});

it('does not reuse another animation poster when one is missing', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'A missing second poster',
        'images' => [
            UploadedFile::fake()->createWithContent('first.webm', mediaFixture('animated.webm')),
            UploadedFile::fake()->createWithContent('second.webm', mediaFixture('animated.webm')),
        ],
        'image_posters' => [
            UploadedFile::fake()->createWithContent('poster.png', mediaFixture('poster.png')),
        ],
    ])->assertSessionHasErrors('images.1')->assertSessionDoesntHaveErrors('images.0');

    expect($user->commits()->count())->toBe(0)
        ->and(Storage::allFiles())->toBeEmpty();
});

it('rejects an animation without a valid bounded poster and stores no attachments', function (string $problem): void {
    $user = User::factory()->create();
    $poster = match ($problem) {
        'missing' => null,
        'excessive dimensions' => UploadedFile::fake()->image('poster.png', 961, 24),
        'unsupported type' => UploadedFile::fake()->image('poster.jpg', 32, 24),
        'invalid content' => UploadedFile::fake()->createWithContent('poster.png', 'not an image'),
        'oversized' => UploadedFile::fake()->createWithContent('poster.png', mediaFixture('poster.png').str_repeat('x', 5 * 1024 * 1024)),
    };

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'An invalid animation',
        'images' => [
            UploadedFile::fake()->image('still.png', 10, 10),
            UploadedFile::fake()->createWithContent('animation.webm', mediaFixture('animated.webm')),
        ],
        'image_posters' => [1 => $poster],
    ])->assertSessionHasErrors();

    expect($user->commits()->count())->toBe(0)
        ->and(Storage::allFiles())->toBeEmpty();
})->with(['missing', 'excessive dimensions', 'unsupported type', 'invalid content', 'oversized']);

it('rejects files whose contents are not recognized as WebM', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('video.webm', 'This is text with a video filename.');

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'A forged video',
        'images' => [new UploadedFile($file->getPathname(), 'video.webm', test: true)],
        'image_posters' => [UploadedFile::fake()->createWithContent('poster.png', mediaFixture('poster.png'))],
    ])->assertSessionHasErrors('images.0');

    expect($user->commits()->count())->toBe(0)
        ->and(Storage::allFiles())->toBeEmpty();
});

it('stores poster dimensions and leaves animation duration unknown', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'An animation with a smaller poster',
        'images' => [UploadedFile::fake()->createWithContent('animation.webm', mediaFixture('animated.webm'))],
        'image_posters' => [UploadedFile::fake()->image('poster.png', 16, 12)],
    ])->assertSessionHasNoErrors();

    $image = $user->commits()->sole()->images()->sole();

    expect($image->width)->toBe(16)
        ->and($image->height)->toBe(12)
        ->and($image->duration_ms)->toBeNull()
        ->and($image->toArray()['duration'])->toBeNull();
});

it('rejects WebM uploads larger than 10 MB', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'An oversized animation',
        'images' => [UploadedFile::fake()->createWithContent('animation.webm', mediaFixture('animated.webm').str_repeat('x', 10 * 1024 * 1024))],
        'image_posters' => [UploadedFile::fake()->createWithContent('poster.png', mediaFixture('poster.png'))],
    ])->assertSessionHasErrors('images.0');

    expect($user->commits()->count())->toBe(0)
        ->and(Storage::allFiles())->toBeEmpty();
});

it('keeps static GIFs in the still-image processing path', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'A static GIF',
        'images' => [UploadedFile::fake()->createWithContent('static.gif', mediaFixture('static.gif'))],
    ])->assertSessionHasNoErrors();

    $image = $user->commits()->sole()->images()->sole();
    expect($image->mime_type)->toBe('image/webp')
        ->and($image->duration_ms)->toBeNull()
        ->and(getimagesizefromstring(Storage::get($image->path))['mime'])->toBe('image/webp');
});
