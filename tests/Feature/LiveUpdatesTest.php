<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\CreateCommitStar;
use App\Actions\CreateConversationCall;
use App\Actions\CreateDevice;
use App\Actions\CreateMessage;
use App\Actions\CreateOrganizationFollow;
use App\Actions\CreateOrganizationMember;
use App\Actions\CreateScheduledCommit;
use App\Actions\CreateUserBlock;
use App\Actions\CreateUserFollow;
use App\Actions\CreateUserTimeout;
use App\Actions\DeleteOrganization;
use App\Actions\DeleteUser;
use App\Actions\UpdateConversationReadThrough;
use App\Actions\UpdateFeature;
use App\Actions\UpdateLinkPreview;
use App\Actions\UpdateOrganizationLane;
use App\Actions\UpdateOrgCall;
use App\Actions\UpdateScheduledCommitSubmittedAt;
use App\Actions\UpdateUserLog;
use App\Actions\UpdateUserTermsAcceptance;
use App\Contracts\Services\LinkPreviewProvider;
use App\Dto\AuthorizationCode;
use App\Enums\CommitStatus;
use App\Enums\CommitUpdate;
use App\Enums\Feature;
use App\Enums\Log;
use App\Enums\ModerationReason;
use App\Enums\RemoteStatus;
use App\Enums\TimeoutDuration;
use App\Enums\UserUpdate;
use App\Events\CommitsUpdated;
use App\Events\FeaturesUpdated;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\Organization;
use App\Models\ScheduledCommit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['app.admins' => ['admin'], 'remotes.requests' => true, 'remotes.everyone' => true]);
    Event::fake([UsersUpdated::class, CommitsUpdated::class, ProfilesUpdated::class]);
});

/**
 * What was broadcast: an event with nobody to tell never is.
 *
 * @return list<UsersUpdated>
 */
function usersUpdated(UserUpdate $update): array
{
    return Event::dispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === $update && $event->broadcastWhen())->map(fn (array $event): UsersUpdated => $event[0])->values()->all();
}

/**
 * @return list<CommitsUpdated>
 */
function commitsUpdated(CommitUpdate $update): array
{
    return Event::dispatched(CommitsUpdated::class, fn (CommitsUpdated $event): bool => $event->update === $update && $event->broadcastWhen())->map(fn (array $event): CommitsUpdated => $event[0])->values()->all();
}

/**
 * @param  list<UsersUpdated>  $events
 * @return list<string>
 */
function recipientsOf(array $events): array
{
    return collect($events)->flatMap(fn (UsersUpdated $event): array => $event->userIds)->unique()->sort()->values()->all();
}

