<?php

declare(strict_types=1);

use App\Enums\ModerationReason;
use App\Models\Commit;
use App\Models\User;
use App\Models\UserTimeout;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lists the accounts acting in bulk in the last 7 days, with what moderators can do about each', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $poster = User::factory()->create(['username' => 'poster']);
    UserTimeout::factory()->for($poster)->create(['reason' => ModerationReason::Spam, 'ends_at' => now()->addHour()]);
    Commit::factory()->for($poster)->count(2)->create();
    Commit::factory()->for($admin)->create();
    Commit::factory()->for($poster)->create(['created_at' => now()->subDays(8)]);

    $this->actingAs($admin)
        ->get(route('bulk-activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/bulk')
            ->where('scale', 'week')
            ->has('activity.posts', 2)
            ->where('activity.posts.0.user.username', 'poster')
            ->where('activity.posts.0.count', 2)
            ->where('activity.posts.1.user.username', 'admin')
            ->where('activity.follows', [])
            ->where("moderation.{$poster->id}.timeoutsCount", 1)
            ->where("moderation.{$poster->id}.timeout.reason", 'Spam or self-promotion')
            ->missing("moderation.{$admin->id}"));
});

it('counts over the window asked for', function (): void {
    Commit::factory()->for(User::factory()->create())->create(['created_at' => now()->subDays(20)]);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('bulk-activity.index', ['scale' => 'month']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('scale', 'month')
            ->has('activity.posts', 1));
});

it('only counts over a day, a week or a month', function (): void {
    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('bulk-activity.index', ['scale' => 'year']))
        ->assertSessionHasErrors('scale');
});

it('is only for admins', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('bulk-activity.index'))
        ->assertForbidden();
});
