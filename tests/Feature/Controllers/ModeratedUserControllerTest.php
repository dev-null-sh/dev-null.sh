<?php

declare(strict_types=1);

use App\Enums\ModerationReason;
use App\Models\Ban;
use App\Models\User;
use App\Models\UserTimeout;

it('lists everyone in timeout, the soonest out first, and every ban, the newest first', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $soon = User::factory()->create(['handle' => 'soon']);
    $later = User::factory()->create(['handle' => 'later']);
    UserTimeout::factory()->for($soon)->create(['reason' => ModerationReason::LowEffort, 'ends_at' => now()->addHour()]);
    UserTimeout::factory()->for($soon)->ended()->create();
    UserTimeout::factory()->for($later)->create(['ends_at' => now()->addHour()]);
    UserTimeout::factory()->for($later)->create(['ends_at' => now()->addDays(3)]);
    UserTimeout::factory()->ended()->create();
    UserTimeout::factory()->create(['github_id' => '999999999']);
    $banned = User::factory()->banned(ModerationReason::AiGenerated)->create(['handle' => 'banned']);
    $this->travel(1)->minute();
    Ban::factory()->create(['github_id' => '583231', 'reason' => ModerationReason::Spam]);

    config(['app.admins' => ['admin']]);

    $this->actingAs($admin)
        ->get(route('moderated-user.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/people')
            ->has('timeouts', 2)
            ->where('timeouts.0.user.handle', 'soon')
            ->where('timeouts.0.reason', 'Low-effort posts')
            ->where('timeouts.0.timeouts_count', 2)
            ->where('timeouts.1.user.handle', 'later')
            ->where('timeouts.1.ends_at', now()->subMinute()->addDays(3)->startOfSecond()->toISOString())
            ->has('bans', 2)
            ->where('bans.0.user', null)
            ->where('bans.0.github_id', '583231')
            ->where('bans.0.reason', 'Spam or self-promotion')
            ->where('bans.1.user.handle', 'banned')
            ->where('bans.1.reason', 'AI-generated posts'));
});

it('is only for admins', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('moderated-user.index'))
        ->assertForbidden();
});

it('lifts a ban from the list', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $user = User::factory()->banned()->create(['handle' => 'octocat']);

    config(['app.admins' => ['admin']]);

    $this->actingAs($admin)
        ->fromRoute('moderated-user.index')
        ->delete(route('ban.destroy', $user->ban))
        ->assertRedirectToRoute('moderated-user.index')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unbanned @octocat.']);

    expect($user->refresh()->isBanned())->toBeFalse()
        ->and(Ban::query()->count())->toBe(0);
});

it('lifts the ban on a deleted account, so it can sign up again', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $ban = Ban::factory()->create();

    config(['app.admins' => ['admin']]);

    $this->actingAs($admin)
        ->delete(route('ban.destroy', $ban))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Lifted the ban. They can sign up again.']);

    expect(Ban::query()->count())->toBe(0);
});

it('may not lift a ban unless an admin', function (): void {
    $ban = Ban::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('ban.destroy', $ban))
        ->assertForbidden();

    expect(Ban::query()->count())->toBe(1);
});

it('sends guests to sign in instead of letting them moderate anyone', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);
    $ban = Ban::factory()->create();

    $this->get(route('moderated-user.index'))->assertRedirectToRoute('home');
    $this->post(route('user-timeout.store', $user), ['reason' => 'spam', 'duration' => 60])->assertRedirectToRoute('home');
    $this->delete(route('user-timeout.destroy', $user))->assertRedirectToRoute('home');
    $this->post(route('user-ban.store', $user), ['reason' => 'spam'])->assertRedirectToRoute('home');
    $this->delete(route('user-ban.destroy', $user))->assertRedirectToRoute('home');
    $this->delete(route('ban.destroy', $ban))->assertRedirectToRoute('home');

    expect($user->timeouts()->count())->toBe(0)
        ->and($user->refresh()->isBanned())->toBeFalse()
        ->and(Ban::query()->count())->toBe(1);
});
