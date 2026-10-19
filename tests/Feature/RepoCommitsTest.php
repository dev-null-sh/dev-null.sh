<?php

declare(strict_types=1);

use App\Actions\DeleteRepoContributor;
use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Enums\NotificationType;
use App\Jobs\ModerateCommit;
use App\Models\Commit;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\ScheduledCommit;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * A repo with a maintainer and a contributor, both approved without review.
 *
 * @return array{Repo, User, User}
 */
function repoWithContributors(): array
{
    config(['app.admins' => ['maintainer', 'contributor']]);

    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->for(User::factory()->state(['username' => 'maintainer']))->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->for(User::factory()->state(['username' => 'contributor']))->create()->user;

    return [$repo, $maintainer, $contributor];
}

it('commits to a repo through the composer', function (): void {
    [$repo, , $contributor] = repoWithContributors();

    $this->actingAs($contributor)
        ->fromRoute('repo.show', $repo)
        ->post(route('commit.store'), ['body' => 'Shipped the repos', 'repo_id' => $repo->id])
        ->assertRedirectToRoute('repo.show', $repo)
        ->assertInertiaFlash('committed.body', 'Shipped the repos');

    $commit = Commit::query()->sole();

    expect($commit->repo_id)->toBe($repo->id)
        ->and($commit->isVisibleTo($contributor))->toBeTrue()
        ->and($commit->isVisibleTo(User::factory()->create()))->toBeFalse();
});

it('only commits to repos the author contributes to', function (): void {
    $repo = Repo::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('commit.store'), ['body' => 'Let me in', 'repo_id' => $repo->id])
        ->assertSessionHasErrors(['repo_id' => 'That repo is no longer available to commit to.']);
    $this->post(route('commit.store'), ['body' => 'Let me in', 'repo_id' => 'not-a-uuid'])
        ->assertSessionHasErrors('repo_id');

    expect(Commit::query()->count())->toBe(0);
});

it('lists the repo commits with their threads on the repo page only', function (): void {
    [$repo, $maintainer, $contributor] = repoWithContributors();
    $commit = Commit::factory()->for($maintainer)->for($repo)->create();
    $reply = Commit::factory()->for($contributor)->for($repo)->for($commit, 'parent')->create();

    $this->actingAs($contributor)->get(route('repo.show', $repo))->assertInertia(fn ($page) => $page
        ->has('commits.data', 1)
        ->where('commits.data.0.id', $commit->id)
        ->where('commits.data.0.replies_count', 1)
        ->where('commits.data.0.reply_preview.data.0.id', $reply->id));

    $this->get(route('log.show', Log::Latest))->assertInertia(fn ($page) => $page->has('commits.data', 0));
    $this->get(route('user.show', $maintainer))->assertInertia(fn ($page) => $page
        ->has('commits.data', 0)
        ->where('user.commits_count', 0));
});

it('keeps replies in the repo of the commit they reply to', function (): void {
    [$repo, $maintainer, $contributor] = repoWithContributors();
    $commit = Commit::factory()->for($maintainer)->for($repo)->create();

    $this->actingAs($contributor)
        ->postJson(route('commit-reply.store', $commit), ['body' => 'Agreed, ship it.'])
        ->assertCreated();

    $reply = $commit->replies()->sole();

    expect($reply->repo_id)->toBe($repo->id)
        ->and($maintainer->notifications()->sole()->type)->toBe(NotificationType::Replied);
    $this->actingAs($maintainer)->getJson(route('commit-reply.index', $commit))->assertJsonPath('data.0.id', $reply->id);
    $this->actingAs(User::factory()->create())->getJson(route('commit-reply.index', $commit))->assertNotFound();
});

it('keeps quotes in the repo of the commit they quote', function (): void {
    [$repo, $maintainer, $contributor] = repoWithContributors();
    $commit = Commit::factory()->for($maintainer)->for($repo)->create();

    $this->actingAs($contributor)
        ->post(route('commit.store'), ['body' => 'Look at this one', 'quoted_commit_id' => $commit->id])
        ->assertSessionHasNoErrors();

    expect(Commit::query()->where('quoted_commit_id', $commit->id)->sole()->repo_id)->toBe($repo->id)
        ->and($maintainer->notifications()->sole()->type)->toBe(NotificationType::Quoted);
});

it('saves and stars repo commits for contributors', function (): void {
    [$repo, $maintainer, $contributor] = repoWithContributors();
    $commit = Commit::factory()->for($maintainer)->for($repo)->create();

    $this->actingAs($contributor)->putJson(route('bookmark.store', $commit))->assertOk();
    $this->postJson(route('commit-star.store', $commit))->assertSuccessful();

    $this->get(route('bookmark.index'))->assertInertia(fn ($page) => $page->where('commits.data.0.id', $commit->id));
    expect($commit->stars()->count())->toBeOne();
});