it('announces nothing when the transaction that changed it rolls back', function (): void {
    $sender = User::factory()->withRemotes()->create();
    $recipient = User::factory()->withRemotes()->create();
    sendChatMessage($sender, $recipient);
    Event::fake([UsersUpdated::class]);
    $conversation = Conversation::between($sender, $recipient);

    try {
        DB::transaction(function () use ($sender, $conversation): void {
            resolve(CreateMessage::class)->handle($sender, $conversation, 'never sent', Str::uuid()->toString());

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
        //
    }

    Event::assertNotDispatched(UsersUpdated::class);
    expect(Message::query()->where('body', 'never sent')->exists())->toBeFalse();
});

it('tells everyone in a conversation that a message arrived, from the web and the API alike, without its content', function (): void {
    $sender = User::factory()->withRemotes()->create();
    $recipient = User::factory()->withRemotes()->create();

    sendChatMessage($sender, $recipient, 'a private hello');
    signedApiRequest(apiKeyPairFor($recipient), 'POST', route('api-conversation-message.store', $sender), [
        'body' => 'a private reply',
        'client_id' => Str::uuid()->toString(),
    ])->assertCreated();

    $events = usersUpdated(UserUpdate::Messages);
    $conversation = Conversation::between($sender, $recipient);
    expect($events)->toHaveCount(2)
        ->and($events[0]->userIds)->toEqualCanonicalizing([$sender->id, $recipient->id])
        ->and($events[1]->broadcastWith())->toBe(['conversation' => $conversation?->id])
        ->and(json_encode($events[0]->broadcastWith()))->not->toContain('hello')
        ->and(collect($events[0]->broadcastOn())->map->name->all())->toEqualCanonicalizing(['private-users.'.$sender->id, 'private-users.'.$recipient->id]);
});

it('announces typing when it starts, not on every ping that keeps it alive', function (): void {
    $sender = User::factory()->withRemotes()->create();
    $recipient = User::factory()->withRemotes()->create();
    sendChatMessage($sender, $recipient);

    $this->actingAs($sender)->putJson(route('conversation-typing.update', $recipient))->assertNoContent();
    $this->actingAs($sender)->putJson(route('conversation-typing.update', $recipient))->assertNoContent();

    expect(usersUpdated(UserUpdate::Conversation))->toHaveCount(1)
        ->and(usersUpdated(UserUpdate::Conversation)[0]->userIds)->toBe([$recipient->id]);
});

it("tells the reader's other devices about a read, and the partner only when they would see it", function (bool $readerShares, bool $partnerShares, RemoteStatus $readerStatus, bool $partnerHears): void {
    $sender = User::factory()->withRemotes()->create(['pull_receipts' => $partnerShares]);
    $recipient = User::factory()->withRemotes()->create(['pull_receipts' => $readerShares]);
    $message = sendChatMessage($sender, $recipient);
    ConversationParticipant::query()->where('user_id', $recipient->id)->update(['status' => $readerStatus]);
    $conversation = Conversation::between($sender, $recipient);
    assert($conversation instanceof Conversation);
    Event::fake([UsersUpdated::class]);

    resolve(UpdateConversationReadThrough::class)->handle($recipient, $conversation, $message->id);
    resolve(UpdateConversationReadThrough::class)->handle($recipient, $conversation, $message->id);

    expect(recipientsOf(usersUpdated(UserUpdate::Inbox)))->toBe([$recipient->id])
        ->and(usersUpdated(UserUpdate::Inbox))->toHaveCount(1)
        ->and(recipientsOf(usersUpdated(UserUpdate::Conversation)))->toBe($partnerHears ? [$sender->id] : []);
})->with([
    'both share reads' => [true, true, RemoteStatus::Open, true],
    'the reader keeps reads private' => [false, true, RemoteStatus::Open, false],
    'the partner keeps reads private' => [true, false, RemoteStatus::Open, false],
    'the reader has not merged the request' => [true, true, RemoteStatus::Fetched, false],
]);

it("leaves the tab that read out of telling the reader's devices, which only the others need", function (): void {
    $sender = User::factory()->withRemotes()->create();
    $recipient = User::factory()->withRemotes()->create();
    $message = sendChatMessage($sender, $recipient);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($recipient)
        ->withHeader('X-Socket-ID', '123.456')
        ->putJson(route('conversation-read.update', $sender), ['through' => $message->id])
        ->assertNoContent();

    expect(usersUpdated(UserUpdate::Inbox)[0]->socket)->toBe('123.456')
        ->and(usersUpdated(UserUpdate::Conversation)[0]->socket)->toBeNull();
});

it('tells only the org members who share reads with the reader about a read', function (): void {
    $maintainer = User::factory()->withRemotes()->create();
    [$sharing, $private] = followersOf($maintainer, 2);
    $private->update(['pull_receipts' => false]);
    $org = Conversation::query()->findOrFail(createOrg($maintainer, [$sharing, $private]));
    $message = pushToOrg($maintainer, $org->id);
    Event::fake([UsersUpdated::class]);

    resolve(UpdateConversationReadThrough::class)->handle($sharing, $org->refresh(), $message->id);

    expect(recipientsOf(usersUpdated(UserUpdate::Conversation)))->toBe([$maintainer->id]);
});

it('tells someone removed from an org to let go of it, and only the remaining members hear what comes next', function (): void {
    $maintainer = User::factory()->withRemotes()->create();
    [$stays, $leaves] = followersOf($maintainer, 2);
    $org = createOrg($maintainer, [$stays, $leaves]);

    $this->actingAs($maintainer)->deleteJson(route('org-member.destroy', ['org' => $org, 'member' => $leaves->handle]))->assertSuccessful();
    Event::fake([UsersUpdated::class]);
    pushToOrg($maintainer, $org);

    expect(recipientsOf(usersUpdated(UserUpdate::Messages)))->toEqualCanonicalizing([$maintainer->id, $stays->id]);
});

it('tells the callee a call rings, and each org member only that a signal waits for them', function (): void {
    $caller = User::factory()->withRemotes()->create();
    $callee = User::factory()->withRemotes()->create();
    sendChatMessage($caller, $callee);

    resolve(CreateConversationCall::class)->handle($caller, $callee, 'v=0 secret offer');

    $calls = usersUpdated(UserUpdate::Calls);
    expect(recipientsOf($calls))->toEqualCanonicalizing([$caller->id, $callee->id])
        ->and(json_encode($calls[0]->broadcastWith()))->not->toContain('offer');

    [$member] = followersOf($caller, 1);
    $org = Conversation::query()->findOrFail(createOrg($caller, [$member]));
    $this->actingAs($caller)->postJson(route('org-call.store', $org))->assertSuccessful();
    Event::fake([UsersUpdated::class]);

    resolve(UpdateOrgCall::class)->handle($caller, $org, [$member->id => ['type' => 'offer', 'sdp' => 'v=0 relay addresses']]);

    $signals = usersUpdated(UserUpdate::CallSignals);
    expect($signals)->toHaveCount(1)
        ->and($signals[0]->userIds)->toBe([$member->id])
        ->and($signals[0]->broadcastWith())->toBe(['conversation' => $org->id]);
});

it('announces an approved reply on the commit, the threads it shows up in and the logs', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $parent = Commit::factory()->create();
    $quoted = Commit::factory()->create();

    $reply = resolve(CreateCommit::class)->handle($admin, 'An approved reply.', $parent, $quoted);

    $visibility = commitsUpdated(CommitUpdate::Visibility);
    expect($visibility)->toHaveCount(1)
        ->and($visibility[0]->broadcastWith())->toBe(['ids' => [$reply->id]])
        ->and(collect($visibility[0]->broadcastOn())->map->name->all())->toEqualCanonicalizing(['commits.'.$reply->id, 'commits.'.$parent->id, 'commits.'.$quoted->id, 'log']);
});

it('keeps a pending commit off public channels until it is approved, however old it is', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $author = User::factory()->create();
    $parent = Commit::factory()->create();

    $pending = resolve(CreateCommit::class)->handle($author, 'Waiting for review.', $parent);

    expect($pending->status)->toBe(CommitStatus::Pending)
        ->and(commitsUpdated(CommitUpdate::Visibility))->toBe([])
        ->and(recipientsOf(usersUpdated(UserUpdate::Moderation)))->toBe([$admin->id])
        ->and(usersUpdated(UserUpdate::Moderation)[0]->broadcastWith())->toBe(['commits' => [$pending->id]])
        ->and(recipientsOf(usersUpdated(UserUpdate::Commits)))->toBe([$author->id]);

    $this->travel(1)->day();
    Commit::factory()->create();
    $this->actingAs($admin)->post(route('commit-approval.store', $pending))->assertRedirect();

    $approved = commitsUpdated(CommitUpdate::Visibility);
    expect($approved)->toHaveCount(1)
        ->and(collect($approved[0]->broadcastOn())->map->name->all())->toEqualCanonicalizing(['commits.'.$pending->id, 'commits.'.$parent->id, 'log']);
});

