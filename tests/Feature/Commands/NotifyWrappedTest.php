<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('notifies handles with a commit that year, once per year', function (): void {
    $year = (int) date('Y');

    $withCommit = User::factory()->create();
    Commit::factory()->create(['author_id' => $withCommit->id, 'created_at' => now()]);
    $withoutCommit = User::factory()->create();

    $this->artisan('wrapped:notify', ['--year' => (string) $year])->assertSuccessful();

    $this->assertDatabaseHas('notifications', [
        'recipient_id' => $withCommit->id,
        'actor_id' => $withCommit->id,
        'type' => 'wrapped_ready',
        'key' => 'wrapped:'.$year,
    ]);
    $this->assertDatabaseMissing('notifications', [
        'recipient_id' => $withoutCommit->id,
        'type' => 'wrapped_ready',
    ]);

    $this->artisan('wrapped:notify', ['--year' => (string) $year])->assertSuccessful();

    $this->assertDatabaseCount('notifications', 1);
});

it('rejects years other than the current year', function (): void {
    $user = User::factory()->create();
    Commit::factory()->create(['author_id' => $user->id, 'created_at' => now()->subYear()]);

    $this->artisan('wrapped:notify', ['--year' => (string) ((int) date('Y') - 1)])->assertFailed();

    $this->assertDatabaseCount('notifications', 0);
});
