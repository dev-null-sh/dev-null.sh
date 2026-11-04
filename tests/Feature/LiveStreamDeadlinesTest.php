<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\LivePlatform;
use App\Enums\Log;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\CommitReport;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function streamingFor(int $minutes, ?User $user = null): User
{
    $user ??= User::factory()->create();
    $user->forceFill([
        'live_platform' => LivePlatform::Twitch,
        'live_url' => 'https://www.twitch.tv/streamer',
        'live_ends_at' => now()->addMinutes($minutes),
    ])->save();

    return $user->refresh();
}

/**
 * @return array{url: string, thumbnail_url: string, ends_at: string}
 */
function streamOf(User $user): array
{
    return [
        'url' => 'https://www.twitch.tv/streamer',
        'thumbnail_url' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_streamer-1280x720.jpg',
        'ends_at' => (string) $user->live_ends_at?->toJSON(),
    ];
}

function inertiaGet(string $url): TestResponse
{
    return test()->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request())])->assertOk();
}

it("says when a profile's stream runs out on its page, in its preview and to the person streaming", function (): void {
    $this->freezeSecond();
    $streamer = streamingFor(30);

    $this->actingAs(User::factory()->create());
    expect(inertiaGet(route('user.show', $streamer))->json('props.user.live'))->toBe(streamOf($streamer))
        ->and($this->getJson(route('user-preview.show', $streamer))->assertOk()->json('user.live'))->toBe(streamOf($streamer));

    $this->actingAs($streamer);
    expect(inertiaGet(route('user.show', $streamer))->json('props.auth.user.live'))->toBe(streamOf($streamer));
});

it('says when each followed stream in the aside runs out', function (): void {
    $this->freezeSecond();
    $viewer = User::factory()->create();
    $streamer = streamingFor(45);
    $viewer->following()->attach($streamer);

    $this->actingAs($viewer);

    expect(inertiaGet(route('starred.index'))->json('props.liveFollowing.0.live'))->toBe(streamOf($streamer));
});

it('keeps the aside across pages no longer than the first stream it lists lasts, so one extended meanwhile is read again', function (): void {
    $this->freezeSecond();
    $viewer = User::factory()->create();
    $soon = streamingFor(1);
    $viewer->following()->attach([$soon->id, streamingFor(30)->id]);
    $this->actingAs($viewer);

    expect(inertiaGet(route('starred.index'))->json('onceProps.liveFollowing.expiresAt'))->toBe(now()->addMinute()->getTimestampMs());

    $soon->forceFill(['live_ends_at' => now()->addSeconds(10)])->save();

    expect(inertiaGet(route('starred.index'))->json('onceProps.liveFollowing.expiresAt'))->toBe(now()->addSeconds(10)->getTimestampMs());
});

it("says when a suggested person's stream runs out, for the preview that shows it", function (): void {
    $this->freezeSecond();
    $profile = User::factory()->has(Commit::factory())->create();
    $suggested = streamingFor(35, User::factory()->has(Commit::factory())->create());
    $profile->following()->attach($suggested);

    $this->actingAs(User::factory()->create())->get(route('user.show', $profile))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('suggestedUsers.0.id', $suggested->id)
            ->where('suggestedUsers.0.live', streamOf($suggested))));
});

it("says when the stream of every commit card's author runs out, in pages and in the reads that load more", function (): void {
    $this->freezeSecond();
    $streamer = streamingFor(20);
    $replier = streamingFor(50);
    $root = Commit::factory()->for($streamer)->create();
    $reply = Commit::factory()->for($streamer)->for($root, 'parent')->create();
    Commit::factory()->for($streamer)->for($reply, 'parent')->create();
    Commit::factory()->for($replier)->for($root, 'parent')->create();
    $this->actingAs(User::factory()->create());

    $log = inertiaGet(route('log.show', Log::Latest))->json('props.commits.data');
    $shown = collect($log)->keyBy('id');

    expect($shown[$root->id]['author']['live'])->toBe(streamOf($streamer))
        ->and($shown[$root->id]['reply_preview']['data'][0]['author']['live'])->toBe(streamOf($replier));

    $thread = inertiaGet(route('commit.show', ['profile' => $streamer, 'commit' => $reply]))->json('props');

    expect($thread['commit']['author']['live'])->toBe(streamOf($streamer))
        ->and($thread['ancestors'][0]['author']['live'])->toBe(streamOf($streamer))
        ->and($thread['continuation']['data'][0]['author']['live'])->toBe(streamOf($streamer))
        ->and($this->getJson(route('commit-reply.index', $root))->assertOk()->json('data.0.author.live'))->toBe(streamOf($streamer))
        ->and($this->getJson(route('commit-continuation.index', $reply))->assertOk()->json('data.0.author.live'))->toBe(streamOf($streamer));
});

