<?php

declare(strict_types=1);

use App\Actions\CreateLinkPreviewJob;
use App\Models\LinkPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('does not queue a preview that completed before its dispatch callback runs', function (): void {
    Queue::fake();
    $preview = LinkPreview::factory()->fetched()->create();

    resolve(CreateLinkPreviewJob::class)->handle($preview);

    Queue::assertNothingPushed();
});

it('does not queue a preview deleted before its dispatch callback runs', function (): void {
    Queue::fake();
    $preview = LinkPreview::factory()->create();
    $preview->delete();

    resolve(CreateLinkPreviewJob::class)->handle($preview);

    Queue::assertNothingPushed();
});

it('records a transient failure when the queue driver rejects a preview', function (): void {
    config(['queue.default' => 'database']);
    Queue::swap(Queue::fake()->queue);
    DB::connection()->beforeExecuting(function (string $query): void {
        throw_if(str_starts_with($query, 'insert into "jobs"'), RuntimeException::class, 'Queue unavailable');
    });
    $preview = LinkPreview::factory()->create();

    expect(fn () => resolve(CreateLinkPreviewJob::class)->handle($preview))
        ->toThrow(RuntimeException::class, 'Queue unavailable');

    expect($preview->refresh())
        ->fetched_at->not->toBeNull()
        ->unfurl_failed_at->not->toBeNull()
        ->publication_unfurl_retries->toBe(0);
});
