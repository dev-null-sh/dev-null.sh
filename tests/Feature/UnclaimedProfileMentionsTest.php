<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Actions\CreateGitHubProfile;
use App\Actions\CreateUnclaimedProfileTopics;
use App\Actions\DeleteUser;
use App\Actions\UpdateStaleHandles;
use App\Contracts\Services\GitHubProvider;
use App\Enums\TopicType;
use App\Jobs\LinkUnclaimedProfileTopics;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * @return array<string, string>
 */
function developerTopics(): array
{
    return DB::table('commit_topics')
        ->where('topic_type', TopicType::Developer)
        ->orderBy('commit_id')
        ->pluck('topic_id', 'commit_id')
        ->all();
}

it('links a commit to the developers with a page who are not here yet, apart from users and organizations', function (): void {
    Queue::fake();

    $author = User::factory()->create();
    $tom = User::factory()->create(['handle' => 'tom']);
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $octocat = Profile::factory()->create(['handle' => 'octocat']);

    $this->actingAs($author)->post('/commits', ['body' => 'Thanks @OctoCat, @tom, @laravel and @nobody'])->assertRedirect();

    $commit = $author->commits()->sole();

    expect($commit->mentions)->toBe(['tom' => $tom->id])
        ->and($commit->organization_mentions)->toBe(['laravel' => $laravel->id])
        ->and(developerTopics())->toBe([$commit->id => $octocat->id]);
});

it('links the commits that mentioned a developer before they had a page', function (): void {
    $octocat = Profile::factory()->create(['handle' => 'octocat']);
    $claimed = User::factory()->create(['handle' => 'monalisa']);

    $mention = Commit::factory()->create(['body' => 'Thanks @OctoCat!', 'mentions' => []]);
    $reply = Commit::factory()->for(Commit::factory(), 'parent')->create(['body' => 'cc @octocat', 'mentions' => []]);
    $deleted = tap(Commit::factory()->create(['body' => 'Hi @octocat', 'mentions' => []]))->delete();
    Commit::factory()->create(['body' => '`@octocat` in code', 'mentions' => []]);
    Commit::factory()->create(['body' => 'Hi @octocat-fan and octocat@example.com', 'mentions' => []]);
    Commit::factory()->create(['body' => 'Hi @octocat', 'mentions' => ['octocat' => $claimed->id]]);
    Commit::factory()->create(['body' => 'Hi @monalisa', 'mentions' => []]);

    resolve(CreateUnclaimedProfileTopics::class)->handle($octocat, $claimed->profile);
    resolve(CreateUnclaimedProfileTopics::class)->handle($octocat);

    expect(developerTopics())->toEqualCanonicalizing([
        $mention->id => $octocat->id,
        $reply->id => $octocat->id,
        $deleted->id => $octocat->id,
    ]);
});

it('looks up the earlier mentions of a developer once they get a page, and at their new handle', function (): void {
    Queue::fake([LinkUnclaimedProfileTopics::class]);
    $account = ['login' => 'octocat', 'id' => 583231, 'type' => 'User', 'name' => 'The Octocat', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null];
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('accountByLogin')->with('octocat')->andReturn($account);
    $github->shouldReceive('accountByLogin')->with('octocat-new')->andReturn([...$account, 'login' => 'octocat-new']);
    $github->shouldReceive('followerIds')->andReturn([]);

    resolve(CreateGitHubProfile::class)->handle('octocat');
    resolve(CreateGitHubProfile::class)->handle('octocat');

    $profile = Profile::query()->sole();
    Queue::assertPushed(LinkUnclaimedProfileTopics::class, 1);
    Queue::assertPushed(LinkUnclaimedProfileTopics::class, fn (LinkUnclaimedProfileTopics $job): bool => $job->profile->is($profile));

    resolve(CreateGitHubProfile::class)->handle('octocat-new');

    Queue::assertPushed(LinkUnclaimedProfileTopics::class, 2);
});

it('leaves the earlier mentions of a handle to whoever had its page here before', function (): void {
    Queue::fake([LinkUnclaimedProfileTopics::class]);
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('accountByLogin')->with('octocat')->andReturn(['login' => 'octocat', 'id' => 583231, 'type' => 'User', 'name' => 'The Octocat', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]);
    $github->shouldReceive('followerIds')->andReturn([]);
    $previous = Profile::factory()->create(['github_id' => '1', 'handle' => 'octocat']);
    $commit = Commit::factory()->create(['body' => 'Thanks @octocat', 'mentions' => []]);
    resolve(CreateCommitTopics::class)->handle($commit, [], ['octocat' => $previous->id]);

    resolve(CreateGitHubProfile::class)->handle('octocat');

    $this->assertModelMissing($previous);
    expect(Profile::query()->where('handle', 'octocat')->sole()->github_id)->toBe('583231')
        ->and(developerTopics())->toBe([]);
    Queue::assertNotPushed(LinkUnclaimedProfileTopics::class);
});

it('links the earlier mentions in the queue', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat']);
    $commit = Commit::factory()->create(['body' => 'Thanks @octocat', 'mentions' => []]);

    dispatch_sync(new LinkUnclaimedProfileTopics($profile));

    expect(developerTopics())->toBe([$commit->id => $profile->id]);
});

it('drops the mentions linked to a developer whose handle someone else took', function (): void {
    $previous = Profile::factory()->create(['github_id' => '1', 'handle' => 'octocat']);
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => '@octocat @laravel']);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $laravel->id], ['octocat' => $previous->id]);

    resolve(UpdateStaleHandles::class)->handle('octocat', '2');

    $this->assertModelMissing($previous);
    expect(DB::table('commit_topics')->pluck('topic_id')->all())->toBe([$laravel->id]);
});

it('drops the mentions linked to them from before they signed up when a user deletes their account', function (): void {
    $user = User::factory()->create();
    $other = Profile::factory()->create();
    $commit = Commit::factory()->create(['body' => 'Hi']);
    resolve(CreateCommitTopics::class)->handle($commit, [], ['someone' => $user->id, 'other' => $other->id]);

    resolve(DeleteUser::class)->handle($user);

    expect(developerTopics())->toBe([$commit->id => $other->id]);
});
