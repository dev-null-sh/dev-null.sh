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
            'github',
            'embed',
        ]);
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

it('highlights lines of code from github when shown', function (): void {
    $linkPreview = LinkPreview::factory()->fetched()->create([
        'github' => ['type' => 'code', 'label' => 'acme/repo', 'state' => null, 'stars' => null, 'language' => 'php', 'lines' => ['<?php', 'echo "<script>alert(1)</script>";'], 'start' => 1],
    ]);

    $html = $linkPreview->toArray()['github']['html'] ?? '';

    expect($html)
        ->toContain('hl-keyword')
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>');
});

it('does not highlight github previews without code', function (): void {
    $linkPreview = LinkPreview::factory()->fetched()->create([
        'github' => ['type' => 'issue', 'label' => 'acme/repo#1', 'state' => 'open', 'stars' => null, 'language' => null],
    ]);

    expect($linkPreview->toArray()['github'])->not->toHaveKey('html');
});

it('is detached from its commits when deleted', function (): void {
    $linkPreview = LinkPreview::factory()->create();
    $commit = Commit::factory()->for($linkPreview)->create();

    $linkPreview->delete();

    expect($commit->refresh()->link_preview_id)->toBeNull();
});
