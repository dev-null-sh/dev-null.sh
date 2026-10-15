<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('composes and posts a series of commits', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $page = visit(route('log.show', Log::Following))
        ->type('#body', 'Three things I learned this week')
        ->click('[data-test="add-commit-part-button"]')
        ->type('#body-2', 'Measure queue age, not queue length')
        ->click('[data-test="add-commit-part-button"]')
        ->type('#body-3', 'Remove me')
        ->assertCount('[data-test="commit-composer-part"]', 3)
        ->assertSeeIn('[data-test="create-commit-button"]', 'Commit 3')
        ->click('[data-test="commit-composer-part"]:nth-child(3) [data-test="remove-commit-part-button"]')
        ->assertCount('[data-test="commit-composer-part"]', 2)
        ->click('[data-test="create-commit-button"]')
        ->assertSeeIn('[data-test="series-position"]', '1/2')
        ->assertSee('1 more commit in this series')
        ->assertValue('#body', '')
        ->assertNoJavaScriptErrors();

    expect($user->commits()->oldest('series_position')->pluck('body')->all())
        ->toBe(['Three things I learned this week', 'Measure queue age, not queue length']);

    $page->click('[data-test="series-continues"]')
        ->assertSee('Measure queue age, not queue length')
        ->assertCount('[data-test="series-position"]', 2)
        ->assertNoJavaScriptErrors();
});

it('keeps a draft series across visits', function (): void {
    $this->actingAs(User::factory()->create());

    visit(route('log.show', Log::Following))
        ->type('#body', 'First part of a draft')
        ->click('[data-test="add-commit-part-button"]')
        ->type('#body-2', 'Second part of a draft')
        ->wait(0.5)
        ->navigate(route('log.show', Log::Everyone))
        ->assertValue('#body', 'First part of a draft')
        ->assertValue('#body-2', 'Second part of a draft')
        ->assertVisible('[data-test="stash-label"]')
        ->assertNoJavaScriptErrors();

    expect(Commit::query()->count())->toBe(0);
});
