<?php

declare(strict_types=1);

use App\Jobs\RefreshRepoPet;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function fakePetRepositoryOptions(): void
{
    Http::fake([
        'https://api.github.com/repos/acme/widget' => Http::response([
            'id' => 42, 'full_name' => 'acme/widget', 'private' => false, 'default_branch' => 'main',
        ]),
        'https://api.github.com/repos/acme/widget/actions/workflows*' => Http::response([
            'workflows' => [
                ['id' => 7, 'name' => 'Tests', 'state' => 'active'],
                ['id' => 8, 'name' => 'Deploy', 'state' => 'active'],
                ['id' => 9, 'name' => 'Retired', 'state' => 'disabled_manually'],
            ],
        ]),
    ]);
}

it('lists active workflows for a public repository', function (): void {
    $user = User::factory()->create();
    fakePetRepositoryOptions();

    $response = $this->actingAs($user)->getJson(route('repo-pet-repository.show', ['repository' => 'acme/widget']));

    $response->assertOk()->assertExactJson([
        'repository' => 'acme/widget',
        'repository_id' => 42,
        'workflows' => [['id' => 7, 'name' => 'Tests'], ['id' => 8, 'name' => 'Deploy']],
    ]);
    Http::assertSentCount(2);
});

it('saves one verified workflow for the signed-in user and ignores spoofed fields', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    fakePetRepositoryOptions();

    $response = $this->actingAs($user)->fromRoute('user-profile.edit')->put(route('repo-pet.update'), [
        'repository' => 'acme/widget', 'workflow_id' => 7,
        'user_id' => $other->id, 'repository_id' => 999, 'workflow' => 'Forged',
    ]);

    $response->assertRedirectToRoute('user-profile.edit')->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Repo pet adopted.');
    expect($user->refresh()->repo_pet)->toBe([
        'repository' => 'acme/widget', 'repository_id' => 42, 'workflow_id' => 7, 'workflow' => 'Tests',
    ]);
    expect($other->refresh()->repo_pet)->toBeNull();
    Http::assertSentCount(2);
});

it('replaces the current selection instead of adding a second pet', function (): void {
    $user = User::factory()->create(['repo_pet' => [
        'repository' => 'acme/old', 'repository_id' => 41, 'workflow_id' => 2, 'workflow' => 'Old CI',
    ]]);
    fakePetRepositoryOptions();

    $response = $this->actingAs($user)->fromRoute('user-profile.edit')->put(route('repo-pet.update'), [
        'repository' => 'acme/widget', 'workflow_id' => 8,
    ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->repo_pet)->toBe([
        'repository' => 'acme/widget', 'repository_id' => 42, 'workflow_id' => 8, 'workflow' => 'Deploy',
    ]);
    Http::assertSentCount(2);
});

it('removes only the signed-in users pet without contacting GitHub', function (): void {
    $configuration = ['repository' => 'acme/widget', 'repository_id' => 42, 'workflow_id' => 7, 'workflow' => 'Tests'];
    $user = User::factory()->create(['repo_pet' => $configuration]);
    $other = User::factory()->create(['repo_pet' => $configuration]);

    $response = $this->actingAs($user)->fromRoute('user-profile.edit')
        ->delete(route('repo-pet.destroy'), ['user_id' => $other->id]);

    $response->assertRedirectToRoute('user-profile.edit')->assertSessionHasNoErrors();
    expect($user->refresh()->repo_pet)->toBeNull();
    expect($other->refresh()->repo_pet)->toBe($configuration);
    Http::assertNothingSent();
});

it('returns 422 for malformed configuration without contacting GitHub', function (array $payload, array $fields): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson(route('repo-pet.update'), $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors($fields);
    expect($user->refresh()->repo_pet)->toBeNull();
    Http::assertNothingSent();
})->with([
    'missing selection' => [[], ['repository', 'workflow_id']],
    'URL instead of repository' => [['repository' => 'https://evil.test/acme/widget', 'workflow_id' => 7], ['repository']],
    'path traversal' => [['repository' => 'acme/../widget', 'workflow_id' => 7], ['repository']],
    'array repository' => [['repository' => ['acme/widget'], 'workflow_id' => 7], ['repository']],
    'invalid workflow' => [['repository' => 'acme/widget', 'workflow_id' => -1], ['workflow_id']],
    'fractional workflow' => [['repository' => 'acme/widget', 'workflow_id' => 1.5], ['workflow_id']],
]);

it('returns 422 when a workflow is disabled or belongs to another repository', function (int $workflow): void {
    $user = User::factory()->create();
    fakePetRepositoryOptions();

    $response = $this->actingAs($user)->putJson(route('repo-pet.update'), ['repository' => 'acme/widget', 'workflow_id' => $workflow]);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'workflow_id' => 'Choose an active workflow from this repository.',
    ]);
    expect($user->refresh()->repo_pet)->toBeNull();
    Http::assertSentCount(2);
})->with([9, 1234]);

