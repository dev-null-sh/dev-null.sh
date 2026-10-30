<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Jobs\LinkCommitTopics;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('links known organizations right away, a user of the same handle first', function (): void {
    Queue::fake();

    $author = User::factory()->create();
    $tom = User::factory()->create(['handle' => 'tom']);
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    Organization::factory()->create(['handle' => 'Tom']);

    $this->actingAs($author)->post('/commits', ['body' => 'Thanks @Laravel and @tom'])->assertRedirect();

    $commit = $author->commits()->sole();

    expect($commit->mentions)->toBe(['tom' => $tom->id])
        ->and($commit->organization_mentions)->toBe(['laravel' => $laravel->id]);

    $this->actingAs($author)->get(route('commit.show', [$author, $commit]))
        ->assertInertia(fn ($page) => $page->where('commit.organization_mentions.laravel', $laravel->id));
});

it('asks GitHub about the other handles of a commit approved right away', function (): void {
    config(['app.admins' => ['admin']]);
    Queue::fake();

    $author = User::factory()->create(['handle' => 'admin']);

    $this->actingAs($author)->post('/commits', ['body' => 'Thanks @laravel'])->assertRedirect();

    Queue::assertPushed(LinkCommitTopics::class, fn (LinkCommitTopics $job): bool => $job->commit->is($author->commits()->sole()));
});

it('does not ask GitHub about commits that only mention users', function (): void {
    config(['app.admins' => ['admin']]);
    Queue::fake();

    $author = User::factory()->create(['handle' => 'admin']);
    User::factory()->create(['handle' => 'tom']);

    $this->actingAs($author)->post('/commits', ['body' => 'Thanks @tom'])->assertRedirect();

    Queue::assertNotPushed(LinkCommitTopics::class);
});

it('waits for the approval of a pending commit to ask GitHub', function (): void {
    config(['app.admins' => ['moderator']]);
    Queue::fake();

    $commit = Commit::factory()->pending()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $other = Commit::factory()->pending()->create(['body' => 'Thanks', 'mentions' => []]);

    Queue::assertNotPushed(LinkCommitTopics::class);

    $this->actingAs($moderator)->post(route('commit-approval.store', $commit))->assertRedirect();
    $this->actingAs($moderator)->post(route('commit-approval.store', $other))->assertRedirect();

    Queue::assertPushed(LinkCommitTopics::class, 1);
    Queue::assertPushed(LinkCommitTopics::class, fn (LinkCommitTopics $job): bool => $job->commit->is($commit));
});

it('links mentions to the organization they captured, even after it is renamed', function (): void {
    $organization = Organization::factory()->create(['handle' => 'laravel']);

    $this->get(route('organization-mention.show', $organization))->assertRedirect('/@laravel');

    $organization->profile->update(['handle' => 'laravel-renamed']);

    $this->get(route('organization-mention.show', $organization))->assertRedirect('/@laravel-renamed');
});

it('previews which mentions are organizations', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['handle' => 'laravel']);

    $this->actingAs($user)->postJson(route('commit-preview.store'), ['body' => 'Thanks @laravel'])
        ->assertOk()
        ->assertJsonPath('organization_mentions', ['laravel' => $organization->id]);
});

it('tells API clients which mentions are organizations', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $organization = Organization::factory()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => '@laravel and @tom', 'mentions' => []]);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $organization->id]);

    signedApiRequest($keyPair, 'GET', route('api-log.show', 'latest'))
        ->assertOk()
        ->assertJsonPath('data.0.mentions', [
            ['handle' => 'laravel', 'offset' => 0, 'length' => 8, 'kind' => 'organization'],
            ['handle' => 'tom', 'offset' => 13, 'length' => 4, 'kind' => 'user'],
        ]);

    signedApiRequest($keyPair, 'POST', route('api-commit-preview.store'), ['body' => 'Hi @Laravel'])
        ->assertOk()
        ->assertJsonPath('data.mentions', [['handle' => 'Laravel', 'offset' => 3, 'length' => 8, 'kind' => 'organization']]);
});
