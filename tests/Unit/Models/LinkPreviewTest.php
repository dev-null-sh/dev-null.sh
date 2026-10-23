<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use Carbon\CarbonInterface;

test('to array', function (): void {
    $linkPreview = LinkPreview::factory()->fetched()->create()->refresh();

    expect(array_keys($linkPreview->toArray()))
        ->toBe([
            'url',
            'title',
            'description',
            'image_url',
            'site_name',
            'is_pending',
            'embed',
        ]);
});

it('is pending until it is fetched', function (): void {
    expect(LinkPreview::factory()->create()->toArray()['is_pending'])->toBeTrue()
        ->and(LinkPreview::factory()->fetched()->create()->toArray()['is_pending'])->toBeFalse()
        ->and(LinkPreview::factory()->failed()->create()->toArray()['is_pending'])->toBeFalse();
});

it('lets only one stale instance start the publication retry', function (): void {
    $preview = LinkPreview::factory()->failed()->create();
    $first = LinkPreview::query()->findOrFail($preview->id);
    $second = LinkPreview::query()->findOrFail($preview->id);

    expect($first->prepareUnfurlForPublication())->toBeTrue()
        ->and($second->prepareUnfurlForPublication())->toBeFalse()
        ->and($preview->refresh())
        ->fetched_at->toBeNull()
        ->publication_unfurl_retries->toBe(1);
});

it('distinguishes definitive completion from a recorded transient failure', function (): void {
    $completed = LinkPreview::factory()->create();
    $completed->completeUnfurl(['title' => null]);

    $transientFailure = LinkPreview::factory()->failed()->create();

    expect($completed->fresh()->prepareUnfurlForPublication())->toBeFalse()
        ->and($transientFailure->prepareUnfurlForPublication())->toBeTrue();
});

it('does not let an old completion overwrite a newer publication retry', function (): void {
    $preview = LinkPreview::factory()->create();
    $oldCycle = LinkPreview::query()->findOrFail($preview->id);
    $oldCycle->markUnfurlAsTransientFailure(0);

    $retry = LinkPreview::query()->findOrFail($preview->id);
    $retry->prepareUnfurlForPublication();

    expect($oldCycle->completeUnfurl(['title' => 'Old']))->toBeFalse()
        ->and(LinkPreview::query()->findOrFail($preview->id)->completeUnfurl(['title' => 'New']))->toBeTrue()
        ->and($preview->refresh()->title)->toBe('New');
});

it('has an embed when its link can be played in place', function (): void {
    expect(LinkPreview::factory()->create(['url' => 'https://youtu.be/dQw4w9WgXcQ'])->toArray()['embed'])->toMatchArray([
        'provider' => 'YouTube',
        'src' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
    ])
        ->and(LinkPreview::factory()->create(['url' => 'https://example.com'])->toArray()['embed'])->toBeNull();
});

it('hashes its url', function (): void {
    $linkPreview = LinkPreview::factory()->create(['url' => 'https://example.com']);

    expect($linkPreview->url_hash)->toBe(hash('sha256', 'https://example.com'));
});

it('serializes a verified external GIF without exposing its storage field', function (): void {
    $preview = LinkPreview::factory()->fetched()->create([
        'url' => 'https://example.com/cat.gif',
        'gif_url' => 'https://cdn.example.com/cat.gif',
    ])->refresh();

    expect($preview->gif_url)->toBe('https://cdn.example.com/cat.gif')
        ->and($preview->toArray())->not->toHaveKey('gif_url')
        ->and($preview->toArray()['embed'])->toMatchArray([
            'kind' => 'gif',
            'src' => 'https://cdn.example.com/cat.gif',
        ]);
});

it('casts the fetched at timestamp', function (): void {
    $linkPreview = LinkPreview::factory()->fetched()->create()->refresh();

    expect($linkPreview->fetched_at)->toBeInstanceOf(CarbonInterface::class)
        ->and(LinkPreview::factory()->create()->refresh()->fetched_at)->toBeNull();
});

it('may have no title', function (): void {
    $linkPreview = LinkPreview::factory()->withoutTitle()->create();

    expect($linkPreview->title)->toBeNull()
        ->and($linkPreview->fetched_at)->not->toBeNull();
});

it('is detached from its commits when deleted', function (): void {
    $linkPreview = LinkPreview::factory()->create();
    $commit = Commit::factory()->for($linkPreview)->create();

    $linkPreview->delete();

    expect($commit->refresh()->link_preview_id)->toBeNull();
});
