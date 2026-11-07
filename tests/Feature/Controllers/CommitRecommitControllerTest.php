<?php

declare(strict_types=1);

use App\Actions\CreateCommitRecommit;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;

it('recommits an approved commit and lists it on the recommitter profile', function (): void {
    Queue::fake();

    $user = User::factory()->firstTimePoster()->create();
    $original = Commit::factory()->create();

    $this->actingAs($user)
        ->post(route('commit-recommit.store', $original))
        ->assertSessionHasErrors('body');

    expect($user->commits()->exists())->toBeFalse();

    $this->actingAs($user)
        ->fromRoute('user.show', $user)
        ->post(route('commit-recommit.store', $original), ['commit_rules_accepted' => '1'])
        ->assertRedirectToRoute('user.show', $user)
        ->assertSessionHasNoErrors();

    $recommit = $user->commits()
        ->where('quoted_commit_id', $original->id)
        ->sole();

    expect($recommit->body)->toBe('')
        ->and($recommit->parent_id)->toBeNull()
        ->and($recommit->author_id)->toBe($user->id)
        ->and($user->refresh()->commit_rules_accepted_at)->not->toBeNull();

    $this->get(route('user.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.id', $recommit->id)
            ->where('commits.data.0.quoted_commit_id', $original->id)
            ->where('commits.data.0.body', ''));

    $this->get(route('commit.show', [$original->author, $original]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.recommitted', true));

    $this->actingAs($user)
        ->post(route('commit-recommit.store', $original))
        ->assertForbidden();
});

it('recommits an approved commit immediately for an admin', function (): void {
    Queue::fake();
    config(['app.admins' => ['admin']]);

    $user = User::factory()->create(['handle' => 'admin']);
    $original = Commit::factory()->create();

    $this->actingAs($user)
        ->fromRoute('user.show', $user)
        ->post(route('commit-recommit.store', $original))
        ->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Recommitted.',
        ]);

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Approved);
});

it('hides recommits of commits the user cannot see', function (): void {
    $author = User::factory()->create();
    $user = User::factory()->create();
    $pending = Commit::factory()->pending()->create(['user_id' => $author->id]);

    $this->actingAs($user)
        ->post(route('commit-recommit.store', $pending))
        ->assertNotFound();
});

it('does not let users recommit their own commits', function (): void {
    $user = User::factory()->create();
    $original = Commit::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('commit-recommit.store', $original))
        ->assertForbidden();
});

it('rechecks that the original is eligible inside the recommit transaction', function (): void {
    $user = User::factory()->create();
    $pending = Commit::factory()->pending()->create();

    expect(fn () => resolve(CreateCommitRecommit::class)->handle($user, $pending))
        ->toThrow(AuthorizationException::class);
});

it('allows a new recommit after deleting the earlier one', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $original = Commit::factory()->create();

    $this->actingAs($user)->post(route('commit-recommit.store', $original))->assertSessionHasNoErrors();
    $first = $user->commits()->where('quoted_commit_id', $original->id)->sole();

    $this->delete(route('commit.destroy', $first))->assertRedirect();
    $this->post(route('commit-recommit.store', $original))->assertSessionHasNoErrors();

    expect($first->refresh()->trashed())->toBeTrue()
        ->and($user->commits()->where('quoted_commit_id', $original->id)->where('body', '')->count())->toBe(1);
});

it('restores a deleted recommit through the normal undo workflow', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $original = Commit::factory()->create();

    $this->actingAs($user)->post(route('commit-recommit.store', $original))->assertSessionHasNoErrors();
    $recommit = $user->commits()->where('quoted_commit_id', $original->id)->sole();

    $this->delete(route('commit.destroy', $recommit))->assertRedirect();
    $this->post(route('commit-restoration.store', $recommit))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.id', $recommit->id);

    $this->assertNotSoftDeleted($recommit);
});
