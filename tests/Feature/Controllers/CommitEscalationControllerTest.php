<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

it('escalates a commit to every admin as a moderator', function (): void {
    config(['app.admins' => ['admin', 'other-admin']]);
    config(['app.moderators' => ['maude']]);

    $admin = User::factory()->create(['handle' => 'admin']);
    $otherAdmin = User::factory()->create(['handle' => 'other-admin']);
    $moderator = User::factory()->create(['handle' => 'maude']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($moderator)
        ->post(route('commit-escalation.store', $commit));

    $response->assertRedirect();

    expect($commit->refresh()->escalated_at)->not->toBeNull()
        ->and($commit->refresh()->status)->toBe(CommitStatus::Pending);

    $notifications = Notification::query()
        ->where('type', NotificationType::Escalated)
        ->where('commit_id', $commit->id)
        ->get();

    expect($notifications->pluck('recipient_id')->sort()->values()->all())
        ->toBe(collect([$admin->id, $otherAdmin->id])->sort()->values()->all())
        ->and($notifications->pluck('actor_id')->unique()->all())->toBe([$moderator->id]);
});

it('keeps a single escalation per commit', function (): void {
    config(['app.admins' => ['admin']]);
    config(['app.moderators' => ['maude', 'mira']]);

    $admin = User::factory()->create(['handle' => 'admin']);
    User::factory()->create(['handle' => 'maude']);
    User::factory()->create(['handle' => 'mira']);
    $commit = Commit::factory()->pending()->create();

    $this->actingAs(User::query()->whereRelation('profile', 'handle', 'maude')->sole())
        ->post(route('commit-escalation.store', $commit))
        ->assertRedirect();

    $this->actingAs(User::query()->whereRelation('profile', 'handle', 'mira')->sole())
        ->post(route('commit-escalation.store', $commit))
        ->assertRedirect();

    expect(Notification::query()->where('type', NotificationType::Escalated)->count())->toBe(1)
        ->and(Notification::query()->sole()->recipient_id)->toBe($admin->id);
});

it('may not escalate a commit as a non moderator', function (): void {
    config(['app.admins' => ['admin']]);
    config(['app.moderators' => ['maude']]);

    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->post(route('commit-escalation.store', $commit));

    $response->assertForbidden();

    expect($commit->refresh()->escalated_at)->toBeNull()
        ->and(Notification::query()->where('type', NotificationType::Escalated)->count())->toBe(0);
});

it('undoes an escalation as a moderator', function (): void {
    config(['app.admins' => ['admin']]);
    config(['app.moderators' => ['maude']]);

    User::factory()->create(['handle' => 'admin']);
    $moderator = User::factory()->create(['handle' => 'maude']);
    $commit = Commit::factory()->pending()->create();

    $this->actingAs($moderator)
        ->post(route('commit-escalation.store', $commit))
        ->assertRedirect();

    expect($commit->refresh()->escalated_at)->not->toBeNull()
        ->and(Notification::query()->where('type', NotificationType::Escalated)->count())->toBe(1);

    $this->actingAs($moderator)
        ->delete(route('commit-escalation.destroy', $commit))
        ->assertRedirect();

    expect($commit->refresh()->escalated_at)->toBeNull()
        ->and(Notification::query()->where('type', NotificationType::Escalated)->count())->toBe(0);
});

it('undoes an escalation as an admin', function (): void {
    config(['app.admins' => ['admin']]);
    config(['app.moderators' => ['maude']]);

    $admin = User::factory()->create(['handle' => 'admin']);
    $moderator = User::factory()->create(['handle' => 'maude']);
    $commit = Commit::factory()->pending()->create();

    $this->actingAs($moderator)
        ->post(route('commit-escalation.store', $commit))
        ->assertRedirect();

    $this->actingAs($admin)
        ->delete(route('commit-escalation.destroy', $commit))
        ->assertRedirect();

    expect($commit->refresh()->escalated_at)->toBeNull()
        ->and(Notification::query()->where('type', NotificationType::Escalated)->count())->toBe(0);
});

it('may not undo an escalation as a non moderator', function (): void {
    config(['app.admins' => ['admin']]);
    config(['app.moderators' => ['maude']]);

    User::factory()->create(['handle' => 'admin']);
    $moderator = User::factory()->create(['handle' => 'maude']);
    $commit = Commit::factory()->pending()->create();

    $this->actingAs($moderator)
        ->post(route('commit-escalation.store', $commit))
        ->assertRedirect();

    $this->actingAs($commit->user)
        ->delete(route('commit-escalation.destroy', $commit))
        ->assertForbidden();

    expect($commit->refresh()->escalated_at)->not->toBeNull()
        ->and(Notification::query()->where('type', NotificationType::Escalated)->count())->toBe(1);
});
