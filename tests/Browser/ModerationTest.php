<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may approve the selected commits', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $first = Commit::factory()->pending()->create(['created_at' => now()->subMinutes(3)]);
    $second = Commit::factory()->pending()->create(['created_at' => now()->subMinutes(2)]);
    $third = Commit::factory()->pending()->create(['created_at' => now()->subMinute()]);

    $this->actingAs($admin);

    $page = visit(route('moderation.show'))
        ->assertSee('Select all')
        ->assertDisabled('[data-test="approve-selected-commits-button"]');

    $page->click(':nth-match([data-test="select-commit-checkbox"], 1)')
        ->assertSee('1 selected')
        ->click('Approve 1 commit')
        ->assertSee('Select all')
        ->assertNoJavaScriptErrors();

    expect($first->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($second->refresh()->status)->toBe(CommitStatus::Pending)
        ->and($third->refresh()->status)->toBe(CommitStatus::Pending);

    $page->click('[data-test="select-all-commits-checkbox"]')
        ->assertSee('2 selected')
        ->click('Approve 2 commits')
        ->assertSee('No commits are waiting for review.')
        ->assertNoJavaScriptErrors();

    expect(Commit::query()->pending()->count())->toBe(0);
});
