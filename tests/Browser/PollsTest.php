<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Enums\PollDuration;
use App\Models\Poll;
use App\Models\User;

it('attaches a poll to a new commit', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit(route('log.show', Log::Following))
        ->type('#body', 'Tabs or spaces?')
        ->click('[data-test="add-poll-button"]')
        ->type('#poll-option-0', 'Tabs')
        ->type('#poll-option-1', 'Spaces')
        ->click('[data-test="add-poll-option-button"]')
        ->type('#poll-option-2', 'Whatever the formatter says')
        ->select('#poll-duration', (string) PollDuration::ThreeDays->value)
        ->click('[data-test="create-commit-button"]')
        ->assertSeeIn('[data-test="commit-poll"]', 'Whatever the formatter says')
        ->assertSeeIn('[data-test="commit-poll"]', 'Voting opens once approved')
        ->assertMissing('[data-test="poll-editor"]')
        ->assertNoJavaScriptErrors();

    $poll = $user->commits()->sole()->poll;

    expect($poll->duration)->toBe(PollDuration::ThreeDays)
        ->and($poll->options->pluck('label')->all())->toBe(['Tabs', 'Spaces', 'Whatever the formatter says']);
});

it('reveals the results once the viewer votes', function (): void {
    $user = User::factory()->create();
    Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    $this->actingAs($user);

    visit(route('log.show', Log::Everyone))
        ->assertDontSeeIn('[data-test="commit-poll"]', '%')
        ->click('Spaces')
        ->click('[data-test="poll-vote-button"]')
        ->assertSeeIn('[data-test="commit-poll"]', '100%')
        ->assertSeeIn('[data-test="poll-summary"]', '1 vote')
        ->assertMissing('[data-test="poll-vote-button"]')
        ->assertNoJavaScriptErrors();

    expect($user->pollVotes()->sole()->option->label)->toBe('Spaces');
});
