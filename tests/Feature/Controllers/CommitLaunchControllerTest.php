<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Events\CommitsUpdated;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('creates an enum typed launch through normal commit submission', function (): void {
    $author = User::factory()->create();

    $this->actingAs($author)->post(route('commit.store'), [
        'body' => 'Today I shipped my first package!',
        'type' => CommitType::Launch->value,
    ])->assertRedirect();

    $commit = $author->commits()->sole();
    expect($commit->type)->toBe(CommitType::Launch)
        ->and($commit->body)->toBe('Today I shipped my first package!');
});

it('keeps ordinary commits standard and rejects unknown types', function (): void {
    $author = User::factory()->create();
    $this->actingAs($author)->post(route('commit.store'), ['body' => 'Hello!'])->assertRedirect();
    expect($author->commits()->sole()->type)->toBe(CommitType::Standard);

    $this->postJson(route('commit.store'), ['body' => 'Nope', 'type' => 'unknown'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');
    expect($author->commits()->count())->toBeOne();
});

it('shows a public launch with an empty crew to guests', function (): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $this->getJson(route('commit-launch.show', $commit))->assertOk()
        ->assertExactJson(['count' => 0, 'boarded' => false, 'passengers' => []])
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->getJson(route('api-commit.show', $commit))->assertOk()->assertJsonPath('data.type', 'launch');
});

it('boards only once and persists that crew for other readers', function (): void {
    Event::fake([CommitsUpdated::class]);
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $viewer = User::factory()->create();

    foreach (range(1, 2) as $attempt) {
        $this->actingAs($viewer)->postJson(route('launch-passenger.store', $commit))
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('boarded', true)
            ->assertJsonPath('passengers.0.handle', $viewer->handle);
    }

    expect(DB::table('launch_passengers')->count())->toBeOne();
    Event::assertDispatchedTimes(CommitsUpdated::class, 1);

    $this->actingAs(User::factory()->create())->getJson(route('commit-launch.show', $commit))
        ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('boarded', false)
        ->assertJsonPath('passengers.0.id', $viewer->id);
});

it('lets a passenger step ashore idempotently', function (): void {
    Event::fake([CommitsUpdated::class]);
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $viewer = User::factory()->create();
    $commit->launchPassengers()->attach($viewer, ['created_at' => now()]);

    foreach (range(1, 2) as $attempt) {
        $this->actingAs($viewer)->deleteJson(route('launch-passenger.destroy', $commit))
            ->assertOk()->assertExactJson(['count' => 0, 'boarded' => false, 'passengers' => []]);
    }

    Event::assertDispatchedTimes(CommitsUpdated::class, 1);
});

it('requires sign in to board or step ashore', function (): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $this->postJson(route('launch-passenger.store', $commit))->assertUnauthorized();
    $this->deleteJson(route('launch-passenger.destroy', $commit))->assertUnauthorized();
    expect(DB::table('launch_passengers')->count())->toBe(0);
});

it('keeps the author as captain instead of adding a self celebration', function (): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $this->actingAs($commit->user)->postJson(route('launch-passenger.store', $commit))->assertForbidden();
    expect(DB::table('launch_passengers')->count())->toBe(0);
});

it('hides non launch and unpublished posts from boarding endpoints', function (CommitType $type, CommitStatus $status): void {
    $commit = Commit::factory()->create(['type' => $type, 'status' => $status]);
    $viewer = User::factory()->create();
    $this->getJson(route('commit-launch.show', $commit))->assertNotFound();
    $this->actingAs($viewer)->postJson(route('launch-passenger.store', $commit))->assertNotFound();
    $this->deleteJson(route('launch-passenger.destroy', $commit))->assertNotFound();
    expect(DB::table('launch_passengers')->count())->toBe(0);
})->with([
    [CommitType::Standard, CommitStatus::Approved],
    [CommitType::Launch, CommitStatus::Pending],
    [CommitType::Launch, CommitStatus::Rejected],
]);

it('does not reveal deleted launches', function (): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $commit->delete();
    $this->getJson(route('commit-launch.show', $commit))->assertNotFound();
    $this->actingAs(User::factory()->create())->postJson(route('launch-passenger.store', $commit))->assertNotFound();
});

it('respects blocks with the author in either direction', function (bool $viewerBlocks): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $viewer = User::factory()->create();
    $viewerBlocks ? $viewer->blocking()->attach($commit->user) : $commit->user->blocking()->attach($viewer);

    $this->actingAs($viewer)->getJson(route('commit-launch.show', $commit))->assertNotFound();
    $this->postJson(route('launch-passenger.store', $commit))->assertNotFound();
})->with([true, false]);

it('bounds public avatar payloads and exposes no private account fields', function (): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    foreach (User::factory()->count(20)->create() as $passenger) {
        $commit->launchPassengers()->attach($passenger, ['created_at' => now()]);
    }

    $response = $this->getJson(route('commit-launch.show', $commit))->assertOk()
        ->assertJsonPath('count', 20)->assertJsonCount(16, 'passengers');
    expect(array_keys($response->json('passengers.0')))->toBe(['id', 'handle', 'name', 'avatar_url']);
});

it('leaves blocked and banned people out of the visible crew', function (): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $banned = User::factory()->create(['banned_at' => now()]);
    $visible = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    foreach ([$blocked, $banned, $visible] as $passenger) {
        $commit->launchPassengers()->attach($passenger, ['created_at' => now()]);
    }

    $this->actingAs($viewer)->getJson(route('commit-launch.show', $commit))->assertOk()
        ->assertJsonPath('count', 1)->assertJsonCount(1, 'passengers')->assertJsonPath('passengers.0.id', $visible->id);
});

it('removes passenger records when an account is deleted', function (): void {
    $commit = Commit::factory()->create(['type' => CommitType::Launch]);
    $passenger = User::factory()->create();
    $commit->launchPassengers()->attach($passenger, ['created_at' => now()]);
    $passenger->delete();
    expect(DB::table('launch_passengers')->count())->toBe(0);
});