it('keeps the saved pet when GitHub cannot verify a replacement', function (): void {
    $configuration = ['repository' => 'acme/widget', 'repository_id' => 42, 'workflow_id' => 7, 'workflow' => 'Tests'];
    $user = User::factory()->create(['repo_pet' => $configuration]);
    Http::fake(['https://api.github.com/repos/acme/private' => Http::response(['id' => 43, 'private' => true])]);

    $response = $this->actingAs($user)->putJson(route('repo-pet.update'), ['repository' => 'acme/private', 'workflow_id' => 7]);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'repository' => 'Could not load that public repository. Check the name or try again shortly.',
    ]);
    expect($user->refresh()->repo_pet)->toBe($configuration);
    Http::assertSentCount(1);
});

it('returns 422 for an invalid repository lookup without contacting GitHub', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('repo-pet-repository.show', ['repository' => 'not-a-repo']));

    $response->assertUnprocessable()->assertJsonValidationErrors('repository');
    Http::assertNothingSent();
});

it('returns 422 for an unavailable public repository lookup', function (): void {
    $user = User::factory()->create();
    Http::fake(['https://api.github.com/repos/acme/widget' => Http::response([], 404)]);

    $response = $this->actingAs($user)->getJson(route('repo-pet-repository.show', ['repository' => 'acme/widget']));

    $response->assertUnprocessable()->assertJsonValidationErrors('repository');
    Http::assertSentCount(1);
});

it('requires authentication for pet settings', function (string $method, string $route): void {
    $response = $this->{$method}(route($route));

    $response->assertRedirectToRoute('home');
    Http::assertNothingSent();
})->with([
    ['get', 'repo-pet-repository.show'], ['put', 'repo-pet.update'], ['delete', 'repo-pet.destroy'],
]);

it('renders a configured pet on another users profile without waiting for GitHub', function (): void {
    $viewer = User::factory()->create();
    $configuration = ['repository' => 'acme/widget', 'repository_id' => 42, 'workflow_id' => 7, 'workflow' => 'Tests'];
    $owner = User::factory()->create(['repo_pet' => $configuration]);
    Queue::fake([RefreshRepoPet::class]);

    $response = $this->actingAs($viewer)->get(route('user.show', $owner));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('user/show')
        ->where('repoPet.repository', 'acme/widget')
        ->where('repoPet.workflow', 'Tests')
        ->where('repoPet.mood', 'sleeping')
        ->missing('user.repo_pet')
        ->missing('repoPetConfiguration'));
    Queue::assertPushed(RefreshRepoPet::class, fn (RefreshRepoPet $job): bool => $job->configuration === $configuration);
    Http::assertNothingSent();
});

it('keeps pet preferences hidden from generic user serialization', function (): void {
    $configuration = ['repository' => 'acme/widget', 'repository_id' => 42, 'workflow_id' => 7, 'workflow' => 'Tests'];
    $user = User::factory()->create(['repo_pet' => $configuration]);
    Queue::fake([RefreshRepoPet::class]);

    $response = $this->actingAs($user)->get(route('user-profile.edit'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('repoPetConfiguration', $configuration)
        ->where('repoPet.repository', 'acme/widget')
        ->missing('auth.user.repo_pet'));
    Queue::assertPushed(RefreshRepoPet::class);
    Http::assertNothingSent();
});

it('leaves unconfigured profiles without a pet or background request', function (): void {
    $user = User::factory()->create();
    Queue::fake([RefreshRepoPet::class]);

    $response = $this->actingAs($user)->get(route('user.show', $user));

    $response->assertOk()->assertInertia(fn ($page) => $page->where('repoPet', null));
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});
