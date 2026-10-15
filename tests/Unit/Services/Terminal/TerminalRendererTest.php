<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Services\Terminal\TerminalRenderer;
use Illuminate\Http\Request;

it('detects CLI clients correctly', function (): void {
    // curl
    $request = Request::create('/', server: ['HTTP_USER_AGENT' => 'curl/8.7.1']);
    expect(TerminalRenderer::wantsCli($request))->toBeTrue();

    // Wget
    $request = Request::create('/', server: ['HTTP_USER_AGENT' => 'Wget/1.21.3']);
    expect(TerminalRenderer::wantsCli($request))->toBeTrue();

    // HTTPie
    $request = Request::create('/', server: ['HTTP_USER_AGENT' => 'HTTPie/3.2.2']);
    expect(TerminalRenderer::wantsCli($request))->toBeTrue();

    // ?cli=1
    $request = Request::create('/?cli=1');
    expect(TerminalRenderer::wantsCli($request))->toBeTrue();

    // ?format=cli
    $request = Request::create('/?format=cli');
    expect(TerminalRenderer::wantsCli($request))->toBeTrue();

    // Accept: text/plain
    $request = Request::create('/', server: ['HTTP_ACCEPT' => 'text/plain']);
    expect(TerminalRenderer::wantsCli($request))->toBeTrue();

    // Browser
    $request = Request::create('/', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
    ]);
    expect(TerminalRenderer::wantsCli($request))->toBeFalse();

    // Inertia header
    $request = Request::create('/', server: [
        'HTTP_USER_AGENT' => 'curl/8.7.1',
        'HTTP_X_INERTIA' => 'true',
    ]);
    expect(TerminalRenderer::wantsCli($request))->toBeFalse();
});

it('respects the NO_COLOR specification', function (): void {
    $request = Request::create('/');
    expect(TerminalRenderer::isColorSupported($request))->toBeTrue();

    $request = Request::create('/', server: ['HTTP_NO_COLOR' => '1']);
    expect(TerminalRenderer::isColorSupported($request))->toBeFalse();

    $request = Request::create('/?no-color=1');
    expect(TerminalRenderer::isColorSupported($request))->toBeFalse();

    $request = Request::create('/?color=0');
    expect(TerminalRenderer::isColorSupported($request))->toBeFalse();
});

it('detects 16-color ANSI mode', function (): void {
    $request = Request::create('/?color=16');
    expect(TerminalRenderer::is16Color($request))->toBeTrue();

    $request = Request::create('/', server: ['HTTP_X_COLOR_DEPTH' => '16']);
    expect(TerminalRenderer::is16Color($request))->toBeTrue();

    $request = Request::create('/', server: ['HTTP_X_COLOR_DEPTH' => '4']);
    expect(TerminalRenderer::is16Color($request))->toBeTrue();

    $request = Request::create('/');
    expect(TerminalRenderer::is16Color($request))->toBeFalse();
});

it('sanitizes user text against terminal display manipulation', function (): void {
    // Strips ANSI cursor / clear escape sequences
    $malicious = "Hello \033[2J\033[HWorld\033[31m!";
    expect(TerminalRenderer::sanitize($malicious))->toBe('Hello World!');

    // Strips C0 control characters (BEL, NUL, BS, etc.)
    $control = "Safe\x07\x00\x08Text";
    expect(TerminalRenderer::sanitize($control))->toBe('SafeText');

    // Preserves valid UTF-8 and emojis
    $emoji = "Commit with 🔥 and 🚀 works\nsecond line";
    expect(TerminalRenderer::sanitize($emoji))->toBe("Commit with 🔥 and 🚀 works\nsecond line");
});

it('renders the ASCII banner', function (): void {
    $renderer = new TerminalRenderer();

    $colored = $renderer->banner(true);
    expect($colored)->toContain('/dev/null')
        ->toContain("\033[");

    $plain = $renderer->banner(false);
    expect($plain)->toContain('/dev/null')
        ->not->toContain("\033[");
});

it('renders terminal landing page for guests', function (): void {
    $renderer = new TerminalRenderer();
    $output = $renderer->landing(false);

    expect($output)
        ->toContain('/dev/null')
        ->toContain('A social network for developers.')
        ->toContain('/login')
        ->toContain('/commit');
});

it('renders empty feed cleanly', function (): void {
    $renderer = new TerminalRenderer();

    $output = $renderer->feed([], false);

    expect($output)
        ->toContain('Nobody has committed anything yet')
        ->toContain('curl -fsSL')
        ->not->toContain("\033[");
});

it('renders feed with commit nodes and lane colors', function (): void {
    $user = User::factory()->create([
        'username' => 'nuno',
        'preferred_lane' => 0,
    ]);

    $commit = Commit::factory()->for($user)->create([
        'body' => 'Shipped to production.',
        'status' => CommitStatus::Approved,
    ]);

    $renderer = new TerminalRenderer();
    $output = $renderer->feed([$commit], true);

    expect($output)
        ->toContain('@nuno')
        ->toContain('Shipped to production.')
        ->toContain(mb_substr($commit->id, 0, 7))
        ->toContain("\033[38;2;217;72;15m"); // Lane 0 orange
});

