<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\CreateUserKeyPair;
use App\Actions\DeleteCommit;
use App\Actions\UpdateCommitStatuses;
use App\Contracts\Services\DnsResolver;
use App\Enums\CommitStatus;
use App\Jobs\DeliverActivity;
use App\Jobs\FederateCommit;
use App\Jobs\FederateCommitDeletion;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\RemoteFollower;
use App\Models\User;
use App\Services\ActivityStreams;
use App\Services\HttpSignatures;
use App\Services\PublicAddresses;
use App\Services\RemoteActors;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RemoteActor;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);

    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['mastodon.example' => ['93.184.216.34'], 'other.example' => ['93.184.216.35'], 'internal.example' => ['10.0.0.5']][$host] ?? [];
        }
    });
});

function federatingUser(array $attributes = []): User
{
    return resolve(CreateUserKeyPair::class)->handle(User::factory()->create(['username' => 'admin', 'federates' => true, ...$attributes]));
}

it('delivers an activity signed so that Mastodon can check it', function (): void {
    Http::fake(['https://mastodon.example/inbox' => Http::response(status: 202)]);
    $user = federatingUser();
    $activity = ['type' => 'Accept', 'actor' => route('actor.show', $user)];

    new DeliverActivity($user, 'https://mastodon.example/inbox', $activity)->handle(
        resolve(HttpSignatures::class),
        resolve(ActivityStreams::class),
        resolve(RemoteActors::class),
        resolve(PublicAddresses::class),
    );

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://mastodon.example/inbox'
        && $request->header('Content-Type')[0] === 'application/activity+json'
        && $request->body() === json_encode($activity, JSON_UNESCAPED_SLASHES)
        && $request->header('Digest')[0] === 'SHA-256='.base64_encode(hash('sha256', $request->body(), true))
        && str_contains($request->header('Signature')[0], 'keyId="'.route('actor.show', $user).'#main-key"')
        && RemoteActor::verifies((string) $user->public_key, 'POST', '/inbox', $request->headers()));
});

it('does not deliver to unsafe inboxes or without a key', function (string $inbox, bool $withKey): void {
    Http::fake();
    $user = $withKey ? federatingUser() : User::factory()->create(['federates' => true]);

    dispatch_sync(new DeliverActivity($user, $inbox, ['type' => 'Accept']));

    Http::assertNothingSent();
})->with([
    'plain http' => ['http://mastodon.example/inbox', true],
    'a private address' => ['https://internal.example/inbox', true],
    'a host that does not resolve' => ['https://nowhere.example/inbox', true],
    'not a url' => ['inbox', true],
    'no key yet' => ['https://mastodon.example/inbox', false],
]);

it('retries deliveries that may pass, but not refusals', function (int $status, bool $retries): void {
    Http::fake(['https://mastodon.example/inbox' => Http::response(status: $status)]);
    $job = new DeliverActivity(federatingUser(), 'https://mastodon.example/inbox', ['type' => 'Accept']);

    $deliver = fn () => $job->handle(
        resolve(HttpSignatures::class),
        resolve(ActivityStreams::class),
        resolve(RemoteActors::class),
        resolve(PublicAddresses::class),
    );

    $retries ? expect($deliver)->toThrow(RequestException::class) : expect($deliver)->not->toThrow(RequestException::class);
})->with([
    'server error' => [503, true],
    'rate limited' => [429, true],
    'gone' => [410, false],
    'forbidden' => [403, false],
]);

it('sends an approved commit once to each server that follows its author', function (): void {
    Queue::fake();
    $user = federatingUser();
    RemoteFollower::factory()->for($user)->count(2)->create(['shared_inbox_url' => 'https://mastodon.example/inbox']);
    RemoteFollower::factory()->for($user)->create(['actor_url' => 'https://other.example/users/bob', 'inbox_url' => 'https://other.example/users/bob/inbox', 'shared_inbox_url' => null]);
    $commit = Commit::factory()->for($user)->create(['status' => CommitStatus::Approved, 'body' => "Shipped <b>it</b>\ntoday"]);
    CommitImage::factory()->for($commit)->create(['description' => 'A chart', 'width' => 800, 'height' => 600]);

    new FederateCommit($commit)->handle(resolve(ActivityStreams::class));

    $url = route('commit.show', [$user, $commit]);

    Queue::assertPushed(DeliverActivity::class, 2);
    Queue::assertPushed(DeliverActivity::class, fn (DeliverActivity $job): bool => $job->inbox === 'https://other.example/users/bob/inbox');
    Queue::assertPushed(DeliverActivity::class, fn (DeliverActivity $job): bool => $job->inbox === 'https://mastodon.example/inbox'
        && $job->activity['type'] === 'Create'
        && $job->activity['id'] === $url.'#create'
        && $job->activity['actor'] === route('actor.show', $user)
        && $job->activity['to'] === ['https://www.w3.org/ns/activitystreams#Public']
        && $job->activity['cc'] === [route('actor-followers.index', $user)]
        && $job->activity['object']['id'] === $url
        && $job->activity['object']['type'] === 'Note'
        && $job->activity['object']['content'] === "<p>Shipped &lt;b&gt;it&lt;/b&gt;<br>\ntoday</p>"
        && $job->activity['object']['attachment'][0]['mediaType'] === 'image/webp'
        && $job->activity['object']['attachment'][0]['name'] === 'A chart');
});

