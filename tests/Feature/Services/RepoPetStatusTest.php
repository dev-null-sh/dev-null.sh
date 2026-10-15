<?php

declare(strict_types=1);

use App\Jobs\RefreshRepoPet;
use App\Services\RepoPetStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/** @return array{repository: string, repository_id: int, workflow_id: int, workflow: string} */
function petStatusConfiguration(): array
{
    return ['repository' => 'acme/widget', 'repository_id' => 42, 'workflow_id' => 7, 'workflow' => 'Tests'];
}

it('leaves an unconfigured pet absent without queuing a refresh', function (): void {
    Queue::fake([RefreshRepoPet::class]);

    $pet = resolve(RepoPetStatus::class)->get(null);

    expect($pet)->toBeNull();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('queues only one refresh when multiple viewers request the same uncached pet', function (): void {
    $configuration = petStatusConfiguration();
    Queue::fake([RefreshRepoPet::class]);

    $pet = resolve(RepoPetStatus::class)->get($configuration);
    resolve(RepoPetStatus::class)->get($configuration);

    expect($pet)->toMatchArray(['repository' => 'acme/widget', 'mood' => 'sleeping', 'checked_at' => null]);
    Queue::assertPushed(RefreshRepoPet::class, 1);
    Http::assertNothingSent();
});

it('returns a fresh cached snapshot without GitHub calls or more jobs', function (): void {
    $configuration = petStatusConfiguration();
    Cache::put(RepoPetStatus::key($configuration), [
        'mood' => 'happy', 'message' => 'Tests passed.', 'url' => 'https://github.com/acme/widget/actions/runs/100',
        'checked_at' => now()->toIso8601String(),
    ], 120);
    Queue::fake([RefreshRepoPet::class]);

    $pet = resolve(RepoPetStatus::class)->get($configuration);

    expect($pet)->toMatchArray(['mood' => 'happy', 'message' => 'Tests passed.']);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('stops displaying an expired green result while a new check is queued', function (): void {
    $configuration = petStatusConfiguration();
    Cache::put(RepoPetStatus::key($configuration), [
        'mood' => 'happy', 'message' => 'Tests passed.', 'url' => 'https://github.com/acme/widget/actions/runs/100',
        'checked_at' => now()->toIso8601String(),
    ], 120);
    Queue::fake([RefreshRepoPet::class]);
    $this->travel(121)->seconds();

    $pet = resolve(RepoPetStatus::class)->get($configuration);

    expect($pet)->toMatchArray(['mood' => 'sleeping', 'checked_at' => null]);
    Queue::assertPushed(RefreshRepoPet::class, 1);
    Http::assertNothingSent();
});

it('caches an unavailable result for two minutes to avoid repeated failing checks', function (): void {
    $configuration = petStatusConfiguration();
    Http::fake(['https://api.github.com/repos/acme/widget' => Http::response([], 403)]);
    Queue::fake([RefreshRepoPet::class]);

    new RefreshRepoPet($configuration)->handle(resolve(RepoPetStatus::class));
    $pet = resolve(RepoPetStatus::class)->get($configuration);
    new RefreshRepoPet($configuration)->handle(resolve(RepoPetStatus::class));

    expect($pet)->toMatchArray(['mood' => 'sleeping', 'checked_at' => now()->toIso8601String()]);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);

    $this->travel(121)->seconds();
    resolve(RepoPetStatus::class)->get($configuration);
    Queue::assertPushed(RefreshRepoPet::class, 1);
});

it('keeps an old selection refresh from replacing the newly selected workflow', function (): void {
    $old = petStatusConfiguration();
    $new = [...$old, 'workflow_id' => 8, 'workflow' => 'Deploy'];
    Queue::fake([RefreshRepoPet::class]);
    resolve(RepoPetStatus::class)->unavailable($old);

    $pet = resolve(RepoPetStatus::class)->get($new);

    expect($pet)->toMatchArray(['workflow' => 'Deploy', 'checked_at' => null]);
    Queue::assertPushed(RefreshRepoPet::class, fn (RefreshRepoPet $job): bool => $job->configuration === $new);
    Http::assertNothingSent();
});

it('backs off after a failed background job', function (): void {
    $configuration = petStatusConfiguration();
    Queue::fake([RefreshRepoPet::class]);

    new RefreshRepoPet($configuration)->failed(new RuntimeException('Worker failed'));
    $pet = resolve(RepoPetStatus::class)->get($configuration);

    expect($pet)->toMatchArray(['mood' => 'sleeping', 'message' => 'Build status is unavailable. Taking a nap.']);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});