it("says when the stream of each card's author runs out on every other page and read that shows commits", function (): void {
    $this->freezeSecond();
    $streamer = streamingFor(25);
    $link = LinkPreview::factory()->fetched()->create();
    $commit = Commit::factory()->for($streamer)->create(['link_preview_id' => $link->id, 'embedding' => embeddingVector(0)]);
    CommitImage::factory()->for($commit)->create(['position' => 0]);
    CommitReport::factory()->create(['commit_id' => $commit->id]);
    $pending = Commit::factory()->for($streamer)->pending()->create();
    config(['app.admins' => ['moderator']]);
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $moderator->starredCommits()->attach($commit);
    $this->actingAs($moderator);

    expect(inertiaGet(route('starred.index'))->json('props.commits.data.0.author.live'))->toBe(streamOf($streamer))
        ->and(inertiaGet(route('link.index', ['url' => $link->url]))->json('props.commits.data.0.author.live'))->toBe(streamOf($streamer))
        ->and(inertiaGet(route('moderation.show'))->json('props.commits.data.0.author.live'))->toBe(streamOf($streamer))
        ->and(inertiaGet(route('reported-commit.index'))->json('props.reports.data.0.commit.author.live'))->toBe(streamOf($streamer))
        ->and(inertiaGet(route('commit-photo.show', [$streamer, $commit, 1]))->json('props.photo.commit.author.live'))->toBe(streamOf($streamer))
        ->and($this->getJson(route('commit-search.index', ['q' => 'feature']))->assertOk()->json('commits.0.author.live'))->toBe(streamOf($streamer));

    $this->actingAs($streamer)->delete(route('commit.destroy', $pending))->assertRedirect();

    $this->fromRoute('log.show', Log::Latest)->post(route('commit-restoration.store', $pending))
        ->assertInertiaFlash('restored.author.live', streamOf($streamer));
});

it('says the end of a stream as it is when read, however it changed since an earlier page', function (): void {
    $this->freezeSecond();
    $streamer = streamingFor(1);
    $this->actingAs(User::factory()->create());

    expect(inertiaGet(route('user.show', $streamer))->json('props.user.live.ends_at'))->toBe(now()->addMinute()->toJSON());

    $this->travel(30)->seconds();
    $streamer->forceFill(['live_ends_at' => now()->addHours(2)])->save();

    expect(inertiaGet(route('user.show', $streamer))->json('props.user.live.ends_at'))->toBe(now()->addHours(2)->toJSON());
});

it('says when a shown stream runs out however many other people are live', function (): void {
    $this->freezeSecond();
    User::factory()->count(101)->create(['live_platform' => LivePlatform::Twitch, 'live_url' => 'https://www.twitch.tv/early', 'live_ends_at' => now()->addMinute()]);
    $streamer = streamingFor(90);
    Commit::factory()->for($streamer)->create(['status' => CommitStatus::Approved]);
    $this->actingAs(User::factory()->create());

    $page = inertiaGet(route('user.show', $streamer));

    expect($page->json('props.user.live'))->toBe(streamOf($streamer))
        ->and($page->json('props.commits.data.0.author.live'))->toBe(streamOf($streamer))
        ->and($page->json('props'))->not->toHaveKey('liveDeadlines');
});

it('never says when a stream runs out that the page does not show, or one that has ended', function (): void {
    $this->freezeSecond();
    streamingFor(30);
    $ended = streamingFor(-1);
    $this->actingAs(User::factory()->create());

    $page = inertiaGet(route('about.show'));
    expect($page->json('props'))->not->toHaveKey('liveDeadlines')
        ->and(inertiaGet(route('user.show', $ended))->json('props.user.live'))->toBeNull();
});
