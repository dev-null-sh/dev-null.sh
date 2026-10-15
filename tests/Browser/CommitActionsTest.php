<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
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
