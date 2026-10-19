<?php

declare(strict_types=1);

use App\Enums\RepoRole;
use App\Models\Commit;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

it('lists the repos of the user', function (): void {
    $user = User::factory()->create();
    $repo = Repo::factory()->create(['name' => 'dev/null']);
    RepoContributor::factory()->for($repo)->for($user)->maintainer()->create();
    RepoContributor::factory()->for($repo)->create();
    Repo::factory()->create();

    $this->actingAs($user)->get(route('repo.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('repo/index')
            ->has('repos', 1)
            ->where('repos.0.name', 'dev/null')
            ->where('repos.0.contributors_count', 2)
            ->where('repos.0.pivot.role', RepoRole::Maintainer->value));
});

it('creates a repo maintained by its creator', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('repo.store'), ['name' => ' dev/null core ', 'description' => 'Where the pastéis are planned.'])
        ->assertRedirectToRoute('repo.show', 'devnull-core');

    $repo = Repo::query()->sole();

    expect($repo->name)->toBe('dev/null core')
        ->and($repo->description)->toBe('Where the pastéis are planned.')
        ->and($repo->isMaintainedBy($user))->toBeTrue();
});

it('creates a repo without a description', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('repo.store'), ['name' => 'dev/null', 'description' => ''])
        ->assertRedirect();

    expect(Repo::query()->sole()->description)->toBeNull();
});

it('validates the repo name', function (array $data, string $error): void {
    Repo::factory()->create(['name' => 'dev/null', 'slug' => 'devnull']);

    $this->actingAs(User::factory()->create())
        ->post(route('repo.store'), $data)
        ->assertSessionHasErrors([$error]);

    expect(Repo::query()->count())->toBeOne();
})->with([
    'missing' => [['name' => ''], 'name'],
    'too long' => [['name' => str_repeat('a', 51)], 'name'],
    'no letters or numbers' => [['name' => '!!!'], 'name'],
    'taken' => [['name' => 'DevNull'], 'name'],
    'description too long' => [['name' => 'other', 'description' => str_repeat('a', 161)], 'description'],
]);

it('shows the repo, its contributors, and its commits to contributors', function (): void {
    $repo = Repo::factory()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    $commit = Commit::factory()->for($repo)->create();
    Commit::factory()->for(Repo::factory())->create();
    Commit::factory()->create();

    $this->actingAs($contributor)->get(route('repo.show', $repo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('repo/show')
            ->where('repo.id', $repo->id)
            ->where('role', RepoRole::Contributor->value)
            ->has('contributors', 1)
            ->where('contributors.0.user.username', $contributor->username)
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $commit->id));
});

it('hides the repo from everyone else', function (): void {
    $repo = Repo::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('repo.show', $repo))->assertNotFound();
});

it('requires a sign in', function (): void {
    $this->get(route('repo.index'))->assertRedirect();
});