it('announces a bulk approval once, and a rejection of something never published nowhere public', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $commits = Commit::factory()->count(3)->pending()->create();
    $rejected = Commit::factory()->pending()->create();

    $this->actingAs($admin)->put(route('moderation.update'), ['commits' => $commits->modelKeys()])->assertRedirect();
    $this->actingAs($admin)->post(route('commit-rejection.store', $rejected), ['reason' => 'spam'])->assertRedirect();

    $visibility = commitsUpdated(CommitUpdate::Visibility);
    expect($visibility)->toHaveCount(1)
        ->and($visibility[0]->commitIds)->toEqualCanonicalizing($commits->modelKeys())
        ->and(usersUpdated(UserUpdate::Moderation))->toHaveCount(2);
});

it('announces deleting and restoring a published commit, so other devices bring it back too', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('commit.destroy', $commit))->assertRedirect();
    $this->actingAs($user)->post(route('commit-restoration.store', $commit))->assertRedirect();

    expect(commitsUpdated(CommitUpdate::Visibility))->toHaveCount(2)
        ->and(recipientsOf(usersUpdated(UserUpdate::Commits)))->toBe([$user->id]);
});

it('announces engagement on published commits only', function (): void {
    $user = User::factory()->create();
    $published = Commit::factory()->create();
    $pending = Commit::factory()->for($user)->pending()->create();

    $this->actingAs($user)->post(route('commit-star.store', $published))->assertSuccessful();
    resolve(CreateCommitStar::class)->handle($user, $pending);
    $this->actingAs($user)->delete(route('commit-star.destroy', $published))->assertSuccessful();

    $engagement = commitsUpdated(CommitUpdate::Engagement);
    expect($engagement)->toHaveCount(2)
        ->and(collect($engagement)->flatMap->commitIds->unique()->all())->toBe([$published->id]);
});

