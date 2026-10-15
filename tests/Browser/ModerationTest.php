<?php

declare(strict_types=1);

use App\Actions\RejectCommit;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may add a private reason while rejecting a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    $this->actingAs($admin);

    visit(route('moderation.show'))
        ->assertDontSee('Reason (optional)')
        ->click('Add reason')
        ->fill('textarea[maxlength="280"]', 'Please remove the access token before sharing the log.')
        ->assertSee('Only the author will see this.')
        ->click('[data-test="reject-commit-button"]')
        ->assertSee('No commits are waiting for review.')
        ->assertNoJavaScriptErrors();

    expect($commit->fresh()->status)->toBe(CommitStatus::Rejected)
        ->and($commit->user->notifications()->sole()->reason)->toBe('Please remove the access token before sharing the log.');
});

it('lets authors open their rejected commit from a moderation update', function (): void {
    $commit = Commit::factory()->pending()->create(['body' => 'A deployment log from my local test app.']);
    resolve(RejectCommit::class)->handle($commit, 'Please add context for other developers.');
    $this->actingAs($commit->user);

    visit(route('notification.index'))
        ->assertSee('Your commit was rejected')
        ->assertSee('Please add context for other developers.')
        ->assertSee('Only you can see this update and your rejected commit.')
        ->click('a[href="'.route('commit.show', [$commit->user, $commit], absolute: false).'"]')
        ->assertPathIs('/@'.$commit->user->username.'/commits/'.$commit->id)
        ->assertSee($commit->body)
        ->assertSee('rejected')
        ->assertDontSee('Please add context for other developers.')
        ->assertNoJavaScriptErrors();
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
