<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

it('serves the terminal landing page for guest curl requests on / and /log', function (): void {
    $response = $this->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get('/');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($response->getContent())
        ->toContain('/dev/null')
        ->toContain('A social network for developers.')
        ->toContain('/login')
        ->toContain('curl -fsSL');

    $logResponse = $this->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get(route('cli-log.index'));

    $logResponse->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($logResponse->getContent())
        ->toContain('/dev/null')
        ->toContain('Sign in:');
});

it('serves the following feed for authenticated users via CLI on /log', function (): void {
    $user = User::factory()->create(['username' => 'auth_dev']);
    $friend = User::factory()->create(['username' => 'nuno']);

    $user->following()->attach($friend);

    $commit = Commit::factory()->for($friend)->create([
        'body' => 'Terminal commits landed.',
        'status' => CommitStatus::Approved,
    ]);

    $response = $this->actingAs($user)
        ->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get(route('cli-log.index'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($response->getContent())
        ->toContain('/dev/null')
        ->toContain('@nuno')
        ->toContain('Terminal commits landed.')
        ->toContain(mb_substr($commit->id, 0, 7));
});

it('redirects browser requests on /log to home for guests', function (): void {
    $response = $this->withServerVariables([
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
    ])->get(route('cli-log.index'));

    $response->assertRedirect(route('home'));
});

it('redirects browser requests on /log to following feed for authenticated users', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withServerVariables([
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ])->get(route('cli-log.index'));

    $response->assertRedirect(route('log.show', 'following'));
});

it('serves public profile preview for guests when curl hits @username', function (): void {
    $user = User::factory()->create([
        'username' => 'taylor',
        'name' => 'Taylor Otwell',
        'bio' => 'Building Laravel.',
    ]);

    $response = $this->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get(route('user.show', $user));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($response->getContent())
        ->toContain('@taylor')
        ->toContain('(Taylor Otwell)')
        ->toContain('Building Laravel.')
        ->toContain("Sign in with GitHub to read @taylor's commits")
        ->toContain('/login');
});

it('serves full user profile and commits when authenticated user curls @username', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create([
        'username' => 'taylor',
        'name' => 'Taylor Otwell',
    ]);

    $commit = Commit::factory()->for($user)->create([
        'body' => 'Pushed commit to devnull.',
        'status' => CommitStatus::Approved,
    ]);

    $response = $this->actingAs($viewer)
        ->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get(route('user.show', $user));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($response->getContent())
        ->toContain('@taylor')
        ->toContain('Pushed commit to devnull.');
});

it('serves commit preview for guests and full thread for authenticated users', function (): void {
    $author = User::factory()->create(['username' => 'nuno']);

    $parent = Commit::factory()->for($author)->create([
        'body' => 'Parent thread.',
        'status' => CommitStatus::Approved,
    ]);

    $reply = Commit::factory()->for($author)->create([
        'parent_id' => $parent->id,
        'body' => 'Child reply.',
        'status' => CommitStatus::Approved,
    ]);

    // Guest gets preview
    $guestResponse = $this->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get(route('commit.show', [$author, $parent]));

    $guestResponse->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($guestResponse->getContent())
        ->toContain('Parent thread.')
        ->toContain('Sign in with GitHub to join the conversation')
        ->toContain('/login')
        ->not->toContain('Child reply.');

    // Authenticated user gets full thread
    $viewer = User::factory()->create();
    $authResponse = $this->actingAs($viewer)
        ->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get(route('commit.show', [$author, $reply]));

    $authResponse->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($authResponse->getContent())
        ->toContain('Parent thread.')
        ->toContain('Child reply.')
        ->toContain('(HEAD)');
});

it('respects NO_COLOR on terminal endpoints', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withServerVariables([
            'HTTP_USER_AGENT' => 'curl/8.7.1',
            'HTTP_NO_COLOR' => '1',
        ])->get(route('cli-log.index'));

    $response->assertOk();

    expect($response->getContent())
        ->toContain('/dev/null')
        ->not->toContain("\033[");
});

it('respects limit query parameter on CLI log', function (): void {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $user->following()->attach($friend);

    Commit::factory()->count(10)->for($friend)->create([
        'status' => CommitStatus::Approved,
    ]);

    $response = $this->actingAs($user)
        ->withServerVariables(['HTTP_USER_AGENT' => 'curl/8.7.1'])
        ->get(route('cli-log.index', ['limit' => 3]));

    $response->assertOk();

    $content = $response->getContent();
    expect(mb_substr_count($content, '@'.$friend->username))->toBe(3);
});
