<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('puts an automatically moderated commit back in the queue', function (Closure $commit): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = $commit();
    $other = Notification::factory()->create(['type' => NotificationType::Replied, 'commit_id' => $commit->id]);
    Notification::factory()->create(['type' => $commit->status === CommitStatus::Approved ? NotificationType::Approved : NotificationType::Rejected, 'commit_id' => $commit->id]);

    $response = $this->actingAs($admin)
        ->fromRoute('auto-moderated-commit.index')
        ->post(route('commit-reopening.store', $commit));

    $response->assertRedirectToRoute('auto-moderated-commit.index');

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending)
        ->and(Notification::query()->pluck('id')->all())->toBe([$other->id]);
})->with([
    'approved' => [fn (): Commit => Commit::factory()->create()],
    'rejected' => [fn (): Commit => Commit::factory()->rejected()->create()],
]);

it('may not reopen commits as a non admin', function (): void {
    $commit = Commit::factory()->rejected()->create();

    $response = $this->actingAs($commit->user)->post(route('commit-reopening.store', $commit));

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected);
});

it('reopens every part of a series and removes its moderation notification', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $parts = commitSeries(status: CommitStatus::Rejected);
    Notification::factory()->create(['type' => NotificationType::Rejected, 'commit_id' => $parts[0]->id]);

    $this->actingAs($admin)
        ->fromRoute('auto-moderated-commit.index')
        ->post(route('commit-reopening.store', $parts[2]))
        ->assertRedirectToRoute('auto-moderated-commit.index');

    expect($parts->fresh()->pluck('status')->unique()->all())->toBe([CommitStatus::Pending])
        ->and(Notification::query()->count())->toBe(0);
});
