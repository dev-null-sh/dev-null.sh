<?php

declare(strict_types=1);

use App\Actions\CreateExport;
use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Enums\ContributorHelp;
use App\Enums\ContributorRequestStatus;
use App\Models\Commit;
use App\Models\ContributorRequest;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->withoutVite();
    Queue::fake();
});

it('publishes through ordinary commit moderation and keeps pending requests private', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($owner)->post(route('contributor-request.store'), [
        'body' => 'Please test the Windows installer.', 'project_url' => 'https://github.com/example/cli/issues/42',
        'kind' => 'testing', 'user_id' => $other->id, 'status' => 'filled',
    ])->assertRedirectToRoute('contributor-request.index');

    $item = ContributorRequest::query()->sole();
    expect($item->user_id)->toBe($owner->id)->and($item->status)->toBe(ContributorRequestStatus::Open)
        ->and($item->commit?->status)->toBe(CommitStatus::Pending)
        ->and($item->commit?->body)->toBe("Please test the Windows installer.\n\nhttps://github.com/example/cli/issues/42");
    $this->get(route('contributor-request.index'))->assertInertia(fn ($page) => $page->has('requests.data', 1));
    $this->actingAs($other)->get(route('contributor-request.index'))->assertInertia(fn ($page) => $page->has('requests.data', 0));
    $this->getJson(route('api-contributor-request.index'))->assertJsonCount(0, 'data');
    $item->commit?->update(['status' => CommitStatus::Approved]);
    $this->getJson(route('api-contributor-request.index'))->assertJsonCount(1, 'data');
});

it('filters by help and availability and paginates visible requests', function (): void {
    ContributorRequest::factory()->count(13)->create(['kind' => ContributorHelp::Testing]);
    ContributorRequest::factory()->create(['kind' => ContributorHelp::Design]);
    ContributorRequest::factory()->create(['kind' => ContributorHelp::Testing, 'status' => ContributorRequestStatus::Filled]);
    $url = route('api-contributor-request.index', ['kind' => 'testing']);
    $first = $this->getJson($url)->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('meta.total', 13);
    $this->getJson($first->json('links.next'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 13);
    $this->getJson(route('api-contributor-request.index', ['status' => 'filled']))->assertJsonCount(1, 'data');
    $this->getJson(route('api-contributor-request.index', ['status' => 'all']))->assertJsonPath('meta.total', 15);
});

it('does not show muted, blocked, deleted, or rejected requests', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->muting()->attach($muted);
    $blocked->blocking()->attach($viewer);
    ContributorRequest::factory()->for($muted)->create();
    ContributorRequest::factory()->for($blocked)->create();
    $deleted = ContributorRequest::factory()->create();
    $deleted->commit?->delete();
    $rejected = ContributorRequest::factory()->create();
    $rejected->commit?->update(['status' => CommitStatus::Rejected]);
    $visible = ContributorRequest::factory()->create();
    signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-contributor-request.index'))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
});

it('lets only the owner change availability without editing the commit or project', function (): void {
    $item = ContributorRequest::factory()->create();
    $body = $item->commit?->body;
    $this->actingAs(User::factory()->create())->put(route('contributor-request.update', $item), ['status' => 'filled'])->assertForbidden();
    $this->actingAs($item->user)->fromRoute('contributor-request.index')->put(route('contributor-request.update', $item), [
        'status' => 'filled', 'body' => 'Edited!', 'project_url' => 'https://github.com/attacker/changed',
    ])->assertRedirectToRoute('contributor-request.index');
    expect($item->refresh()->status)->toBe(ContributorRequestStatus::Filled)->and($item->filled_at)->not->toBeNull()
        ->and($item->commit?->body)->toBe($body)->and($item->project_url)->toBe('https://github.com/example/project');
    $this->put(route('contributor-request.update', $item), ['status' => 'open'])->assertRedirect();
    expect($item->refresh()->filled_at)->toBeNull();
});

