<?php

declare(strict_types=1);

use App\Actions\CreateCommitStar;
use App\Actions\CreateCommitTopics;
use App\Actions\CreateMentionNotifications;
use App\Actions\CreateQuoteNotifications;
use App\Actions\CreateReplyNotifications;
use App\Actions\CreateTomFriendship;
use App\Actions\CreateWorldReply;
use App\Actions\DeleteCommitStar;
use App\Actions\DeleteOrganization;
use App\Actions\DeleteOrganizationMember;
use App\Actions\DeleteUser;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Enums\OperationsEventSource;
use App\Enums\OrganizationRole;
use App\Jobs\CheckUserLevel;
use App\Jobs\ReplyAsTom;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Queries\OperationsEventsQuery;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['handle' => 'laravel']);
    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();
    $this->organization->members()->attach($this->admin->id, ['role' => OrganizationRole::Admin]);
    $this->organization->members()->attach($this->member->id, ['role' => OrganizationRole::Member]);
    $this->commit = Commit::factory()->for($this->admin)->create(['author_id' => $this->organization->id]);
});

/**
 * @return array<string, string|null>
 */
function heardBy(NotificationType $type): array
{
    return Notification::query()->where('type', $type)->pluck('organization_id', 'recipient_id')->all();
}

it('tells every member of the organization about replies to its commits, but the one who replied', function (): void {
    $reply = Commit::factory()->for($this->commit, 'parent')->create();
    $ownReply = Commit::factory()->for($this->commit, 'parent')->for($this->member)->create();

    resolve(CreateReplyNotifications::class)->handle($reply, $ownReply);

    expect(Notification::query()->where('commit_id', $reply->id)->pluck('organization_id', 'recipient_id')->all())->toEqualCanonicalizing([
        $this->admin->id => $this->organization->id,
        $this->member->id => $this->organization->id,
    ])->and(Notification::query()->where('commit_id', $ownReply->id)->pluck('recipient_id')->all())->toBe([$this->admin->id]);
});

it('tells every member of the organization about quotes of its commits and stars on them, while they hold', function (): void {
    $stargazer = User::factory()->create();
    $quote = Commit::factory()->create(['quoted_commit_id' => $this->commit->id]);

    resolve(CreateQuoteNotifications::class)->handle($quote);
    resolve(CreateCommitStar::class)->handle($stargazer, $this->commit);

    $everyone = [$this->admin->id => $this->organization->id, $this->member->id => $this->organization->id];

    expect(heardBy(NotificationType::Quoted))->toEqualCanonicalizing($everyone)
        ->and(heardBy(NotificationType::Starred))->toEqualCanonicalizing($everyone);

    resolve(DeleteCommitStar::class)->handle($stargazer, $this->commit);

    expect(heardBy(NotificationType::Starred))->toBe([]);
});

it('tells every member of an organization it is mentioned, once, and not again those who hear about it otherwise', function (): void {
    $reply = Commit::factory()->for($this->commit, 'parent')->create([
        'body' => 'Thanks @laravel and @'.$this->member->handle,
        'mentions' => [mb_strtolower($this->member->handle) => $this->member->id],
    ]);
    $outsider = Commit::factory()->for(User::factory())->create(['body' => 'Hello @laravel']);
    $own = Commit::factory()->for($this->member)->create(['body' => 'Proud of @laravel']);

    foreach ([$reply, $outsider, $own] as $commit) {
        resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $this->organization->id]);
    }

    resolve(CreateMentionNotifications::class)->handle($reply, $outsider, $own);

    expect(Notification::query()->where('commit_id', $reply->id)->exists())->toBeFalse()
        ->and(Notification::query()->where('commit_id', $outsider->id)->pluck('organization_id', 'recipient_id')->all())->toEqualCanonicalizing([
            $this->admin->id => $this->organization->id,
            $this->member->id => $this->organization->id,
        ])
        ->and(Notification::query()->where('commit_id', $own->id)->pluck('organization_id', 'recipient_id')->all())->toBe([
            $this->admin->id => $this->organization->id,
        ]);
});

it('tells someone mentioned both themselves and as a member of the organization once, for themselves', function (): void {
    $commit = Commit::factory()->create([
        'body' => '@laravel @'.$this->member->handle,
        'mentions' => [mb_strtolower($this->member->handle) => $this->member->id],
    ]);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $this->organization->id]);

    resolve(CreateMentionNotifications::class)->handle($commit);

    expect($this->member->notifications()->sole()->organization_id)->toBeNull()
        ->and($this->admin->notifications()->sole()->organization_id)->toBe($this->organization->id);
});

