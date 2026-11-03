<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Support\Str;

function changesSince(array $ids, int $since): Illuminate\Testing\TestResponse
{
    return test()->getJson(route('commit-change.index', ['ids' => $ids, 'since' => $since]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
}

it('names what changed about each commit since the page read it, and nothing else', function (): void {
    $quiet = Commit::factory()->create();
    $replied = Commit::factory()->create();
    $deleted = Commit::factory()->create();
    $approved = Commit::factory()->pending()->create();
    $since = now()->getTimestampMs();
    $this->travel(5)->seconds();

    $reply = Commit::factory()->for($replied, 'parent')->create();
    $quote = Commit::factory()->create(['quoted_commit_id' => $replied->id]);
    Commit::factory()->for($replied, 'parent')->pending()->create();
    $deleted->delete();
    Commit::query()->whereKey($approved->id)->update(['status' => CommitStatus::Approved]);

    $changes = changesSince([$quiet->id, $replied->id, $deleted->id, $approved->id], $since)->json('changes');

    expect($changes[$quiet->id])->toBe(['changed' => false, 'replies' => [], 'truncated' => false])
        ->and($changes[$replied->id]['changed'])->toBeFalse()
        ->and($changes[$replied->id]['replies'])->toEqualCanonicalizing([$reply->id, $quote->id])
        ->and($changes[$deleted->id]['changed'])->toBeTrue()
        ->and($changes[$approved->id]['changed'])->toBeTrue();
});

it('names a reply deleted or approved since, and the viewer\'s own pending reply, but nobody else\'s', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $deleted = Commit::factory()->for($commit, 'parent')->create(['created_at' => now()->subHour()]);
    $since = now()->getTimestampMs();
    $this->travel(5)->seconds();
    $deleted->delete();
    $mine = Commit::factory()->for($viewer)->for($commit, 'parent')->pending()->create();
    Commit::factory()->for($commit, 'parent')->pending()->create();

    $this->actingAs($viewer);

    expect(changesSince([$commit->id], $since)->json("changes.{$commit->id}.replies"))->toEqualCanonicalizing([$deleted->id, $mine->id]);
});

it('counts a poll vote, a poll closing, and a link card read since as changes', function (): void {
    $voted = Commit::factory()->create();
    $voted->poll()->create(['duration' => 60, 'opened_at' => now()->subMinutes(10)])
        ->options()->create(['label' => 'Yes', 'position' => 0]);
    $closing = Commit::factory()->create();
    $closing->poll()->create(['duration' => 60, 'opened_at' => now()->subMinutes(59)]);
    $preview = LinkPreview::query()->create(['url_hash' => hash('sha256', 'https://example.com'), 'url' => 'https://example.com']);
    $unfurled = Commit::factory()->create(['link_preview_id' => $preview->id]);
    $since = now()->getTimestampMs();
    $this->travel(2)->minutes();
    $voted->poll->votes()->create(['poll_option_id' => $voted->poll->options->first()->id, 'user_id' => User::factory()->create()->id]);
    $preview->update(['fetched_at' => now()]);

    $changes = changesSince([$voted->id, $closing->id, $unfurled->id], $since)->json('changes');

    expect(array_column($changes, 'changed'))->toBe([true, true, true]);
});

it('says when more replies changed than it names, and counts a commit it cannot find as changed', function (): void {
    $commit = Commit::factory()->create();
    $since = now()->getTimestampMs();
    $this->travel(5)->seconds();
    Commit::factory()->for($commit, 'parent')->count(51)->create();
    $missing = (string) Str::uuid();

    $changes = changesSince([$commit->id, $missing], $since)->json('changes');

    expect($changes[$commit->id]['replies'])->toHaveCount(50)
        ->and($changes[$commit->id]['truncated'])->toBeTrue()
        ->and($changes[$missing]['changed'])->toBeTrue();
});

it('needs ids and when the page read them', function (): void {
    $this->getJson(route('commit-change.index'))->assertUnprocessable()->assertJsonValidationErrors(['ids', 'since']);
});
