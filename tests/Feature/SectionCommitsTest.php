<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Enums\Section;
use App\Models\Commit;
use App\Models\ScheduledCommit;
use App\Models\User;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
});

it('posts a commit to a section when composing it', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('section.show', Section::Hiring)
        ->post(route('commit.store'), ['body' => 'We are hiring a Rust engineer.', 'section' => 'hiring'])
        ->assertRedirectToRoute('section.show', Section::Hiring)
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole())->section->toBe(Section::Hiring);
});

it('names the section in what it flashes about the commit', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Trending)
        ->post(route('commit.store'), ['body' => 'We are hiring a Rust engineer.', 'section' => 'hiring'])
        ->assertInertiaFlash('committed', [
            'id' => $user->commits()->sole()->id,
            'body' => 'We are hiring a Rust engineer.',
            'section' => 'hiring',
        ]);
});

it('posts to the logs unless a section was chosen', function (mixed $input): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Trending)
        ->post(route('commit.store'), ['body' => 'Hello there', ...$input])
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole())->section->toBeNull();
})->with([
    'not sent' => [[]],
    'empty' => [['section' => null]],
]);

it('rejects a section that does not exist', function (): void {
    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Trending)
        ->post(route('commit.store'), ['body' => 'Hello there', 'section' => 'nowhere'])
        ->assertSessionHasErrors('section');

    expect(Commit::query()->count())->toBe(0);
});

it('never posts a quote to a section', function (): void {
    $quoted = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Trending)
        ->post(route('commit.store'), ['body' => 'Required reading.', 'quoted_commit_id' => $quoted->id, 'section' => 'hiring'])
        ->assertSessionHasErrors('section');

    expect(Commit::query()->whereNotNull('quoted_commit_id')->count())->toBe(0);
});

it('never posts a reply to a section', function (): void {
    $parent = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Trending)
        ->post(route('commit.store'), ['body' => 'Reply', 'parent_id' => $parent->id, 'section' => 'hiring'])
        ->assertSessionHasErrors('section');

    expect(Commit::query()->whereNotNull('parent_id')->count())->toBe(0);
});

it('keeps replies under a commit in a section out of the section', function (): void {
    $author = User::factory()->create();
    $replier = User::factory()->create();
    $hiring = Commit::factory()->for($author)->inSection(Section::Hiring)->create();

    $this->actingAs($replier)
        ->fromRoute('section.show', Section::Hiring)
        ->post(route('commit-reply.store', $hiring), ['body' => 'I am interested!'])
        ->assertSessionHasNoErrors();

    $reply = $replier->commits()->sole();

    expect($reply->parent_id)->toBe($hiring->id)
        ->and($reply->root_id)->toBe($hiring->id)
        ->and($reply->section)->toBeNull()
        ->and($reply->threadSection())->toBe(Section::Hiring)
        ->and($reply->isInSection())->toBeTrue();
});

it('may not quote a commit in a section', function (): void {
    $quoted = Commit::factory()->inSection(Section::Hiring)->create();

    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Trending)
        ->post(route('commit.store'), ['body' => 'Required reading.', 'quoted_commit_id' => $quoted->id])
        ->assertSessionHasErrors(['quoted_commit_id' => 'That commit is no longer available to quote.']);

    expect(Commit::query()->whereNotNull('quoted_commit_id')->count())->toBe(0);
});

it('may still quote a reply under a commit in a section', function (): void {
    $reply = Commit::factory()->for(Commit::factory()->inSection(Section::Hiring)->create(), 'parent')->create();

    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Trending)
        ->post(route('commit.store'), ['body' => 'Required reading.', 'quoted_commit_id' => $reply->id])
        ->assertSessionHasNoErrors();

    expect(Commit::query()->where('quoted_commit_id', $reply->id)->count())->toBe(1);
});

it('may not pin a commit in a section', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->inSection(Section::Hiring)->create();

    $this->actingAs($user)
        ->put(route('commit-pin.update', $commit))
        ->assertForbidden();

    expect($user->fresh()->pinned_commit_id)->toBeNull();
});

it('schedules a commit to a section, which is posted there at the due time', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scheduled-commit.store'), [
        'body' => 'We are hiring a Rust engineer.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'section' => 'hiring',
    ])->assertSessionHasNoErrors();

    expect(ScheduledCommit::query()->sole())->section->toBe(Section::Hiring)
        ->and(Commit::query()->count())->toBe(0);

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect(Commit::query()->sole())->section->toBe(Section::Hiring);
});

it('schedules to the logs unless a section was chosen', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scheduled-commit.store'), [
        'body' => 'Hello there',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertSessionHasNoErrors();

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect(ScheduledCommit::query()->sole())->section->toBeNull()
        ->and(Commit::query()->sole())->section->toBeNull();
});

it('rejects a scheduled section that does not exist', function (): void {
    $this->actingAs(User::factory()->create())->post(route('scheduled-commit.store'), [
        'body' => 'Hello there',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'section' => 'nowhere',
    ])->assertSessionHasErrors('section');

    expect(ScheduledCommit::query()->count())->toBe(0);
});
