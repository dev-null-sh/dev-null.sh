<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin', 'other']]);
});

it('may claim a commit for five minutes', function (): void {
    $this->freezeSecond();

    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('commit-claim.update', $commit));

    $response->assertRedirectToRoute('moderation.show');

    $claim = CommitClaim::query()->sole();

    expect($claim->commit_id)->toBe($commit->id)
        ->and($claim->user_id)->toBe($admin->id)
        ->and($claim->expires_at->equalTo(now()->addMinutes(5)))->toBeTrue();
});

it('releases the previous claim of the admin', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $previous = Commit::factory()->pending()->create();
    $commit = Commit::factory()->pending()->create();

    $this->actingAs($admin)->put(route('commit-claim.update', $previous));
    $this->actingAs($admin)->put(route('commit-claim.update', $commit));

    expect(CommitClaim::query()->sole()->commit_id)->toBe($commit->id);
});

it('may not claim a commit another admin is reviewing', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $other = User::factory()->create(['username' => 'other']);
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->for($other)->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('commit-claim.update', $commit));

    $response->assertRedirectToRoute('moderation.show')
        ->assertSessionHasErrors(['commit' => '@other is reviewing this commit.']);

    expect(CommitClaim::query()->sole()->user_id)->toBe($other->id);
});

it('may take over a commit another admin is reviewing', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $other = User::factory()->create(['username' => 'other']);
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->for($other)->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('commit-claim.update', $commit), ['take_over' => true]);

    $response->assertRedirectToRoute('moderation.show')
        ->assertSessionHasNoErrors();

    expect(CommitClaim::query()->sole()->user_id)->toBe($admin->id);
});

it('may claim a commit once the claim of another admin expired', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->expired()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('commit-claim.update', $commit));

    $response->assertSessionHasNoErrors();

    expect(CommitClaim::query()->sole()->user_id)->toBe($admin->id);
});

it('may only claim pending commits', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->create();

    $response = $this->actingAs($admin)
        ->put(route('commit-claim.update', $commit));

    $response->assertNotFound();

    expect(CommitClaim::query()->count())->toBe(0);
});

it('may not claim commits as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs(User::factory()->create())
        ->put(route('commit-claim.update', $commit));

    $response->assertForbidden();

    expect(CommitClaim::query()->count())->toBe(0);
});
