<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('renders emoji shortcodes as emoji', function (): void {
    $viewer = User::factory()->create();

    $shortcodes = [
        'heart' => '❤️',
        'like' => '👍',
        'dislike' => '👎',
        'laugh' => '😂',
        'smile' => '😄',
        'sad' => '😢',
        'angry' => '😠',
        'fire' => '🔥',
        'rocket' => '🚀',
        'clap' => '👏',
        'eyes' => '👀',
        'party' => '🎉',
    ];

    $commit = Commit::factory()->create([
        'body' => 'This commit body is intentionally long enough to clear the sixty character title cut-off before '
            .implode(' ', array_map(static fn (string $name): string => ":{$name}", array_keys($shortcodes))),
    ]);

    $this->actingAs($viewer);

    $page = visit(route('commit.show', [$commit->user, $commit]));

    foreach ($shortcodes as $name => $emoji) {
        $page->assertSee($emoji)->assertDontSee(":{$name}");
    }

    $page->assertNoJavaScriptErrors();
});

it('replaces a shortcode inline as the user types', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit(route('log.show', Log::Following))
        ->type('#body', ':heart')
        ->assertValue('#body', '❤️')
        ->assertNoJavaScriptErrors();
});

it('leaves shortcode prefixes inside longer words untouched', function (): void {
    $viewer = User::factory()->create();

    $commit = Commit::factory()->create([
        'body' => 'This commit body is intentionally long enough to clear the sixty character title cut-off before :heartbeat :likewise',
    ]);

    $this->actingAs($viewer);

    visit(route('commit.show', [$commit->user, $commit]))
        ->assertSee(':heartbeat')
        ->assertSee(':likewise')
        ->assertDontSee('❤️')
        ->assertDontSee('👍')
        ->assertNoJavaScriptErrors();
});
