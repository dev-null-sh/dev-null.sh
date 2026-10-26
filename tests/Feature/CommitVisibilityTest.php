<?php

declare(strict_types=1);

use App\Actions\CreateExport;
use App\Actions\CreateTomFriendship;
use App\Actions\CreateWorldReply;
use App\Enums\CommitSource;
use App\Enums\Log;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;

it('removes disabled channels from every log while preserving own commits', function (string $channel, array $attributes): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => [$channel]]);
    $author = User::factory()->create();
    $viewer->following()->attach($author);
    Commit::factory()->for($author)->count(3)->create($attributes);
    $shown = Commit::factory()->for($author)->create();
    $own = Commit::factory()->for($viewer)->create($attributes);

    foreach (Log::cases() as $log) {
        $this->actingAs($viewer)->get(route('log.show', $log))->assertOk()
            ->assertInertia(fn ($page) => $page->has('commits.data', 2)
                ->where('commits.data', fn ($commits): bool => collect($commits)->pluck('id')->sort()->values()->all() === collect([$shown->id, $own->id])->sort()->values()->all()));
    }
})->with([
    'terminal' => ['cli', ['source' => CommitSource::Cli]],
    'workflow' => ['ci', ['source' => CommitSource::Ci]],
    'ai' => ['ai', ['is_ai' => true]],
]);

it('matches any disabled channel without confusing workflows and terminals', function (): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['cli']]);
    Commit::factory()->create(['source' => CommitSource::Cli, 'is_ai' => true]);
    $workflow = Commit::factory()->create(['source' => CommitSource::Ci, 'is_ai' => true]);
    $normal = Commit::factory()->create();

    $this->actingAs($viewer)->get(route('log.show', Log::Latest))->assertInertia(fn ($page) => $page->has('commits.data', 2));
    $viewer->update(['hidden_commit_channels' => ['ai']]);
    $this->get(route('log.show', Log::Latest))->assertInertia(fn ($page) => $page->has('commits.data', 1)->where('commits.data.0.id', $normal->id));
    $viewer->update(['hidden_commit_channels' => ['ci']]);
    $this->get(route('log.show', Log::Latest))->assertInertia(fn ($page) => $page->has('commits.data', 2)
        ->where('commits.data', fn ($commits): bool => collect($commits)->pluck('id')->doesntContain($workflow->id)));
});

it('keeps guests and the default audience unfiltered and exposes source badges', function (): void {
    $commit = Commit::factory()->create(['source' => CommitSource::Ci, 'is_ai' => true]);

    $this->getJson(route('api-commit.show', $commit))->assertOk()
        ->assertJsonPath('data.channels', ['ci', 'ai'])->assertJsonPath('data.is_filtered', false);
    $this->actingAs(User::factory()->create())->get(route('log.show', Log::Latest))
        ->assertInertia(fn ($page) => $page->has('commits.data', 1)->where('commits.data.0.channels', ['ci', 'ai']));
});

it('keeps filtered replies as content-free placeholders and preserves their descendants', function (): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['cli']]);
    $parent = Commit::factory()->create();
    $preview = LinkPreview::factory()->create(['title' => 'Hidden title']);
    $reply = Commit::factory()->for($parent, 'parent')->for($preview)->create(['source' => CommitSource::Cli, 'body' => 'Hidden message']);
    CommitImage::factory()->for($reply)->create();
    $poll = Poll::factory()->for($reply)->create();
    PollOption::factory()->for($poll)->create(['label' => 'Hidden choice']);
    $child = Commit::factory()->for($reply, 'parent')->create(['body' => 'Visible descendant']);

    $this->actingAs($viewer)->getJson(route('commit-reply.index', $parent))->assertOk()
        ->assertJsonPath('data.0.id', $reply->id)->assertJsonPath('data.0.is_filtered', true)
        ->assertJsonPath('data.0.body', '')->assertJsonPath('data.0.body_segments', [])
        ->assertJsonPath('data.0.images', [])->assertJsonPath('data.0.poll', null)
        ->assertJsonPath('data.0.link_preview', null)->assertJsonPath('data.0.replies_count', 1)
        ->assertDontSee('Hidden message')->assertDontSee('Hidden choice')->assertDontSee('Hidden title');
    $this->getJson(route('commit-reply.index', $reply))->assertOk()->assertJsonPath('data.0.id', $child->id)
        ->assertJsonPath('data.0.body', 'Visible descendant');
});

it('redacts direct links, quoted commits, and ancestors before explicit reveal', function (): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['ai']]);
    $hidden = Commit::factory()->create(['is_ai' => true, 'body' => 'Generated secret phrase']);
    $quote = Commit::factory()->for($hidden, 'quotedCommit')->create();
    $reply = Commit::factory()->for($hidden, 'parent')->create();

    $this->actingAs($viewer)->get(route('commit.show', [$hidden->user, $hidden]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.is_filtered', true)->where('commit.body', ''));
    $this->get(route('commit.show', [$quote->user, $quote]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.quoted_commit.is_filtered', true)->where('commit.quoted_commit.body', ''));
    $this->get(route('commit.show', [$reply->user, $reply]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('ancestors.0.is_filtered', true)->where('ancestors.0.body', ''));
    $this->getJson(route('commit-visibility.show', $hidden))->assertOk()->assertJsonPath('is_filtered', false)
        ->assertJsonPath('body', 'Generated secret phrase')->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('commit.show', [$hidden->user, $hidden]))
        ->assertInertia(fn ($page) => $page->where('commit.is_filtered', true)->where('commit.body', ''));
    expect($viewer->fresh()->hidden_commit_channels)->toBe(['ai']);
});

it('does not let a reveal bypass moderation, deletion, or blocks', function (string $state): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['ai']]);
    $commit = Commit::factory()->create(['is_ai' => true]);
    match ($state) {
        'pending' => $commit->update(['status' => 'pending']),
        'deleted' => $commit->delete(),
        'blocked' => $viewer->blocking()->attach($commit->user),
    };

    $this->actingAs($viewer)->getJson(route('commit-visibility.show', $commit))->assertNotFound();
    $key = apiKeyPairFor($viewer);
    signedApiRequest($key, 'GET', route('api-commit-visibility.show', $commit))->assertNotFound();
})->with(['pending', 'deleted', 'blocked']);

