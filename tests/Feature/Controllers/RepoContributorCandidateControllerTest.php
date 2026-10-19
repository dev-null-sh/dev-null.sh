<?php

declare(strict_types=1);

use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

it('says who can join a repo and why not', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->create(['user_id' => User::factory()->create(['username' => 'ana_in'])])->user;
    $contributor->following()->attach($maintainer);
    $follower = User::factory()->create(['username' => 'ana_follows']);
    $follower->following()->attach($maintainer);
    User::factory()->create(['username' => 'ana_stranger']);

    $response = $this->actingAs($maintainer)->getJson(route('repo-contributor-candidate.index', [$repo, 'q' => 'ana']))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(collect($response->json('candidates'))->mapWithKeys(fn (array $candidate): array => [$candidate['user']['username'] => $candidate['reason']])->all())
        ->toEqualCanonicalizing([
            'ana_in' => 'already contributes',
            'ana_follows' => null,
            'ana_stranger' => "doesn't follow you",
        ]);
});

it('leaves the maintainer out', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create(['user_id' => User::factory()->create(['username' => 'ana_me'])])->user;

    $this->actingAs($maintainer)->getJson(route('repo-contributor-candidate.index', [$repo, 'q' => 'ana']))
        ->assertOk()
        ->assertJsonPath('candidates', []);
});

it('lets only maintainers search for people to add', function (): void {
    $repo = Repo::factory()->create();
    RepoContributor::factory()->for($repo)->maintainer()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;

    $this->actingAs($contributor)->getJson(route('repo-contributor-candidate.index', [$repo, 'q' => 'ana']))->assertForbidden();
    $this->actingAs(User::factory()->create())->getJson(route('repo-contributor-candidate.index', [$repo, 'q' => 'ana']))->assertNotFound();
});