it('uses the same signed API validation, moderation and ownership rules', function (): void {
    $user = User::factory()->create();
    $key = apiKeyPairFor($user);
    $response = signedApiRequest($key, 'POST', route('api-contributor-request.store'), [
        'body' => 'Could someone improve the setup guide?', 'project_url' => 'https://github.com/example/project', 'kind' => 'documentation',
    ])->assertCreated()->assertJsonPath('data.can_manage', true)->assertJsonPath('data.commit.status', 'pending');
    $id = $response->json('data.id');
    signedApiRequest($key, 'PUT', route('api-contributor-request.update', $id), ['status' => 'filled'])
        ->assertOk()->assertJsonPath('data.status', 'filled');
    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'PUT', route('api-contributor-request.update', $id), ['status' => 'open'])->assertForbidden();
    $this->postJson(route('api-contributor-request.store'), ['body' => 'Untrusted'])->assertUnauthorized();
});

it('rejects unsafe or unsupported project links', function (string $url): void {
    $this->actingAs(User::factory()->create())->postJson(route('contributor-request.store'), [
        'body' => 'Please review the docs.', 'project_url' => $url, 'kind' => 'feedback',
    ])->assertUnprocessable()->assertJsonValidationErrors('project_url');
    expect(ContributorRequest::query()->count())->toBe(0)->and(Commit::query()->count())->toBe(0);
})->with([
    'http://github.com/owner/repo', 'https://github.com.evil.test/owner/repo', 'https://github.com@evil.test/owner/repo',
    'https://github.com/owner/repo?redirect=elsewhere', 'https://github.com/owner/repo#fragment', 'https://github.com/owner/../issues/1',
    'https://github.com/owner/repo/pull/1', 'javascript:alert(1)', 'https://github.com/owner/repo/issues/0',
]);

it('requires a useful body, a valid category and the normal commit length', function (array $overrides, string $field): void {
    $data = [...['body' => 'Please test this.', 'project_url' => 'https://github.com/example/project', 'kind' => 'testing'], ...$overrides];
    $this->actingAs(User::factory()->create())->postJson(route('contributor-request.store'), $data)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['body' => ''], 'body'], [['body' => " \n\n "], 'body'], [['body' => str_repeat('x', 4001)], 'body'], [['kind' => 'unknown'], 'kind'],
]);

it('honours posting timeouts and requires web authentication', function (): void {
    $this->post(route('contributor-request.store'), [])->assertRedirectToRoute('home');
    $user = User::factory()->create();
    UserTimeout::factory()->create(['github_id' => $user->github_id]);
    $this->actingAs($user)->postJson(route('contributor-request.store'), [
        'body' => 'Please test this.', 'project_url' => 'https://github.com/example/project', 'kind' => 'testing',
    ])->assertForbidden();
    expect(ContributorRequest::query()->count())->toBe(0);
});

it('exports only the owners requests and removes them with the account', function (): void {
    $item = ContributorRequest::factory()->create();
    ContributorRequest::factory()->create();
    $export = resolve(CreateExport::class)->handle($item->user);
    $items = iterator_to_array($export['contributor_requests']);
    expect($export['schema_version'])->toBe(10)->and($items)->toHaveCount(1)
        ->and($items[0]['commit_id'])->toBe($item->commit_id);
    resolve(DeleteUser::class)->handle($item->user);
    expect(ContributorRequest::query()->whereKey($item->id)->exists())->toBeFalse();
});

it('shares the commit posting budget and requires first-time posters to accept the normal rules', function (): void {
    $user = User::factory()->firstTimePoster()->create();
    $data = ['body' => 'Please test this.', 'project_url' => 'https://github.com/example/project', 'kind' => 'testing'];
    $this->actingAs($user)->postJson(route('contributor-request.store'), $data)
        ->assertUnprocessable()->assertJsonValidationErrors('body');
    $user->update(['commit_rules_accepted_at' => now()]);
    RateLimiter::increment("commits:{$user->id}", 60, 10);
    $this->postJson(route('contributor-request.store'), $data)->assertTooManyRequests();
    expect(ContributorRequest::query()->count())->toBe(0)->and(Commit::query()->count())->toBe(0);
});