it('requires authentication to reveal a filtered commit', function (): void {
    $commit = Commit::factory()->create();
    $this->getJson(route('commit-visibility.show', $commit))->assertUnauthorized();
    $this->getJson(route('api-commit-visibility.show', $commit))->assertUnauthorized();
});

it('redacts signed API content and offers explicit temporary reveal', function (): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['ci']]);
    $key = apiKeyPairFor($viewer);
    $hidden = Commit::factory()->create(['source' => CommitSource::Ci, 'body' => 'Workflow details']);
    $reply = Commit::factory()->for($hidden, 'parent')->create();

    signedApiRequest($key, 'GET', route('api-commit.show', $hidden))->assertOk()
        ->assertJsonPath('data.is_filtered', true)->assertJsonPath('data.body', '')->assertJsonPath('data.replies_count', 1);
    signedApiRequest($key, 'GET', route('api-commit-visibility.show', $hidden))->assertOk()
        ->assertJsonPath('data.body', 'Workflow details')->assertJsonPath('data.is_filtered', false);
    signedApiRequest($key, 'GET', route('api-commit-reply.index', $hidden))->assertOk()->assertJsonPath('data.0.id', $reply->id);
});

it('labels only Tom and World replies as bots and never filters them as AI', function (string $githubId): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['cli', 'ci', 'ai']]);
    $bot = User::factory()->create(['github_id' => $githubId]);
    $root = Commit::factory()->for($bot)->create();
    $reply = Commit::factory()->for($bot)->for($root, 'parent')->create();

    $this->actingAs($viewer)->getJson(route('commit-reply.index', $root))->assertOk()
        ->assertJsonPath('data.0.id', $reply->id)->assertJsonPath('data.0.channels', ['bot'])
        ->assertJsonPath('data.0.is_filtered', false);
    $this->get(route('commit.show', [$bot, $root]))->assertInertia(fn ($page) => $page->where('commit.channels', []));
})->with([CreateTomFriendship::GITHUB_ID, CreateWorldReply::GITHUB_ID]);

it('filters profiles, pins, saved commits, and search before selecting results', function (): void {
    config(['ai.providers.openai.key' => 'test']);
    fakeSearch();
    $viewer = User::factory()->create(['hidden_commit_channels' => ['ai']]);
    $author = User::factory()->create();
    $hidden = Commit::factory()->for($author)->create(['is_ai' => true, 'embedding' => embeddingVector(0)]);
    $shown = Commit::factory()->for($author)->create(['embedding' => embeddingVector(0)]);
    $author->update(['pinned_commit_id' => $hidden->id]);
    $viewer->starredCommits()->attach([$hidden->id, $shown->id]);

    $this->actingAs($viewer)->get(route('user.show', $author))->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 1)->where('commits.data.0.id', $shown->id)->where('pinnedCommit', null));
    $this->get(route('starred.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 1)->where('commits.data.0.id', $shown->id));
    $this->getJson('/search/commits?q=release')->assertOk()->assertJsonCount(1, 'commits')->assertJsonPath('commits.0.id', $shown->id);
});

it('filters notifications and unread counts without changing stored activity', function (): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['ci']]);
    $hidden = Notification::factory()->for($viewer, 'recipient')->replied()->create();
    $hidden->commit->update(['source' => CommitSource::Ci]);

    $shown = Notification::factory()->for($viewer, 'recipient')->replied()->create();

    $this->actingAs($viewer)->get(route('notification.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)->where('notifications.data.0.id', $shown->id));
    expect($viewer->notifications()->count())->toBe(2);
});

it('invalidates signed browser history when visibility settings change', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer)->get(route('log.show', Log::Latest))
        ->assertViewHas('page', fn (array $page): bool => $page['encryptHistory'] === true);
    $this->put(route('user-commit-visibility.update'), ['hidden_commit_channels' => ['ai']])->assertRedirect();
    $this->get(route('user-commit-visibility.edit'))->assertViewHas('page', fn (array $page): bool => $page['clearHistory'] === true);
});

it('exports declarations and private visibility preferences only to their owner', function (): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['cli', 'ai']]);
    $commit = Commit::factory()->for($viewer)->create(['source' => CommitSource::Cli, 'is_ai' => true]);
    $export = resolve(CreateExport::class)->handle($viewer);

    expect($export['profile']['hidden_commit_channels'])->toBe(['cli', 'ai']);
    $entry = collect(iterator_to_array($export['commits']))->firstWhere('id', $commit->id);
    expect($entry['source'])->toBe('cli')->and($entry['is_ai'])->toBeTrue();
    $this->getJson(route('api-user.show', $viewer))->assertJsonMissingPath('data.hidden_commit_channels');
});

it('opens a filtered photo link at its commit placeholder instead of an empty viewer', function (): void {
    $viewer = User::factory()->create(['hidden_commit_channels' => ['ai']]);
    $commit = Commit::factory()->create(['is_ai' => true]);
    CommitImage::factory()->for($commit)->create();

    $this->actingAs($viewer)->get(route('commit-photo.show', [$commit->user, $commit, 1]))
        ->assertRedirectToRoute('commit.show', [$commit->user, $commit]);
});