it('tells every commit and message showing a link that it stopped waiting for it, when it is read and when it fails', function (): void {
    $author = User::factory()->create();
    $sender = User::factory()->withRemotes()->create();
    $recipient = User::factory()->withRemotes()->create();
    $preview = LinkPreview::query()->create(['url_hash' => hash('sha256', 'https://example.com'), 'url' => 'https://example.com']);
    $published = Commit::factory()->create(['link_preview_id' => $preview->id]);
    Commit::factory()->for($author)->pending()->create(['link_preview_id' => $preview->id]);
    $message = sendChatMessage($sender, $recipient);
    $message->update(['link_preview_id' => $preview->id]);
    $this->mock(LinkPreviewProvider::class)->shouldReceive('metadata')->andReturn(['title' => 'Example']);

    resolve(UpdateLinkPreview::class)->handle($preview);
    new UnfurlLink($preview)->failed();

    expect(commitsUpdated(CommitUpdate::Preview))->toHaveCount(2)
        ->and(commitsUpdated(CommitUpdate::Preview)[0]->commitIds)->toBe([$published->id])
        ->and(recipientsOf(usersUpdated(UserUpdate::Commits)))->toBe([$author->id])
        ->and(recipientsOf(usersUpdated(UserUpdate::Conversation)))->toEqualCanonicalizing([$sender->id, $recipient->id]);
});

it('announces a poll vote without its results', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $poll = $commit->poll()->create(['duration' => 1440, 'opened_at' => now()]);
    $option = $poll->options()->create(['label' => 'Yes', 'position' => 0]);

    $this->actingAs($user)->post(route('commit-poll-vote.store', $commit), ['option' => $option->id]);

    expect(commitsUpdated(CommitUpdate::Poll))->toHaveCount(1)
        ->and(commitsUpdated(CommitUpdate::Poll)[0]->broadcastWith())->toBe(['ids' => [$commit->id]]);
});

it('tells the followed whose notifications changed when someone follows and unfollows, and both sides of a block', function (): void {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    $this->actingAs($follower)->post(route('user-follow.store', $followed))->assertRedirect();
    $this->actingAs($follower)->delete(route('user-follow.destroy', $followed))->assertRedirect();

    expect(usersUpdated(UserUpdate::Notifications))->toHaveCount(2)
        ->and(recipientsOf(usersUpdated(UserUpdate::Notifications)))->toBe([$followed->id]);

    resolve(CreateUserBlock::class)->handle($follower, $followed);

    expect(collect(usersUpdated(UserUpdate::Inbox))->last()->userIds)->toEqualCanonicalizing([$follower->id, $followed->id]);
});

it('announces a scheduled commit submitted by the scheduler like any other', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $scheduled = ScheduledCommit::query()->create(['user_id' => $admin->id, 'body' => 'Scheduled hello.', 'scheduled_at' => now()->subMinute()]);

    resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id);

    expect(commitsUpdated(CommitUpdate::Visibility))->toHaveCount(1)
        ->and(recipientsOf(usersUpdated(UserUpdate::ScheduledCommits)))->toBe([$admin->id]);
});

it('announces a pin on the profile it shows on', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();

    $this->actingAs($user)->put(route('commit-pin.update', $commit))->assertRedirect();

    Event::assertDispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => $event->profileIds === [$user->id]);
});

