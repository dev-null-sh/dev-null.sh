<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\ScheduledCommit;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

it('tells the web composer whether the user still has the commit rules to read', function (): void {
    $this->actingAs(User::factory()->firstTimePoster()->create())
        ->get(route('starred.index'))
        ->assertInertia(fn ($page) => $page->where('auth.mustReadCommitRules', true));

    $this->actingAs(User::factory()->create())
        ->get(route('starred.index'))
        ->assertInertia(fn ($page) => $page->where('auth.mustReadCommitRules', false));
});

it('accepts the first commit of a first-time poster from the web composer, which read them the rules', function (): void {
    $user = User::factory()->firstTimePoster()->create();

    $this->actingAs($user)
        ->post(route('commit.store'), ['body' => 'Hello, world.', 'commit_rules_accepted' => '1'])
        ->assertSessionHasNoErrors();

    expect($user->commits()->count())->toBe(1)
        ->and($user->refresh()->commit_rules_accepted_at)->not->toBeNull();
});

it('refuses the first commit of a first-time poster that did not come with the rules', function (): void {
    $user = User::factory()->firstTimePoster()->create();
    $parent = Commit::factory()->create();

    $this->actingAs($user)
        ->post(route('commit.store'), ['body' => 'Hello, world.', 'commit_rules_accepted' => '0'])
        ->assertSessionHasErrors(['body' => 'Read the commit rules first: reload the page and commit again.']);

    $this->postJson(route('commit-reply.store', $parent), ['body' => 'Hello, world.'])
        ->assertUnprocessable();

    expect($user->commits()->count())->toBe(0)
        ->and($user->refresh()->commit_rules_accepted_at)->toBeNull();
});

it('refuses the first commit of a first-time poster from the app, even when it says they read the rules', function (): void {
    $user = User::factory()->firstTimePoster()->create();

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit.store'), ['body' => 'Hello, world.', 'commit_rules_accepted' => true])
        ->assertForbidden()
        ->assertJson([
            'code' => 'commit_rules_required',
            'message' => 'Welcome! Your first commit has to come from the web, so you can read the commit rules first: '.url('/').'. After that, commit from anywhere.',
        ]);

    expect($user->commits()->count())->toBe(0);
});

it('lets the user commit from anywhere once they read the rules', function (): void {
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit.store'), ['body' => 'Hello, world.'])
        ->assertCreated();

    $this->actingAs($user)
        ->post(route('commit.store'), ['body' => 'Hello again.'])
        ->assertSessionHasNoErrors();

    expect($user->commits()->count())->toBe(2);
});

it('asks a first-time poster for the commit rules on replies and scheduled commits too', function (): void {
    $user = User::factory()->firstTimePoster()->create();
    $parent = Commit::factory()->create();

    $this->actingAs($user)
        ->post(route('scheduled-commit.store'), ['body' => 'Hello, world.', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertSessionHasErrors(['body' => 'Read the commit rules first: reload the page and commit again.']);

    expect(ScheduledCommit::query()->count())->toBe(0);

    $this->postJson(route('commit-reply.store', $parent), ['body' => 'Hello, world.', 'commit_rules_accepted' => true])
        ->assertSuccessful();

    expect($user->commits()->count())->toBe(1)
        ->and($user->refresh()->commit_rules_accepted_at)->not->toBeNull();
});
