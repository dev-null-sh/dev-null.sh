<?php

declare(strict_types=1);

use App\Actions\CreateCommitSeries;
use App\Enums\CommitStatus;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('chains each part as a reply to the part before it', function (): void {
    $user = User::factory()->create();

    $parts = resolve(CreateCommitSeries::class)->handle($user, ['First part', 'Second part', 'Third part']);

    expect($parts)->toHaveCount(3)
        ->and($parts->pluck('body')->all())->toBe(['First part', 'Second part', 'Third part'])
        ->and($parts->pluck('parent_id')->all())->toBe([null, $parts[0]->id, $parts[1]->id])
        ->and($parts->pluck('series_position')->all())->toBe([1, 2, 3])
        ->and($parts->pluck('series_size')->unique()->all())->toBe([3])
        ->and($parts->pluck('series_id')->unique())->toHaveCount(1)
        ->and($parts[0]->series_id)->toBeString()
        ->and($parts->every(fn (Commit $part): bool => $part->user_id === $user->id))->toBeTrue()
        ->and(Commit::query()->count())->toBe(3);
});

it('creates a series pending review', function (): void {
    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), ['First part', 'Second part']);

    expect($parts->pluck('status')->unique()->all())->toBe([CommitStatus::Pending]);
});

it('approves a series of an admin right away without notifying them about their own parts', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(['username' => 'nunomaduro']), ['First part', 'Second part']);

    expect($parts->pluck('status')->unique()->all())->toBe([CommitStatus::Approved])
        ->and(Notification::query()->count())->toBe(0);
});

it('stores emoji shortcodes and attaches a link preview for each part', function (): void {
    Queue::fake();

    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), [
        'shipped it :heart :rocket:',
        'notes at https://example.com/notes',
    ]);

    expect($parts[0]->body)->toBe('shipped it ❤️ 🚀')
        ->and($parts[0]->link_preview_id)->toBeNull()
        ->and($parts[1]->linkPreview?->url)->toBe('https://example.com/notes');

    Queue::assertPushed(UnfurlLink::class, 1);
});
