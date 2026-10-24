<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may approve a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('takes the unique lock of the level check of the author only once the approval is saved', function (): void {
    // On Postgres, a lock row left by an earlier check makes the insert fail and aborts the transaction it runs in.
    config(['cache.default' => 'database']);
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    $outside = DB::transactionLevel();
    $levels = [];
    DB::listen(function (QueryExecuted $query) use (&$levels): void {
        if (str_contains($query->sql, 'cache_locks')) {
            $levels[] = DB::transactionLevel();
        }
    });

    $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit))
        ->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($levels)->not->toBeEmpty()
        ->and(array_unique($levels))->toBe([$outside]);
});

it('may not approve a deleted commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    $commit->delete();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertNotFound();

    expect(Commit::withTrashed()->findOrFail($commit->id)->status)->toBe(CommitStatus::Pending);
});

it('may not approve a commit as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->post(route('commit-approval.store', $commit));

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});