it('does not send commits that are not public on /dev/null, or whose author does not federate', function (Closure $commit): void {
    Queue::fake();
    $user = federatingUser();
    RemoteFollower::factory()->for($user)->create();

    new FederateCommit($commit($user))->handle(resolve(ActivityStreams::class));

    Queue::assertNothingPushed();
})->with([
    'pending' => fn (User $user) => Commit::factory()->for($user)->create(['status' => CommitStatus::Pending]),
    'a reply' => fn (User $user) => Commit::factory()->for($user)->create(['status' => CommitStatus::Approved, 'parent_id' => Commit::factory()]),
    'deleted' => fn (User $user) => tap(Commit::factory()->for($user)->create(['status' => CommitStatus::Approved]))->delete(),
    'author stopped federating' => fn (User $user) => Commit::factory()->for(tap($user)->update(['federates' => false]))->create(['status' => CommitStatus::Approved]),
]);

it('tells followers when an approved commit stays deleted', function (): void {
    Queue::fake();
    $user = federatingUser();
    RemoteFollower::factory()->for($user)->create(['shared_inbox_url' => 'https://mastodon.example/inbox']);
    $commit = Commit::factory()->for($user)->create(['status' => CommitStatus::Approved]);
    $commit->delete();

    new FederateCommitDeletion($commit)->handle(resolve(ActivityStreams::class));

    Queue::assertPushed(DeliverActivity::class, fn (DeliverActivity $job): bool => $job->inbox === 'https://mastodon.example/inbox'
        && $job->activity['type'] === 'Delete'
        && $job->activity['object'] === ['id' => route('commit.show', [$user, $commit]), 'type' => 'Tombstone']);
});

it('does not send a deletion for commits that were restored or never sent', function (Closure $commit): void {
    Queue::fake();
    $user = federatingUser();
    RemoteFollower::factory()->for($user)->create();

    new FederateCommitDeletion($commit($user))->handle(resolve(ActivityStreams::class));

    Queue::assertNothingPushed();
})->with([
    'restored' => fn (User $user) => Commit::factory()->for($user)->create(['status' => CommitStatus::Approved]),
    'pending' => fn (User $user) => tap(Commit::factory()->for($user)->create(['status' => CommitStatus::Pending]))->delete(),
    'a reply' => fn (User $user) => tap(Commit::factory()->for($user)->create(['status' => CommitStatus::Approved, 'parent_id' => Commit::factory()]))->delete(),
    'author stopped federating' => fn (User $user) => tap(Commit::factory()->for(tap($user)->update(['federates' => false]))->create(['status' => CommitStatus::Approved]))->delete(),
]);

it('queues the commit for followers when it is approved on creation', function (): void {
    Queue::fake();
    $user = federatingUser();

    $commit = resolve(CreateCommit::class)->handle($user, 'Shipped federation.');

    expect($commit->status)->toBe(CommitStatus::Approved);
    Queue::assertPushed(FederateCommit::class, fn (FederateCommit $job): bool => $job->commit->is($commit));
});

it('does not queue commits of users who do not federate', function (): void {
    Queue::fake();

    resolve(CreateCommit::class)->handle(User::factory()->create(['username' => 'admin']), 'Shipped federation.');

    Queue::assertNotPushed(FederateCommit::class);
});

it('queues the commit for followers when a moderator approves it', function (): void {
    Queue::fake();
    $user = federatingUser(['username' => 'someone']);
    $commit = Commit::factory()->for($user)->create(['status' => CommitStatus::Pending]);
    $reply = Commit::factory()->for($user)->create(['status' => CommitStatus::Pending, 'parent_id' => Commit::factory()]);
    $other = Commit::factory()->create(['status' => CommitStatus::Pending]);

    resolve(UpdateCommitStatuses::class)->handle([$commit->id, $reply->id, $other->id], CommitStatus::Approved);

    Queue::assertPushed(FederateCommit::class, 1);
    Queue::assertPushed(FederateCommit::class, fn (FederateCommit $job): bool => $job->commit->is($commit));
});

it('queues the deletion after the undo window when a commit is deleted', function (): void {
    Queue::fake();
    $user = federatingUser();
    $commit = Commit::factory()->for($user)->create(['status' => CommitStatus::Approved]);

    resolve(DeleteCommit::class)->handle($commit);

    Queue::assertPushed(FederateCommitDeletion::class, fn (FederateCommitDeletion $job): bool => $job->commit->is($commit) && $job->delay !== null);
});

it('does not queue a deletion for users who do not federate', function (): void {
    Queue::fake();

    resolve(DeleteCommit::class)->handle(Commit::factory()->create(['status' => CommitStatus::Approved]));

    Queue::assertNotPushed(FederateCommitDeletion::class);
});