it('renders feed with 16-color ANSI fallbacks', function (): void {
    $user = User::factory()->create([
        'username' => 'nuno',
        'preferred_lane' => 1,
    ]);

    $commit = Commit::factory()->for($user)->create([
        'body' => 'Standard ANSI terminal.',
        'status' => CommitStatus::Approved,
    ]);

    $renderer = new TerminalRenderer();
    $output = $renderer->feed([$commit], color: true, force16: true);

    expect($output)
        ->toContain('@nuno')
        ->toContain("\033[32m"); // Lane 1 green in ANSI-16
});

it('renders user profile and stats', function (): void {
    $user = User::factory()->create([
        'username' => 'taylor',
        'name' => 'Taylor Otwell',
        'bio' => 'Laravel creator.',
        'preferred_lane' => 3,
    ]);

    $commit = Commit::factory()->for($user)->create([
        'body' => 'Laravel release day.',
        'status' => CommitStatus::Approved,
    ]);

    $renderer = new TerminalRenderer();
    $output = $renderer->profile($user, [$commit], false);

    expect($output)
        ->toContain('@taylor (Taylor Otwell)')
        ->toContain('Laravel creator.')
        ->toContain('1 commits')
        ->toContain('Laravel release day.')
        ->not->toContain("\033[");
});

it('renders public profile preview for guests', function (): void {
    $user = User::factory()->create([
        'username' => 'guest_target',
        'name' => 'Target User',
        'bio' => 'Target bio.',
    ]);

    $renderer = new TerminalRenderer();
    $output = $renderer->profilePreview($user, false);

    expect($output)
        ->toContain('@guest_target (Target User)')
        ->toContain('Target bio.')
        ->toContain("Sign in with GitHub to read @guest_target's commits on /dev/null")
        ->toContain('/login');
});

it('renders public commit preview for guests', function (): void {
    $user = User::factory()->create(['username' => 'author']);

    $commit = Commit::factory()->for($user)->create([
        'body' => 'A preview commit.',
        'status' => CommitStatus::Approved,
    ]);

    $renderer = new TerminalRenderer();
    $output = $renderer->commitPreview($commit, false);

    expect($output)
        ->toContain('A preview commit.')
        ->toContain('Sign in with GitHub to join the conversation and read replies:')
        ->toContain('/login');
});

it('renders thread with ancestors, focused commit, and replies', function (): void {
    $user = User::factory()->create(['username' => 'brendt']);

    $parent = Commit::factory()->for($user)->create([
        'body' => 'Original commit.',
        'status' => CommitStatus::Approved,
    ]);

    $focused = Commit::factory()->for($user)->create([
        'parent_id' => $parent->id,
        'body' => 'In-between reply.',
        'status' => CommitStatus::Approved,
    ]);

    $reply = Commit::factory()->for($user)->create([
        'parent_id' => $focused->id,
        'body' => 'Bottom reply.',
        'status' => CommitStatus::Approved,
    ]);

    $renderer = new TerminalRenderer();
    $output = $renderer->thread($focused, [$parent], [$reply], false);

    expect($output)
        ->toContain('Original commit.')
        ->toContain('In-between reply.')
        ->toContain('(HEAD)')
        ->toContain('Bottom reply.')
        ->not->toContain("\033[");
});

it('rejects JSON API requests from wantsCli', function (): void {
    $request = Request::create('/', server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    expect(TerminalRenderer::wantsCli($request))->toBeFalse();
});

it('renders feed with a title banner', function (): void {
    $renderer = new TerminalRenderer();
    $output = $renderer->feed([], false, false, 'Custom Feed Title');

    expect($output)
        ->toContain('Custom Feed Title')
        ->toContain('────────────────────────────────────────────────────────────');
});

it('renders user profile with zero commits gracefully', function (): void {
    $user = User::factory()->create(['username' => 'newbie']);

    $renderer = new TerminalRenderer();
    $output = $renderer->profile($user, [], false);

    expect($output)
        ->toContain('@newbie has not committed anything yet.');
});

it('renders commit card with stars and replies count', function (): void {
    $user = User::factory()->create(['username' => 'popular', 'preferred_lane' => 99]);

    $commit = Commit::factory()->for($user)->create([
        'body' => 'Popular commit with interactions.',
        'status' => CommitStatus::Approved,
    ]);

    $commit->stars_count = 42;
    $commit->replies_count = 7;

    $renderer = new TerminalRenderer();
    $card = $renderer->commitCard($commit, true);

    expect($card)
        ->toContain('42 ★')
        ->toContain('7 ⤿')
        ->toContain('@popular');
});
