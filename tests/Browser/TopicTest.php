<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Topic;
use App\Models\User;

it('files a new commit under the topics Claude picks', function (): void {
    fakeClaude(['topics' => ['laravel']]);
    Topic::factory()->hasAttached(Commit::factory()->count(Topic::MIN_COMMITS - 1))->create(['name' => 'laravel']);

    $this->actingAs(User::factory()->create());

    $page = visit('/log/everyone');

    $page->fill('title', 'Shipping a new queue driver for Laravel')
        ->click('[data-test="create-commit-button"]')
        ->assertSee('Shipping a new queue driver for Laravel')
        ->click('article:has-text("Shipping a new queue driver") [data-test="topic-link"]')
        ->assertPathIs('/topics/laravel')
        ->assertSee(Topic::MIN_COMMITS.' commits')
        ->assertSee('Shipping a new queue driver for Laravel')
        ->assertNoJavaScriptErrors();
});