it('keeps each member from hearing from people they mute or across a block', function (): void {
    $troll = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $this->member->muting()->attach($troll);
    $this->member->blocking()->attach($blocked);
    $blocker->blocking()->attach($this->member);
    $replies = collect([$troll, $blocked, $blocker])->map(fn (User $user): Commit => Commit::factory()->for($this->commit, 'parent')->for($user)->create());

    resolve(CreateReplyNotifications::class)->handle(...$replies);

    expect(Notification::query()->pluck('recipient_id')->unique()->values()->all())->toBe([$this->admin->id]);
});

it('stops telling members once they are removed, keeping what they already heard', function (): void {
    $first = Commit::factory()->for($this->commit, 'parent')->create();
    resolve(CreateReplyNotifications::class)->handle($first);

    resolve(DeleteOrganizationMember::class)->handle($this->organization, $this->member);

    $second = Commit::factory()->for($this->commit, 'parent')->create();
    resolve(CreateReplyNotifications::class)->handle($second);

    expect($this->member->notifications()->pluck('commit_id')->all())->toBe([$first->id])
        ->and($this->admin->notifications()->count())->toBe(2);
});

it('still tells the other members once the poster deleted their account', function (): void {
    resolve(DeleteUser::class)->handle($this->admin);
    $reply = Commit::factory()->for($this->commit->refresh(), 'parent')->pending()->create();

    resolve(UpdateCommitStatuses::class)->handle([$reply->id], CommitStatus::Approved);

    expect(Notification::query()->pluck('recipient_id')->all())->toBe([$this->member->id]);
});

it('tells every member of the organization about its rejected commit, each as their own actor, while they are members', function (): void {
    resolve(UpdateCommitStatuses::class)->handle([$this->commit->id], CommitStatus::Rejected, 'Spam');

    expect(heardBy(NotificationType::Rejected))->toEqualCanonicalizing([
        $this->admin->id => $this->organization->id,
        $this->member->id => $this->organization->id,
    ])->and(Notification::query()->whereColumn('actor_id', '<>', 'recipient_id')->exists())->toBeFalse();

    $this->actingAs($this->member)
        ->get(route('notification.index', ['type' => 'moderation']))
        ->assertInertia(fn ($page) => $page->where('notifications.data.0.reason', 'Spam'));
    $this->get(route('commit.show', ['profile' => 'laravel', 'commit' => $this->commit]))->assertOk();

    resolve(DeleteOrganizationMember::class)->handle($this->organization, $this->member);

    $this->get(route('notification.index', ['type' => 'moderation']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 0));
    $this->get(route('commit.show', ['profile' => 'laravel', 'commit' => $this->commit]))->assertNotFound();
});

it('deletes what the members heard about an organization GitHub no longer has', function (): void {
    $mention = Commit::factory()->create(['body' => 'Hello @laravel']);
    resolve(CreateCommitTopics::class)->handle($mention, ['laravel' => $this->organization->id]);
    resolve(CreateMentionNotifications::class)->handle($mention);

    $followed = Notification::factory()->for($this->member, 'recipient')->create();

    resolve(DeleteOrganization::class)->handle($this->organization);

    expect(Notification::query()->pluck('id')->all())->toBe([$followed->id]);
});

it('names the organization a notification is for, in the inbox and the API', function (): void {
    $reply = Commit::factory()->for($this->commit, 'parent')->create();
    resolve(CreateReplyNotifications::class)->handle($reply);

    $this->actingAs($this->member)
        ->get(route('notification.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.data.0.organization.handle', 'laravel')
            ->where('notifications.data.0.organization.id', $this->organization->id));

    signedApiRequest(apiKeyPairFor($this->member), 'GET', route('api-notification.index'))
        ->assertOk()
        ->assertJsonPath('data.0.organization.handle', 'laravel');
});

it('exports the organization each notification was for', function (): void {
    $reply = Commit::factory()->for($this->commit, 'parent')->create();
    resolve(CreateReplyNotifications::class)->handle($reply);

    $response = $this->actingAs($this->member)->get(route('export'));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data['notifications'][0]['organization'])->toBe('laravel');
});

it('shows every member the commit of the organization a quote quotes, rejected too, until they leave it', function (): void {
    $quote = Commit::factory()->create(['quoted_commit_id' => $this->commit->id]);
    resolve(CreateQuoteNotifications::class)->handle($quote);
    $this->commit->update(['status' => CommitStatus::Rejected]);

    $this->actingAs($this->member)
        ->get(route('notification.index'))
        ->assertInertia(fn ($page) => $page->where('notifications.data.0.commit.quoted_commit.id', $this->commit->id));

    resolve(DeleteOrganizationMember::class)->handle($this->organization, $this->member);

    $this->get(route('notification.index'))
        ->assertInertia(fn ($page) => $page->where('notifications.data.0.commit.quoted_commit', null));
});

