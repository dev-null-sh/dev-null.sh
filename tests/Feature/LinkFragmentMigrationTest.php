<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;

it('gives each commit back the fragment of its card link once, under a preview copied from the fetched page', function (): void {
    $page = LinkPreview::factory()->fetched()->create(['url' => 'https://php.net/archive/2026.php', 'title' => 'PHP: News Archive - 2026']);
    $entry = Commit::factory()->create(['link_preview_id' => $page->id, 'body' => 'PHP 8.5.1 is out https://www.php.net/archive/2026.php#2026-11-18-1']);
    $sameEntry = Commit::factory()->create(['link_preview_id' => $page->id, 'body' => 'Upgrading today: https://php.net/archive/2026.php#2026-11-18-1 #php']);
    $highlight = Commit::factory()->create(['link_preview_id' => $page->id, 'body' => 'https://php.net/archive/2026.php#:~:text=released']);
    $hashtag = Commit::factory()->create(['link_preview_id' => $page->id, 'body' => 'All the news #php https://php.net/archive/2026.php']);
    $otherLink = Commit::factory()->create(['link_preview_id' => $page->id, 'body' => 'https://php.net/archive/2026.php and https://example.com/docs#install']);
    $plainFirst = Commit::factory()->create(['link_preview_id' => $page->id, 'body' => 'https://php.net/archive/2026.php, see https://php.net/archive/2026.php#2026-11-20-1']);
    $pendingPage = LinkPreview::factory()->create(['url' => 'https://example.org/post']);
    $pending = Commit::factory()->create(['link_preview_id' => $pendingPage->id, 'body' => 'https://example.org/post#comments']);
    $existing = LinkPreview::factory()->fetched()->create(['url' => 'https://php.net/archive/2026.php#2026-10-20-1']);
    $alreadyThere = Commit::factory()->create(['link_preview_id' => $page->id, 'body' => 'https://php.net/archive/2026.php#2026-10-20-1']);

    $migration = require database_path('migrations/2026_11_18_201054_give_link_cards_back_their_fragment.php');
    $migration->up();
    $migration->up();

    $preview = LinkPreview::query()->where('url_hash', hash('sha256', 'https://php.net/archive/2026.php#2026-11-18-1'))->firstOrFail();

    expect($preview->only(['url', 'title', 'description', 'image_url', 'site_name']))->toBe([
        'url' => 'https://php.net/archive/2026.php#2026-11-18-1',
        ...$page->only(['title', 'description', 'image_url', 'site_name']),
    ])
        ->and($preview->fetched_at?->toDateTimeString())->toBe($page->fetched_at?->toDateTimeString())
        ->and($entry->refresh()->link_preview_id)->toBe($preview->id)
        ->and($sameEntry->refresh()->link_preview_id)->toBe($preview->id)
        ->and($highlight->refresh()->link_preview_id)->toBe($page->id)
        ->and($hashtag->refresh()->link_preview_id)->toBe($page->id)
        ->and($otherLink->refresh()->link_preview_id)->toBe($page->id)
        ->and($plainFirst->refresh()->link_preview_id)->toBe($page->id)
        ->and($pending->refresh()->link_preview_id)->toBe($pendingPage->id)
        ->and($alreadyThere->refresh()->link_preview_id)->toBe($existing->id)
        ->and(LinkPreview::query()->count())->toBe(4);
});
