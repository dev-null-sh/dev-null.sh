<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Message;

it('merges the previews of each canonical url into the one fetched last', function (): void {
    $fetchedLast = LinkPreview::factory()->fetched()->create(['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=a', 'fetched_at' => now()->subHour()]);
    $canonical = LinkPreview::factory()->fetched()->create(['url' => 'https://youtu.be/dQw4w9WgXcQ', 'fetched_at' => now()->subDay()]);
    $pending = LinkPreview::factory()->create(['url' => 'https://youtu.be/dQw4w9WgXcQ?si=b']);
    $commit = Commit::factory()->create(['link_preview_id' => $canonical->id]);
    $deletedCommit = Commit::factory()->trashed()->create(['link_preview_id' => $pending->id]);
    $message = Message::factory()->create(['link_preview_id' => $pending->id]);
    $renamed = LinkPreview::factory()->create(['url' => 'https://Example.com/#top']);
    $untouched = LinkPreview::factory()->create(['url' => 'https://example.org/post', 'updated_at' => now()->subWeek()]);

    $migration = require database_path('migrations/2026_11_18_145248_merge_link_previews_by_canonical_url.php');
    $migration->up();

    expect(LinkPreview::query()->pluck('id')->sort()->values()->all())->toBe(collect([$fetchedLast->id, $renamed->id, $untouched->id])->sort()->values()->all())
        ->and($fetchedLast->refresh())
        ->url->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->url_hash->toBe(hash('sha256', 'https://youtu.be/dQw4w9WgXcQ'))
        ->and($commit->refresh()->link_preview_id)->toBe($fetchedLast->id)
        ->and(Commit::withTrashed()->findOrFail($deletedCommit->id)->link_preview_id)->toBe($fetchedLast->id)
        ->and($message->refresh()->link_preview_id)->toBe($fetchedLast->id)
        ->and($renamed->refresh())
        ->url->toBe('https://example.com')
        ->url_hash->toBe(hash('sha256', 'https://example.com'))
        ->and(LinkPreview::query()->findOrFail($untouched->id)->only(['url', 'url_hash', 'updated_at']))->toEqual($untouched->only(['url', 'url_hash', 'updated_at']));
});