it('groups what a member hears about for the organization apart from what they hear about for themselves', function (): void {
    $thread = Commit::factory()->create();
    $personal = Commit::factory()->for($thread, 'parent')->create([
        'body' => 'Thanks @'.$this->admin->handle,
        'mentions' => [mb_strtolower($this->admin->handle) => $this->admin->id],
    ]);
    $organizational = Commit::factory()->for($thread, 'parent')->create(['body' => 'Thanks @laravel', 'created_at' => now()->addMinute()]);
    resolve(CreateCommitTopics::class)->handle($organizational, ['laravel' => $this->organization->id]);
    resolve(CreateMentionNotifications::class)->handle($personal, $organizational);

    $this->actingAs($this->admin)
        ->get(route('notification.index', ['type' => 'mentions']))
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 2)
            ->where('notifications.data.0.organization.handle', 'laravel')
            ->where('notifications.data.1.organization', null));
});

it('groups what a member hears about for each of their organizations apart', function (): void {
    $symfony = Organization::factory()->create(['handle' => 'symfony']);
    $symfony->members()->attach($this->admin->id, ['role' => OrganizationRole::Member]);
    $thread = Commit::factory()->create();
    $mentions = collect(['laravel' => $this->organization, 'symfony' => $symfony])->values()->map(function (Organization $organization, int $index) use ($thread): Commit {
        $commit = Commit::factory()->for($thread, 'parent')->create(['body' => 'Thanks @'.$organization->handle, 'created_at' => now()->addMinutes($index)]);
        resolve(CreateCommitTopics::class)->handle($commit, [$organization->handle => $organization->id]);

        return $commit;
    });
    resolve(CreateMentionNotifications::class)->handle(...$mentions);

    $this->actingAs($this->admin)
        ->get(route('notification.index', ['type' => 'mentions']))
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 2)
            ->where('notifications.data.0.organization.handle', 'symfony')
            ->where('notifications.data.1.organization.handle', 'laravel'));
});

it("tells no member about a newcomer's first commit that mentions the organization twice", function (): void {
    $newcomer = User::factory()->create();
    $newcomer->followers()->attach($this->member);
    $commit = Commit::factory()->for($newcomer)->pending()->create(['body' => 'Hello @laravel']);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $this->organization->id]);

    resolve(UpdateCommitStatuses::class)->handle([$commit->id], CommitStatus::Approved);

    expect($this->member->notifications()->pluck('type')->all())->toBe([NotificationType::Mentioned]);
});

it('lets the members of an organization see its pending commits and their images, as their own, and no one else', function (): void {
    $pending = Commit::factory()->for($this->admin)->pending()->create(['author_id' => $this->organization->id]);
    $outsider = User::factory()->create();

    expect($pending->isVisibleTo($this->member))->toBeTrue()
        ->and($pending->imagesAreVisibleTo($this->member))->toBeTrue()
        ->and($pending->isVisibleTo($outsider))->toBeFalse()
        ->and($pending->imagesAreVisibleTo($outsider))->toBeFalse()
        ->and(Commit::query()->visibleTo($this->member)->whereKey($pending->id)->exists())->toBeTrue()
        ->and(Commit::query()->visibleTo($outsider)->whereKey($pending->id)->exists())->toBeFalse();
});

it("lists an organization's rejected commit once in the operations feed, as its poster's", function (): void {
    resolve(UpdateCommitStatuses::class)->handle([$this->commit->id], CommitStatus::Rejected, 'Spam');

    expect(collect(resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable(), OperationsEventSource::Moderation))
        ->where('message', 'Commit rejected')->pluck('actor')->all())->toBe([$this->admin->handle]);
});

it('greets the organization for its hello world, which tells every member', function (): void {
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id, 'body' => 'Hello, World!']);

    resolve(CreateWorldReply::class)->handle($commit);

    $reply = $commit->replies()->sole();

    expect($reply->body)->toBe("Hello, {$this->organization->name}.")
        ->and(Notification::query()->where('commit_id', $reply->id)->pluck('organization_id', 'recipient_id')->all())->toEqualCanonicalizing([
            $this->admin->id => $this->organization->id,
            $this->member->id => $this->organization->id,
        ]);
});

it("asks Tom to answer the organization's commits addressed to him, as anyone's", function (): void {
    config(['ai.providers.opencode.key' => 'test', 'ai.providers.openai.key' => 'test']);
    $tom = User::factory()->create(['handle' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $reply = Commit::factory()->pending()->for(Commit::factory()->for($tom), 'parent')->for($this->member)->create(['author_id' => $this->organization->id]);
    Queue::fake([ReplyAsTom::class, CheckUserLevel::class]);

    resolve(UpdateCommitStatuses::class)->handle([$reply->id], CommitStatus::Approved);

    Queue::assertPushed(ReplyAsTom::class, fn (ReplyAsTom $job): bool => $job->commitId === $reply->id);
});
