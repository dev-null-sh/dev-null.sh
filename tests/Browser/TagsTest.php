<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\Tag;
use App\Models\User;

it('links tags but not issue references or url fragments', function (): void {
    $commit = Commit::factory()->create([
        'body' => 'Shipping #Laravel today, fixes #123. Docs: https://laravel.com/docs#queues',
    ]);
    Tag::factory()->create(['name' => 'laravel'])->commits()->attach($commit);

    $this->actingAs(User::factory()->create());

    visit(route('log.show', Log::Everyone))
        ->assertSee('fixes #123.')
        ->assertScript(<<<'JS'
            [...document.querySelectorAll('[data-test="commit"] p a')].map((link) => [link.textContent, link.getAttribute('href')])
            JS, [
            ['#Laravel', '/tags/laravel'],
            ['https://laravel.com/docs#queues', 'https://laravel.com/docs#queues'],
        ])
        ->assertNoJavaScriptErrors();
});

it('follows a tag from a commit and shows its commits in the log', function (): void {
    $user = User::factory()->create();
    $author = User::factory()->create(['username' => 'ferris']);
    $commit = Commit::factory()->for($author)->create(['body' => 'Borrow checker finally clicked #rust']);
    Tag::factory()->create(['name' => 'rust'])->commits()->attach($commit);

    $user->following()->attach(User::factory()->create());

    $this->actingAs($user);

    visit(route('log.show', Log::Following))
        ->assertDontSee('Borrow checker finally clicked')
        ->navigate(route('log.show', Log::Everyone))
        ->click('[data-test="commit"] a[href="/tags/rust"]')
        ->assertPathIs('/tags/rust')
        ->assertSee('Borrow checker finally clicked')
        ->click('[data-test="tag-follow-button"]')
        ->assertSee('Following')
        ->navigate(route('log.show', Log::Following))
        ->assertSee('Borrow checker finally clicked')
        ->assertNoJavaScriptErrors();

    expect($user->followedTags()->pluck('name')->all())->toBe(['rust']);
});