it('schedules commits to a repo', function (): void {
    [$repo, , $contributor] = repoWithContributors();

    $this->actingAs($contributor)->post(route('scheduled-commit.store'), [
        'body' => 'Release notes for the repo',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'repo_id' => $repo->id,
    ])->assertSessionHasNoErrors();

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect(Commit::query()->sole()->repo_id)->toBe($repo->id);
});

it('cancels the commits someone scheduled to a repo once they leave it', function (): void {
    [$repo, , $contributor] = repoWithContributors();
    $this->actingAs($contributor)->post(route('scheduled-commit.store'), [
        'body' => 'Release notes for the repo',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'repo_id' => $repo->id,
    ])->assertSessionHasNoErrors();

    resolve(DeleteRepoContributor::class)->handle($repo, $contributor);

    expect(ScheduledCommit::query()->sole()->canceled_at)->not->toBeNull();
});

it('notifies only contributors mentioned in repo commits', function (): void {
    [$repo, $maintainer, $contributor] = repoWithContributors();
    $outsider = User::factory()->create(['username' => 'outsider']);

    $this->actingAs($maintainer)->post(route('commit.store'), [
        'body' => 'Pairing with @contributor and @outsider',
        'repo_id' => $repo->id,
    ])->assertSessionHasNoErrors();

    expect($contributor->notifications()->sole()->type)->toBe(NotificationType::Mentioned)
        ->and($outsider->notifications()->count())->toBe(0);
});

it('hides notifications about repo commits from people who left the repo', function (): void {
    [$repo, $maintainer, $contributor] = repoWithContributors();
    $commit = Commit::factory()->for($contributor)->for($repo)->create();
    $this->actingAs($maintainer)->postJson(route('commit-reply.store', $commit), ['body' => 'Nice one.'])->assertCreated();
    $pending = Commit::factory()->for($contributor)->for($repo)->create(['status' => CommitStatus::Rejected]);
    $contributor->notifications()->create(['actor_id' => $contributor->id, 'type' => NotificationType::Rejected, 'commit_id' => $pending->id, 'key' => 'rejected:'.$pending->id]);

    expect($contributor->notifications()->relevant()->count())->toBe(2);

    resolve(DeleteRepoContributor::class)->handle($repo, $contributor);

    expect($contributor->notifications()->relevant()->count())->toBe(0);
});

it('lets maintainers delete any reply in their repo, without an undo', function (): void {
    [$repo, $maintainer, $contributor] = repoWithContributors();
    $commit = Commit::factory()->for($maintainer)->for($repo)->create();
    $reply = Commit::factory()->for($contributor)->for($repo)->for($commit, 'parent')->create();

    $this->actingAs($maintainer)
        ->deleteJson(route('commit-reply.destroy', [$commit, $reply]))
        ->assertOk()
        ->assertExactJson(['toast' => ['type' => 'success', 'message' => 'Reply deleted.']]);

    $this->assertSoftDeleted($reply);
    $this->actingAs($contributor)->deleteJson(route('commit-reply.destroy', [$commit, Commit::factory()->for($maintainer)->for($repo)->for($commit, 'parent')->create()]))->assertForbidden();
});

it('does not pin repo commits to profiles', function (): void {
    [$repo, $maintainer] = repoWithContributors();
    $commit = Commit::factory()->for($maintainer)->for($repo)->create();

    $this->actingAs($maintainer)->put(route('commit-pin.update', $commit))->assertForbidden();
});

it('moderates repo commits like any other', function (bool $approved): void {
    Queue::fake();
    config(['app.admins' => ['moderator'], 'services.openai.key' => 'test-key']);

    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $newcomer = RepoContributor::factory()->for($repo)->create()->user;
    $moderator = User::factory()->create(['username' => 'moderator']);

    $this->actingAs($newcomer)->post(route('commit.store'), ['body' => 'First repo commit', 'repo_id' => $repo->id]);

    $commit = Commit::query()->sole();

    expect($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->isVisibleTo($maintainer))->toBeFalse();
    Queue::assertPushed(ModerateCommit::class);

    $this->actingAs($moderator)->get(route('moderation.show'))
        ->assertInertia(fn ($page) => $page->has('commits.data', 1)->where('commits.data.0.id', $commit->id));

    $this->post(route($approved ? 'commit-approval.store' : 'commit-rejection.store', $commit))->assertRedirect();

    expect($commit->refresh()->status)->toBe($approved ? CommitStatus::Approved : CommitStatus::Rejected)
        ->and($commit->isVisibleTo($maintainer))->toBe($approved)
        ->and($commit->isVisibleTo($moderator))->toBeFalse();
})->with(['approved' => true, 'rejected' => false]);
