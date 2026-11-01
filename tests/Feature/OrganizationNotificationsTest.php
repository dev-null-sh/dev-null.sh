<?php

declare(strict_types=1);

use App\Actions\CreateCommitStar;
use App\Actions\CreateCommitTopics;
use App\Actions\CreateMentionNotifications;
use App\Actions\CreateQuoteNotifications;
use App\Actions\CreateReplyNotifications;
use App\Actions\DeleteCommitStar;
use App\Actions\DeleteOrganization;
use App\Actions\DeleteOrganizationMember;
use App\Actions\DeleteUser;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Enums\OrganizationRole;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;

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
    $this->member->muting()->attach($troll);
    $reply = Commit::factory()->for($this->commit, 'parent')->for($troll)->create();

    resolve(CreateReplyNotifications::class)->handle($reply);

    expect(Notification::query()->pluck('recipient_id')->all())->toBe([$this->admin->id]);
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

it("tells the poster of an organization's rejected commit, for the organization", function (): void {
    resolve(UpdateCommitStatuses::class)->handle([$this->commit->id], CommitStatus::Rejected);

    expect(heardBy(NotificationType::Rejected))->toBe([$this->admin->id => $this->organization->id]);
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
