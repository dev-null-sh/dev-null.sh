<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Support\Facades\Storage;

test('to array', function (): void {
    $image = CommitImage::factory()->create()->refresh();

    expect($image->toArray())->toBe([
        'id' => $image->id,
        'width' => 1200,
        'height' => 800,
        'description' => null,
        'mime_type' => 'image/webp',
        'duration' => null,
        'url' => route('commit-image.show', $image),
        'poster_url' => null,
    ]);
});

it('uses the stored image itself as its preview', function (): void {
    $image = CommitImage::factory()->create()->refresh();

    expect($image->isAnimation())->toBeFalse()
        ->and($image->previewPath())->toBe($image->path);
});

it('exposes an animation and its separate poster', function (): void {
    $image = CommitImage::factory()->create([
        'path' => 'commit-images/animation.webm',
        'mime_type' => 'video/webm',
        'duration_ms' => 470,
    ])->refresh();

    expect($image->isAnimation())->toBeTrue()
        ->and($image->previewPath())->toBe('commit-images/animation.webm.poster.webp')
        ->and($image->toArray())->toMatchArray([
            'mime_type' => 'video/webm',
            'duration' => 0.47,
            'poster_url' => route('commit-image.show', [$image, 'poster' => 1]),
        ]);
});

it('resolves public media and preview URLs from the configured disk', function (): void {
    Storage::fake(config: ['url' => 'https://media.example.test']);
    $image = CommitImage::factory()->create([
        'path' => 'commit-images/animation.webm',
        'mime_type' => 'video/webm',
        'duration_ms' => 470,
    ]);

    expect($image->publicUrl())->toBe('https://media.example.test/commit-images/animation.webm')
        ->and($image->publicUrl(preview: true))->toBe('https://media.example.test/commit-images/animation.webm.poster.webp');
});

it('belongs to a commit', function (): void {
    $commit = Commit::factory()->create();
    $image = CommitImage::factory()->for($commit)->create();

    expect($image->commit->is($commit))->toBeTrue();
});

it('is deleted with its commit', function (): void {
    $image = CommitImage::factory()->create();

    $image->commit->forceDelete();

    expect(CommitImage::query()->count())->toBe(0);
});
