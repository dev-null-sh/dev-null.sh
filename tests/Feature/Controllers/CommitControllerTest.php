<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;

it('may create a commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Hello, devnull!',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->commits()->sole()->body)->toBe('Hello, devnull!');
});

it('flashes the created commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $commit = $user->commits()->sole();

    $response->assertInertiaFlash('committed', [
        'id' => $commit->id,
        'body' => 'Initial commit',
    ]);
});

it('does not count the authenticated user relations when redirecting after a commit', function (): void {
    $user = User::factory()->create();

    DB::enableQueryLog();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Initial commit',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect(array_column(DB::getQueryLog(), 'query'))
        ->not->toBeEmpty()
        ->each->not->toContain('count(*)');
});

it('requires a body', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => '   ',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('requires the body to be at least 3 characters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => '  ab  ',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('requires the body to be at most 280 characters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => str_repeat('a', 281),
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('rate limits commit creation', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->fromRoute('log.show', Log::Following)
            ->post(route('commit.store'), ['body' => "Commit {$i}"])
            ->assertRedirectToRoute('log.show', Log::Following);
    }

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'One too many']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
        ]);

    expect($user->commits()->count())->toBe(10);
});

it('requires authentication to create a commit', function (): void {
    $response = $this->post(route('commit.store'), [
        'body' => 'Initial commit',
    ]);

    $response->assertRedirectToRoute('home');

    expect(Commit::query()->count())->toBe(0);
});

it('may delete own commit', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs($commit->user)
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($commit->fresh())->toBeNull();
});

it('may not delete commits of other users', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $response->assertForbidden();

    expect($commit->fresh())->not->toBeNull();
});

it('may delete own commit from its page', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs($commit->user)
        ->from(route('commit.show', [$commit->user, $commit]))
        ->delete(route('commit.destroy', $commit));

    $response->assertRedirectToRoute('user.show', $commit->user);

    expect($commit->fresh())->toBeNull();
});

it('tells users their commit is pending review', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlash('toast.type', 'info');

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Pending);
});

it('publishes commits of admins right away', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $user = User::factory()->create(['username' => 'nunomaduro']);

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlashMissing('toast');

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Approved);
});

it('may reply to a commit', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'Agreed, ship it.',
            'parent_id' => $parent->id,
        ]);

    $response->assertRedirectToRoute('log.show', Log::Everyone);

    expect($user->commits()->sole()->parent_id)->toBe($parent->id);
});

it('may reply to own pending commit', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->for($user)->pending()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Follow-up', 'parent_id' => $parent->id])
        ->assertSessionHasNoErrors();

    expect($parent->replies()->count())->toBe(1);
});

it('may not reply to a commit that is not visible', function (): void {
    $parent = Commit::factory()->pending()->create();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), ['body' => 'Hello', 'parent_id' => $parent->id]);

    $response->assertSessionHasErrors('parent_id');

    expect($parent->replies()->count())->toBe(0);
});

it('may not reply to a commit that does not exist', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), ['body' => 'Hello', 'parent_id' => '01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3']);

    $response->assertSessionHasErrors('parent_id');
});

it('shows a commit with its ancestors and reply count', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    $commit = Commit::factory()->for($root, 'parent')->create(['body' => 'Focused']);
    Commit::factory()->for($commit, 'parent')->count(2)->create();
    Commit::factory()->for($commit, 'parent')->pending()->create();

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->where('commit.id', $commit->id)
            ->where('commit.body', 'Focused')
            ->where('commit.user.username', $commit->user->username)
            ->where('commit.replies_count', 2)
            ->has('replies.data', 2)
            ->where('replies.data.0.user.username', fn (string $username): bool => $username !== '')
            ->where('replies.next_cursor', null)
            ->has('ancestors', 1)
            ->where('ancestors.0.id', $root->id)
            ->where('ancestors.0.user.username', $root->user->username)
            ->missing('commit.user.email'));
});

it('shows the first 50 replies of a commit with a cursor to the rest', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();

    Commit::factory()->for($commit, 'parent')->count(51)
        ->state(new Sequence(fn (Sequence $sequence): array => [
            'body' => "Reply {$sequence->index}",
            'created_at' => now()->subMinutes(100 - $sequence->index),
        ]))
        ->create();
    Commit::factory()->for($commit, 'parent')->pending()->create(['created_at' => now()->subMinutes(99)]);

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.replies_count', 51)
            ->has('replies.data', 50)
            ->where('replies.data.0.body', 'Reply 0')
            ->where('replies.data.1.body', 'Reply 1')
            ->where('replies.data.49.body', 'Reply 49')
            ->where('replies.next_cursor', fn (string $cursor): bool => $cursor !== ''));
});

it('shows own pending commit', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.status', 'pending'));
});

it('does not show a commit that is not visible', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertNotFound();
});

it('does not show a commit under another username', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [User::factory()->create(), $commit]));

    $response->assertNotFound();
});

it('requires authentication to show a commit', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->get(route('commit.show', [$commit->user, $commit]));

    $response->assertRedirectToRoute('home');
});
