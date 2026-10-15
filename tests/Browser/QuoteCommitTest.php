<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('quotes a commit from the log and embeds it under the new commit', function (): void {
    $user = User::factory()->create();
    $quoted = Commit::factory()->for(User::factory()->state(['username' => 'octocat']))->create(['body' => 'Tabs are a moral choice.']);

    $this->actingAs($user);

    visit(route('log.show', Log::Everyone))
        ->click('[data-test="quote-button"]')
        ->assertVisible('[data-test="quote-composer"]')
        ->assertSeeIn('[data-test="quote-composer"]', 'Quote @octocat')
        ->assertSeeIn('[data-test="quote-composer"]', 'Tabs are a moral choice.')
        ->type('[data-test="quote-composer"] textarea', 'Spaces, and I will die on this hill.')
        ->click('[data-test="submit-quote-button"]')
        ->assertMissing('[data-test="quote-composer"]')
        ->assertSee('Spaces, and I will die on this hill.')
        ->click('[data-test="commit"]:has-text("Spaces, and I will die on this hill.") [data-test="quoted-commit"]')
        ->assertPathIs("/@octocat/commits/{$quoted->id}")
        ->assertNoJavaScriptErrors();

    expect($user->commits()->sole()->quoted_commit_id)->toBe($quoted->id);
});

it('opens the quote composer for the focused commit with q', function (): void {
    $user = User::factory()->create();
    $quoted = Commit::factory()->create(['body' => 'Ship small, ship often.']);

    $this->actingAs($user);

    visit(route('log.show', Log::Everyone))
        ->keys('[data-test="commit"]', 'q')
        ->assertVisible('[data-test="quote-composer"]')
        ->assertScript('document.activeElement.tagName', 'TEXTAREA')
        ->type('[data-test="quote-composer"] textarea', 'Agreed, and ship it on a Friday.')
        ->keys('[data-test="quote-composer"] textarea', 'ControlOrMeta+Enter')
        ->assertMissing('[data-test="quote-composer"]')
        ->assertSee('Agreed, and ship it on a Friday.')
        ->assertNoJavaScriptErrors();

    expect($user->commits()->sole()->quoted_commit_id)->toBe($quoted->id);
});
