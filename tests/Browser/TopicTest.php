<?php

declare(strict_types=1);

use App\Models\User;

it('groups a new post into the topic of its hashtag', function (): void {
    $this->actingAs(User::factory()->create());

    $page = visit('/timelines/everyone');

    $page->fill('body', 'Shipping a new release #Laravel')
        ->press('Post')
        ->assertSee('Shipping a new release')
        ->click('[data-test="topic-link"]')
        ->assertPathIs('/topics/laravel')
        ->assertSee('#laravel')
        ->assertSee('1 Post')
        ->assertSee('Shipping a new release')
        ->assertNoJavaScriptErrors();
});
