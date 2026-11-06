<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
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
