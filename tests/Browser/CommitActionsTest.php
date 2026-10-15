<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;

it('shows the star in the same row as reply and copy link', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $this->actingAs($user);

    visit(route('log.show', Log::Everyone))
        ->assertVisible('[data-test="star-commit-button"]')
        ->assertScript(<<<'JS'
            (() => {
                const row = document.querySelector('[data-test="reply-button"]').parentElement;

                return row.contains(document.querySelector('[data-test="star-commit-button"]'))
                    && row.contains(document.querySelector('[data-test="copy-link-button"]'));
            })()
            JS)
        ->click('[data-test="star-commit-button"]')
        ->assertSeeIn('[data-test="star-count"]', '1')
        ->assertNoJavaScriptErrors();

    expect($user->hasStarred($commit))->toBeTrue();
});

it('reports a published commit alongside the inline star actions', function (bool $permalink): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();

    $this->actingAs($viewer);

    visit($permalink ? route('commit.show', [$commit->user, $commit]) : route('log.show', Log::Everyone))
        ->assertScript(<<<'JS'
            (() => {
                const row = document.querySelector('[data-test="reply-button"]').parentElement;

                return row.querySelectorAll('[data-test="star-commit-button"]').length === 1
                    && row.querySelectorAll('[aria-label="Report commit"]').length === 1
                    && row.querySelectorAll('[data-test="bookmark-button"]').length === 1
                    && row.querySelectorAll('[data-test="copy-link-button"]').length === 1;
            })()
            JS)
        ->click('[data-test="star-commit-button"]')
        ->assertSeeIn('[data-test="star-count"]', '1')
        ->click('[data-test="bookmark-button"]')
        ->assertVisible('[aria-label="Unsave commit"]')
        ->click('[aria-label="Report commit"]')
        ->click('input[value="spam"]')
        ->click('Send report')
        ->assertNotPresent('[role="dialog"]')
        ->assertSee($commit->body)
        ->assertSeeIn('[data-test="star-count"]', '1')
        ->assertVisible('[aria-label="Unsave commit"]')
        ->assertNoJavaScriptErrors();

    expect(CommitReport::query()->sole())
        ->commit_id->toBe($commit->id)
        ->reporter_id->toBe($viewer->id)
        ->and($viewer->hasStarred($commit))->toBeTrue();
    expect(Bookmark::query()->sole())
        ->commit_id->toBe($commit->id)
        ->user_id->toBe($viewer->id);
})->with([false, true]);
