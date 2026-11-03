<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\OrganizationRole;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function changesSince(array $ids, int $since): TestResponse
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

it("names a reply deleted or approved since, and the viewer's own pending reply, but nobody else's", function (): void {
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

it('answers for a commit the viewer cannot see exactly as for one that does not exist', function (Closure $hidden, bool $signedIn): void {
    $viewer = User::factory()->create();
    $commit = $hidden($viewer);
    Commit::factory()->for($commit, 'parent')->create(['created_at' => now()->subHour()]);
    $since = now()->getTimestampMs();
    $this->travel(5)->seconds();
    Commit::factory()->for($commit, 'parent')->create();
    $missing = (string) Str::uuid();

    if ($signedIn) {
        $this->actingAs($viewer);
    }

    $changes = changesSince([$commit->id, $missing], $since)->json('changes');

    expect($changes[$commit->id])->toBe($changes[$missing])
        ->and($changes[$missing])->toBe(['changed' => true, 'replies' => [], 'truncated' => false]);
})->with([
    "someone else's pending commit" => [fn (User $viewer): Commit => Commit::factory()->pending()->create(['created_at' => now()->subHour()]), true],
    "someone else's rejected commit" => [fn (User $viewer): Commit => Commit::factory()->rejected()->create(['created_at' => now()->subHour()]), true],
    'a pending commit, to a guest' => [fn (User $viewer): Commit => Commit::factory()->for($viewer)->pending()->create(['created_at' => now()->subHour()]), false],
    'a commit by someone the viewer blocks' => [function (User $viewer): Commit {
        $commit = Commit::factory()->create(['created_at' => now()->subHour()]);
        $viewer->blocking()->attach($commit->user_id);

        return $commit;
    }, true],
    'a commit by someone who blocks the viewer' => [function (User $viewer): Commit {
        $commit = Commit::factory()->create(['created_at' => now()->subHour()]);
        $commit->user->blocking()->attach($viewer);

        return $commit;
    }, true],
    "an organization's pending commit, once the viewer left it" => [function (User $viewer): Commit {
        $organization = Organization::factory()->create();
        $organization->members()->attach($viewer->id, ['role' => OrganizationRole::Member]);
        $commit = Commit::factory()->pending()->create(['author_id' => $organization->id, 'created_at' => now()->subHour()]);
        $organization->members()->detach($viewer->id);

        return $commit;
    }, true],
]);

it("checks the commits the viewer can see, their own unapproved ones, their organizations' and tombstones", function (): void {
    $viewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($viewer->id, ['role' => OrganizationRole::Member]);
    $own = Commit::factory()->for($viewer)->rejected()->create(['created_at' => now()->subHour()]);
    $organizations = Commit::factory()->pending()->create(['author_id' => $organization->id, 'created_at' => now()->subHour()]);
    $tombstone = Commit::factory()->create(['created_at' => now()->subHour()]);
    $tombstone->delete();

    $since = now()->addSecond()->getTimestampMs();
    $this->travel(5)->seconds();
    $reply = Commit::factory()->for($tombstone, 'parent')->create();

    $this->actingAs($viewer);
    $changes = changesSince([$own->id, $organizations->id, $tombstone->id], $since)->json('changes');

    expect($changes[$own->id])->toBe(['changed' => false, 'replies' => [], 'truncated' => false])
        ->and($changes[$organizations->id])->toBe(['changed' => false, 'replies' => [], 'truncated' => false])
        ->and($changes[$tombstone->id])->toBe(['changed' => false, 'replies' => [$reply->id], 'truncated' => false]);
});

it('names only the replies the viewer could see in the thread', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $muted = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);
    $viewer->muting()->attach($muted);
    $commit = Commit::factory()->create(['created_at' => now()->subHour()]);
    $since = now()->getTimestampMs();
    $this->travel(5)->seconds();
    $approved = Commit::factory()->for($commit, 'parent')->create();
    $mine = Commit::factory()->for($viewer)->for($commit, 'parent')->pending()->create();
    foreach ([$blocked, $blocker, $muted] as $author) {
        Commit::factory()->for($author)->for($commit, 'parent')->create();
    }

    Commit::factory()->for($commit, 'parent')->pending()->create();
    Commit::factory()->for($commit, 'parent')->rejected()->create();

    $this->actingAs($viewer);

    expect(changesSince([$commit->id], $since)->json("changes.{$commit->id}"))
        ->toBe(['changed' => false, 'replies' => [$approved->id, $mine->id], 'truncated' => false]);
});

it('asks a thread to read again what it loaded when a reply it may have shown was hidden since, without naming it', function (): void {
    $commit = Commit::factory()->create(['created_at' => now()->subHour()]);
    $shown = Commit::factory()->for($commit, 'parent')->create(['created_at' => now()->subMinutes(30)]);
    $since = now()->getTimestampMs();
    $this->travel(5)->seconds();
    $shown->update(['status' => CommitStatus::Rejected]);

    expect(changesSince([$commit->id], $since)->json("changes.{$commit->id}"))
        ->toBe(['changed' => false, 'replies' => [], 'truncated' => true]);
});
