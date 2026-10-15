<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    User::factory()->create(['username' => 'dev-a', 'name' => 'Developer Alpha']);
    User::factory()->create(['username' => 'dev-b', 'name' => 'Developer Beta']);
    $this->actingAs(User::factory()->create(['username' => 'viewer', 'name' => 'Viewer']));
});

it('completes mentions with keyboard navigation without submitting and keeps drafts', function (): void {
    $page = visit(route('log.show', Log::Everyone))
        ->fill('#body', 'Thanks @')
        ->assertSee('Type a name or @handle.')
        ->fill('#body', 'Thanks @dev')
        ->assertSee('Developer Alpha')
        ->assertSee('Developer Beta')
        ->keys('#body', 'ArrowDown')
        ->keys('#body', 'Enter')
        ->assertValue('#body', 'Thanks @dev-b ')
        ->assertMissing('[data-test="mention-popup"]')
        ->assertNoJavaScriptErrors();

    expect(Commit::query()->count())->toBe(0);

    $page->refresh()->assertValue('#body', 'Thanks @dev-b ')
        ->fill('#body', 'With @dev')
        ->assertSee('Developer Alpha')
        ->keys('#body', 'ArrowUp')
        ->keys('#body', 'Tab')
        ->assertValue('#body', 'With @dev-b ');
});

it('replaces the caret fragment and dismisses and reopens without losing the reply', function (): void {
    $parent = Commit::factory()->create();
    $page = visit(route('commit.show', [$parent->user, $parent]))
        ->click('[data-test="reply-button"]')
        ->fill('textarea[name="body"]', 'Before @dev after')
        ->keys('textarea[name="body"]', 'Home')
        ->keys('textarea[name="body"]', 'ArrowRight')
        ->assertMissing('[data-test="mention-popup"]');

    $page->keys('textarea[name="body"]', array_fill(0, 8, 'ArrowRight'));
    $page->assertSee('Developer Alpha')
        ->keys('textarea[name="body"]', 'Escape')
        ->assertMissing('[data-test="mention-popup"]')
        ->assertPresent('[data-test="reply-composer"]')
        ->keys('textarea[name="body"]', 'ArrowDown')
        ->assertSee('Developer Alpha')
        ->click('[role="option"]:has-text("Developer Alpha")')
        ->assertValue('textarea[name="body"]', 'Before @dev-a after')
        ->assertNoJavaScriptErrors();
});

it('does not activate inside email addresses or URLs and preserves submit shortcuts', function (): void {
    $page = visit(route('log.show', Log::Everyone));
    foreach (['me@dev.com', 'https://example.com/@dev', 'example.com/(@dev'] as $body) {
        $page->fill('#body', $body)->assertMissing('[data-test="mention-popup"]');
    }

    $page->fill('#body', 'Thanks @dev')
        ->assertSee('Developer Alpha')
        ->keys('#body', 'Control+Enter')
        ->assertValue('#body', '')
        ->assertNoJavaScriptErrors();

    expect(Commit::query()->sole()->body)->toBe('Thanks @dev');
});

it('ignores stale search responses and exposes loading empty and error states', function (): void {
    $alpha = User::query()->where('username', 'dev-a')->firstOrFail()->toArray();
    $beta = User::query()->where('username', 'dev-b')->firstOrFail()->toArray();
    $page = visit(route('log.show', Log::Everyone));
    $page->script('window.mentionUsers = '.json_encode([$alpha, $beta], JSON_THROW_ON_ERROR).';
        const original = window.fetch.bind(window);
        window.fetch = (input, options) => {
            if (!String(input).includes("/search/users")) return original(input, options);
            const query = new URL(input, location.href).searchParams.get("q");
            return new Promise(resolve => setTimeout(() => resolve(new Response(JSON.stringify({users: query === "slow" ? [window.mentionUsers[0]] : query === "fast" ? [window.mentionUsers[1]] : []}), {status: query === "error" ? 500 : 200, headers: {"Content-Type": "application/json"}})), query === "slow" ? 800 : 50));
        };');

    $page->fill('#body', '@slow')->assertSee('Searching…');
    $page->script('() => new Promise(resolve => setTimeout(resolve, 200))');
    $page->fill('#body', '@fast')->assertSee('Developer Beta');
    $page->script('() => new Promise(resolve => setTimeout(resolve, 900))');
    $page->assertDontSee('Developer Alpha')->assertSee('Developer Beta')
        ->fill('#body', '@missing')->assertSee("No developers match 'missing'.")
        ->fill('#body', '@error')->assertSee('Search unavailable. Keep typing to try again.')
        ->fill('#body', '@fast')->assertSee('Developer Beta')
        ->assertNoJavaScriptErrors();
});

it('does not select or submit while composing text with an IME', function (): void {
    $page = visit(route('log.show', Log::Everyone))
        ->fill('#body', 'Thanks @dev')->assertSee('Developer Alpha');
    $page->script('document.querySelector("#body").dispatchEvent(new CompositionEvent("compositionstart", {bubbles: true}));');
    $page->keys('#body', 'Control+Enter')->assertMissing('[data-test="mention-popup"]')
        ->assertValue('#body', 'Thanks @dev');
    expect(Commit::query()->count())->toBe(0);

    $page->script('document.querySelector("#body").dispatchEvent(new CompositionEvent("compositionend", {bubbles: true}));');
    $page->assertSee('Developer Alpha')->assertNoJavaScriptErrors();
});

it('keeps a small viewport popup bounded and rejects completions beyond 280 characters', function (): void {
    $body = str_repeat('x', 274).' @dev';
    $page = visit(route('log.show', Log::Everyone))->resize(390, 380)
        ->fill('#body', $body)->assertSee('Developer Alpha');

    $page->assertScript('(() => { const box = document.querySelector("[data-test=mention-popup]").getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth && box.top >= 0 && box.bottom <= innerHeight; })()')
        ->keys('#body', 'Enter')
        ->assertValue('#body', $body)
        ->assertSee('This mention would exceed 280 characters. Shorten your text first.')
        ->assertNoJavaScriptErrors();
});

it('dismisses the popup when its textarea scrolls out of view', function (): void {
    Commit::factory()->count(8)->create();
    $page = visit(route('log.show', Log::Everyone))->resize(390, 380)
        ->fill('#body', 'Thanks @dev')->assertSee('Developer Alpha');
    $page->script('window.scrollTo(0, 900);');
    $page->assertMissing('[data-test="mention-popup"]')->assertNoJavaScriptErrors();
});