it('announces everything an erased account showed, and tells the people it was talking to', function (): void {
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    $commits = Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->pending()->create();
    sendChatMessage($partner, $user);

    resolve(DeleteUser::class)->handle($user);

    expect(collect(commitsUpdated(CommitUpdate::Visibility))->flatMap->commitIds->all())->toEqualCanonicalizing($commits->modelKeys())
        ->and(recipientsOf(usersUpdated(UserUpdate::Inbox)))->toBe([$partner->id]);
});

it('sends a wide audience through the queue instead of holding up the request', function (): void {
    $few = new UsersUpdated(User::factory()->count(2)->create()->modelKeys(), UserUpdate::Live);
    $many = new UsersUpdated(array_map(fn (): string => Str::uuid()->toString(), range(1, 101)), UserUpdate::Live);

    expect($few->shouldBroadcastNow())->toBeTrue()
        ->and($many->shouldBroadcastNow())->toBeFalse()
        ->and(new UsersUpdated([], UserUpdate::Live)->broadcastWhen())->toBeFalse();
});

it("refreshes the shared counts from the signed-in user's private channel, and gives guests none", function (): void {
    $user = User::factory()->withRemotes()->create();
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request())];

    expect($this->get('/terms', $headers)->json('liveProps'))->toBeNull();

    $live = $this->actingAs($user)->get('/settings/appearance', $headers)->json('liveProps');

    expect($live['auth.unreadNotificationsCount']['listeners'])->toBe([[
        'channel' => ['name' => 'users.'.$user->id, 'type' => 'private'],
        'events' => ['notifications'],
    ]])
        ->and($live['auth.unreadMessagesCount']['listeners'][0]['events'])->toBe(['remotes.messages', 'remotes.inbox']);
});

it('shares the language the reader reads translations in, live on their own channel, so a saved change shows everywhere', function (): void {
    $user = User::factory()->create();
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request())];

    $page = $this->actingAs($user)->get('/settings/appearance', [...$headers, 'Accept-Language' => 'pt-BR,pt;q=0.9'])->json();

    expect($page['props']['auth']['translationLanguage'])->toBe('pt')
        ->and($page['liveProps']['auth.translationLanguage']['listeners'][0])->toBe([
            'channel' => ['name' => 'users.'.$user->id, 'type' => 'private'],
            'events' => ['account'],
        ]);

    resolve(App\Actions\UpdateUserTranslationLanguage::class)->handle($user, 'de');

    expect($this->get('/settings/appearance', [...$headers, 'Accept-Language' => 'pt-BR'])->json('props.auth.translationLanguage'))->toBe('de');
});

it("tells the recipient's profile a pastel arrived, without its total, which each reader reads again as they may", function (): void {
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $recipient = User::factory()->create();
    $commit = Commit::factory()->for($recipient)->create();

    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit))->assertOk();

    Event::assertDispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => $event->profileIds === [$recipient->id]
        && $event->broadcastWith() === []);

    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request())];
    $page = $this->get(route('user.show', $recipient), $headers)->json();

    expect($page['props']['pasteisReceived'])->toBe(1)
        ->and($page['liveProps']['pasteisReceived']['listeners'][0])->toBe([
            'channel' => ['name' => 'profiles.'.$recipient->id, 'type' => 'public'],
            'events' => ['profile'],
        ]);
});

/**
 * Listens for real, so what each update makes a page read again is what it finds.
 *
 * @return Closure(): list<mixed>
 */
function onUpdate(UserUpdate $update, Closure $read): Closure
{
    $seen = [];
    Event::listen(UsersUpdated::class, function (UsersUpdated $event) use ($update, $read, &$seen): void {
        if ($event->update === $update) {
            $seen[] = $read();
        }
    });

    return function () use (&$seen): array {
        return $seen;
    };
}

