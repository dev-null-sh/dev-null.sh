<?php

declare(strict_types=1);

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

it('lets maintainers add their followers as contributors', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    [$first, $second] = followersOf($maintainer, 2);

    $this->actingAs($maintainer)
        ->fromRoute('repo.show', $repo)
        ->post(route('repo-contributor.store', $repo), ['contributors' => [$first->username, $second->username]])
        ->assertRedirectToRoute('repo.show', $repo);

    expect($repo->roleOf($first))->toBe(RepoRole::Contributor)
        ->and($repo->roleOf($second))->toBe(RepoRole::Contributor);
});

it('only adds followers who are not in yet', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    $contributor->following()->attach($maintainer);
    [$follower] = followersOf($maintainer, 1);
    $stranger = User::factory()->create(['username' => 'stranger']);

    $this->actingAs($maintainer)->post(route('repo-contributor.store', $repo), ['contributors' => [$follower->username, 'stranger']])
        ->assertSessionHasErrors(['contributors.1' => "@stranger doesn't follow you."]);
    $this->post(route('repo-contributor.store', $repo), ['contributors' => [$contributor->username]])
        ->assertSessionHasErrors(['contributors.0' => "@{$contributor->username} already contributes."]);
    $this->post(route('repo-contributor.store', $repo), ['contributors' => ['nobody-by-that-name']])
        ->assertSessionHasErrors('contributors.0');
    $this->post(route('repo-contributor.store', $repo), ['contributors' => []])
        ->assertSessionHasErrors('contributors');

    expect($repo->hasContributor($stranger))->toBeFalse()
        ->and($repo->hasContributor($follower))->toBeFalse();
});

it('lets only maintainers add contributors', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    [$follower] = followersOf($maintainer, 1);
    $follower->following()->attach($contributor);

    $this->actingAs($contributor)->post(route('repo-contributor.store', $repo), ['contributors' => [$follower->username]])->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('repo-contributor.store', $repo), ['contributors' => [$follower->username]])->assertNotFound();

    expect($repo->hasContributor($follower))->toBeFalse();
});

it('promotes and demotes contributors', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->create()->user;

    $this->actingAs($maintainer)->put(route('repo-contributor.update', [$repo, $contributor]), ['role' => 'maintainer'])->assertRedirect();
    expect($repo->isMaintainedBy($contributor))->toBeTrue();

    $this->put(route('repo-contributor.update', [$repo, $contributor]), ['role' => 'contributor'])->assertRedirect();
    expect($repo->roleOf($contributor))->toBe(RepoRole::Contributor);
});

it('keeps at least one maintainer', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;

    $this->actingAs($maintainer)->put(route('repo-contributor.update', [$repo, $maintainer]), ['role' => 'contributor'])
        ->assertSessionHasErrors(['role' => 'A repo needs at least one maintainer.']);
    $this->put(route('repo-contributor.update', [$repo, $maintainer]), ['role' => 'owner'])
        ->assertSessionHasErrors('role');

    expect($repo->isMaintainedBy($maintainer))->toBeTrue();
});

it('lets only maintainers change roles of contributors', function (): void {
    $repo = Repo::factory()->create();
    RepoContributor::factory()->for($repo)->maintainer()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    $other = RepoContributor::factory()->for($repo)->create()->user;

    $this->actingAs($contributor)->put(route('repo-contributor.update', [$repo, $other]), ['role' => 'maintainer'])->assertForbidden();
    $this->actingAs(User::factory()->create())->put(route('repo-contributor.update', [$repo, $other]), ['role' => 'maintainer'])->assertNotFound();
    $this->actingAs($contributor)->put(route('repo-contributor.update', [$repo, User::factory()->create()]), ['role' => 'maintainer'])->assertNotFound();

    expect($repo->roleOf($other))->toBe(RepoRole::Contributor);
});

it('lets maintainers remove contributors', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->create()->user;

    $this->actingAs($maintainer)
        ->fromRoute('repo.show', $repo)
        ->delete(route('repo-contributor.destroy', [$repo, $contributor]))
        ->assertRedirectToRoute('repo.show', $repo);

    expect($repo->hasContributor($contributor))->toBeFalse();
    $this->actingAs($contributor)->get(route('repo.show', $repo))->assertNotFound();
});

it('lets anyone leave, back to their repos', function (): void {
    $repo = Repo::factory()->create();
    RepoContributor::factory()->for($repo)->maintainer()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;

    $this->actingAs($contributor)
        ->delete(route('repo-contributor.destroy', [$repo, $contributor]))
        ->assertRedirectToRoute('repo.index');

    expect($repo->hasContributor($contributor))->toBeFalse();
});

it('lets only maintainers remove someone else', function (): void {
    $repo = Repo::factory()->create();
    RepoContributor::factory()->for($repo)->maintainer()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    $other = RepoContributor::factory()->for($repo)->create()->user;

    $this->actingAs($contributor)->delete(route('repo-contributor.destroy', [$repo, $other]))->assertForbidden();
    $this->actingAs(User::factory()->create())->delete(route('repo-contributor.destroy', [$repo, $other]))->assertNotFound();
    $this->actingAs($contributor)->delete(route('repo-contributor.destroy', [$repo, User::factory()->create()]))->assertNotFound();

    expect($repo->hasContributor($other))->toBeTrue();
});
