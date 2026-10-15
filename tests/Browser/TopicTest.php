<?php

declare(strict_types=1);

use App\Ai\Agents\TopicExtractor;
use App\Models\Commit;
use App\Models\Topic;
use App\Models\User;

it('files a new commit under the topics Claude picks', function (): void {
    TopicExtractor::fake(fn (): array => ['topics' => ['laravel']]);
    Topic::factory()->hasAttached(Commit::factory()->count(Topic::MIN_COMMITS - 1))->create(['name' => 'laravel']);

    // Only approved commits get topics, and an admin's commits are approved right away.
    config(['app.admins' => ['octocat']]);
    $this->actingAs(User::factory()->create(['username' => 'octocat']));

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