it('announces a scheduled commit, a device and a timeout only once they are stored', function (): void {
    Event::fake([CommitsUpdated::class, ProfilesUpdated::class]);
    $user = User::factory()->create();
    $scheduled = onUpdate(UserUpdate::ScheduledCommits, fn (): int => ScheduledCommit::query()->where('user_id', $user->id)->count());
    $account = onUpdate(UserUpdate::Account, fn (): array => [$user->devices()->count(), $user->timeouts()->count()]);

    resolve(CreateScheduledCommit::class)->handle($user, 'Later.', now()->addHour()->toImmutable());
    resolve(CreateDevice::class)->handle(new AuthorizationCode('authorization-code:test', $user->id, 'SHA256:fingerprint', 'Phone', 'challenge'));
    resolve(CreateUserTimeout::class)->handle($user, ModerationReason::Spam, TimeoutDuration::cases()[0]);

    expect($scheduled())->toBe([1])
        ->and($account())->toBe([[1, 0], [1, 1]]);
});

it('announces nothing when storing a scheduled commit fails', function (): void {
    Event::fake([UsersUpdated::class]);
    $user = User::factory()->create();

    expect(fn () => resolve(CreateScheduledCommit::class)->handle($user, 'Later.', now()->addHour()->toImmutable(), [UploadedFile::fake()->create('broken.jpg', 4)]))
        ->toThrow(ValidationException::class);

    Event::assertNotDispatched(UsersUpdated::class);
    expect(ScheduledCommit::query()->count())->toBe(0);
});

it('tells both people about a follow, the follower that their log changed, and their profiles that connections changed', function (): void {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    resolve(CreateUserFollow::class)->handle($follower, $followed);

    expect(recipientsOf(usersUpdated(UserUpdate::Account)))->toEqualCanonicalizing([$follower->id, $followed->id])
        ->and(recipientsOf(usersUpdated(UserUpdate::Relationships)))->toBe([$follower->id]);
    Event::assertDispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => $event->profileIds === [$follower->id, $followed->id]
        && $event->broadcastWith() === ['connections' => true]);
});

it('tells whoever shows the person that their stream started or ended, with the stream their profile shows', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-live.update'), ['url' => 'https://www.twitch.tv/octocat', 'hours' => 2]);
    $this->actingAs($user)->delete(route('user-live.destroy'));

    $live = Event::dispatched(ProfilesUpdated::class)->map(fn (array $event): array => $event[0]->broadcastWith())->values()->all();
    expect($live)->toBe([
        ['live' => ['url' => 'https://www.twitch.tv/octocat', 'thumbnail_url' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_octocat-1280x720.jpg', 'ends_at' => now()->addHours(2)->startOfSecond()->toJSON()]],
        ['live' => null],
    ]);
});

it('tells everyone showing an organization when its color, followers or admins change, and its members when it goes', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $follower = User::factory()->create();

    resolve(UpdateOrganizationLane::class)->handle($organization, 3, null);
    resolve(CreateOrganizationFollow::class)->handle($follower, $organization);
    resolve(CreateOrganizationMember::class)->handle($organization, $member);
    resolve(DeleteOrganization::class)->handle($organization);

    expect(Event::dispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => in_array($organization->id, $event->profileIds, true)))->toHaveCount(4)
        ->and(recipientsOf(usersUpdated(UserUpdate::Relationships)))->toBe([$follower->id]);
});

it("tells the user's other pages when the log they read, the terms they accepted or a feature changes", function (): void {
    Event::fake([UsersUpdated::class, FeaturesUpdated::class]);
    $user = User::factory()->create();

    resolve(UpdateUserLog::class)->handle($user, Log::Latest);
    resolve(UpdateUserTermsAcceptance::class)->handle($user);
    resolve(UpdateFeature::class)->handle(Feature::OrganizationCommits, true);

    expect(usersUpdated(UserUpdate::Account))->toHaveCount(2);
    Event::assertDispatched(FeaturesUpdated::class);
});

it("refreshes an organization page from its public channel and the viewer's relationship to it from theirs", function (): void {
    $viewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request())];

    $live = $this->actingAs($viewer)->get(route('user.show', $organization->handle), $headers)->json('liveProps');

    expect($live['followersCount']['listeners'][0]['channel'])->toBe(['name' => 'profiles.'.$organization->id, 'type' => 'public'])
        ->and($live['isFollowing']['listeners'][0]['channel'])->toBe(['name' => 'users.'.$viewer->id, 'type' => 'private'])
        ->and($live['auth.user']['listeners'][0]['events'])->toBe(['account']);
});
