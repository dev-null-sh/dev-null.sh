<?php

declare(strict_types=1);

use App\Enums\ModerationReason;
use App\Models\Commit;
use App\Models\User;
use App\Models\UserTimeout;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lists the accounts acting in bulk, with the thresholds and what moderators can do about each', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $poster = User::factory()->create(['username' => 'poster']);
    UserTimeout::factory()->for($poster)->create(['reason' => ModerationReason::Spam, 'ends_at' => now()->addHour()]);
    Commit::factory()->for($poster)->count(15)->create();
    Commit::factory()->for($admin)->count(15)->create();
    Commit::factory()->for(User::factory()->create())->count(14)->create();

    $this->actingAs($admin)
        ->get(route('bulk-activity.index', ['scale' => 'day']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/bulk')
            ->where('scale', 'day')
            ->has('activity.posts', 2)
            ->where('activity.posts.0.count', 15)
            ->where('activity.follows', [])
            ->where('thresholds.posts', ['perDay' => 15, 'total' => 15])
            ->where("moderation.{$poster->id}.timeoutsCount", 1)
            ->where("moderation.{$poster->id}.timeout.reason", 'Spam or self-promotion')
            ->missing("moderation.{$admin->id}"));
});

it('scales the thresholds with the window, the last 7 days by default', function (): void {
    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('bulk-activity.index'))
        ->assertInertia(fn ($page) => $page
            ->where('scale', 'week')
            ->where('thresholds.follows', ['perDay' => 50, 'total' => 350]));

    $this->get(route('bulk-activity.index', ['scale' => 'month']))
        ->assertInertia(fn ($page) => $page
            ->where('scale', 'month')
            ->where('thresholds.follows', ['perDay' => 50, 'total' => 1500]));
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
