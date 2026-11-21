<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;

it('loads contextual profile suggestions without reusing cached suggestions', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->has(Commit::factory())->create();
    $related = User::factory()->has(Commit::factory())->create();

    $profile->following()->attach($related);

    $response = $this->actingAs($viewer)->get(route('user.show', $profile));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->missing('suggestedUsers')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('suggestedUsers', 1)
                ->where('suggestedUsers.0.id', $related->id)));

    $data = $response->viewData('page');

    expect(collect($data['deferredProps'] ?? [])->flatten())->toContain('suggestedUsers')
        ->and($data['mergeProps'] ?? [])->toContain('suggestedUsers')
        ->and($data['matchPropsOn'] ?? [])->toContain('suggestedUsers.id')
        ->and($data['onceProps'] ?? [])->not->toHaveKey('suggestedUsers');
});

it('loads visible thread participants as contextual suggestions', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $participant = User::factory()->has(Commit::factory())->create();

    Commit::factory()->for($participant)->for($root, 'parent')->create(['root_id' => $root->id]);

    $response = $this->actingAs($viewer)->get(route('commit.show', [$author, $root]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->missing('suggestedUsers')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('suggestedUsers', 1)
                ->where('suggestedUsers.0.id', $participant->id)));

    $data = $response->viewData('page');

    expect(collect($data['deferredProps'] ?? [])->flatten())->toContain('suggestedUsers')
        ->and($data['mergeProps'] ?? [])->toContain('suggestedUsers')
        ->and($data['matchPropsOn'] ?? [])->toContain('suggestedUsers.id')
        ->and($data['onceProps'] ?? [])->not->toHaveKey('suggestedUsers');
});

it('loads contextual suggestions on the commit photo page', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    CommitImage::factory()->for($root)->create();
    $participant = User::factory()->has(Commit::factory())->create();

    Commit::factory()->for($participant)->for($root, 'parent')->create(['root_id' => $root->id]);

    $this->actingAs($viewer)->get(route('commit-photo.show', [$author, $root, 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->missing('suggestedUsers')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('suggestedUsers', 1)
                ->where('suggestedUsers.0.id', $participant->id)));
});
