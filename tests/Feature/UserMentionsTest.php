<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;

beforeEach(function (): void {
    $this->viewer = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->viewer);
});

it('keeps an API mention on the user it captured after they rename and someone else takes the handle', function (): void {
    $original = User::factory()->create(['handle' => 'oldname']);

    signedApiRequest($this->keyPair, 'POST', route('api-commit.store'), ['body' => 'Thanks @OldName'])->assertCreated();

    $original->profile->update(['handle' => 'newname']);
    $newcomer = User::factory()->create(['handle' => 'oldname']);

    signedApiRequest($this->keyPair, 'GET', route('api-log.show', 'latest'))
        ->assertOk()
        ->assertJsonPath('data.0.mentions', [['handle' => 'OldName', 'offset' => 7, 'length' => 8, 'kind' => 'user', 'id' => $original->id]]);

    signedApiRequest($this->keyPair, 'GET', route('api-mentioned-user.show', $original->id))
        ->assertOk()
        ->assertJsonPath('data.id', $original->id)
        ->assertJsonPath('data.handle', 'newname');

    expect($newcomer->id)->not->toBe($original->id);
});

it('keeps each mention of a handle made of digits on the user it captured', function (): void {
    $hundred = User::factory()->create(['handle' => '123']);
    $zero = User::factory()->create(['handle' => '0']);

    $id = signedApiRequest($this->keyPair, 'POST', route('api-commit.store'), ['body' => '@123 @0'])->assertCreated()->json('data.id');

    signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $id))
        ->assertOk()
        ->assertJsonPath('data.mentions.*.id', [$hundred->id, $zero->id]);
});

it('gives a preview the ids its mentions would capture', function (): void {
    $nuno = User::factory()->create(['handle' => 'nunomaduro']);
    $laravel = Organization::factory()->create(['handle' => 'laravel']);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-preview.store'), ['body' => '@NunoMaduro @laravel @ghost'])
        ->assertOk()
        ->assertJsonPath('data.mentions.*.id', [$nuno->id, $laravel->id, null]);
});

it('opens a mentioned user by id as their profile does by handle, signed or not', function (): void {
    $user = User::factory()->create(['handle' => 'nunomaduro']);

    $byHandle = signedApiRequest($this->keyPair, 'GET', route('api-user.show', 'nunomaduro'))->assertOk()->json();

    signedApiRequest($this->keyPair, 'GET', route('api-mentioned-user.show', $user->id))
        ->assertOk()
        ->assertExactJson($byHandle);

    $this->getJson(route('api-mentioned-user.show', $user->id))
        ->assertOk()
        ->assertExactJson($this->getJson(route('api-user.show', 'nunomaduro'))->json());
});

it('answers a mentioned user across a block as their profile does', function (): void {
    $user = User::factory()->create(['handle' => 'nunomaduro']);
    $this->viewer->blocking()->attach($user);

    $byHandle = signedApiRequest($this->keyPair, 'GET', route('api-user.show', 'nunomaduro'));

    signedApiRequest($this->keyPair, 'GET', route('api-mentioned-user.show', $user->id))
        ->assertStatus($byHandle->status())
        ->assertExactJson($byHandle->json());
});

it('finds no mentioned user that is gone, or an id that is not one', function (): void {
    $this->getJson(route('api-mentioned-user.show', '019a0000-0000-7000-8000-000000000000'))->assertNotFound();
    $this->getJson('/api/v1/mentions/nunomaduro')->assertNotFound();
});

it('opens a mentioned organization by id after it is renamed, as its page does by handle', function (): void {
    $organization = Organization::factory()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel']);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $organization->id]);
    $organization->profile->update(['handle' => 'laravel-org']);

    $byHandle = signedApiRequest($this->keyPair, 'GET', route('api-organization.show', 'laravel-org'))->assertOk()->json();

    signedApiRequest($this->keyPair, 'GET', route('api-log.show', 'latest'))
        ->assertJsonPath('data.0.mentions.0.id', $organization->id);

    signedApiRequest($this->keyPair, 'GET', route('api-mentioned-organization.show', $organization->id))
        ->assertOk()
        ->assertExactJson($byHandle);

    $this->getJson(route('api-mentioned-organization.show', '019a0000-0000-7000-8000-000000000000'))->assertNotFound();
});
