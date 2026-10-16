<?php

declare(strict_types=1);

use App\Actions\UpdateEventCover;
use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Pest\Expectation;

beforeEach(function (): void {
    Storage::fake();
});

it('stores the cover as webp and records its size', function (): void {
    $event = Event::factory()->create();

    resolve(UpdateEventCover::class)->handle($event, UploadedFile::fake()->image('cover.jpg', 1920, 1080));

    $event->refresh();

    expect($event->cover_path)->toStartWith('event-covers/')->toEndWith('.webp')
        ->and([$event->cover_width, $event->cover_height])->toBe([1600, 900])
        ->and(getimagesizefromstring((string) Storage::disk()->get((string) $event->cover_path))['mime'] ?? null)->toBe('image/webp');
});

it('returns the replaced cover and leaves its file for the caller to erase', function (): void {
    $event = Event::factory()->create();
    $action = resolve(UpdateEventCover::class);

    expect($action->handle($event, UploadedFile::fake()->image('first.jpg', 400, 225)))->toBeNull();

    $first = (string) $event->refresh()->cover_path;

    expect($action->handle($event, UploadedFile::fake()->image('second.jpg', 400, 225)))->toBe($first);

    Storage::disk()->assertExists($first);
    Storage::disk()->assertExists((string) $event->refresh()->cover_path);
});

it('refuses files that are not images under the cover key', function (): void {
    $event = Event::factory()->create();

    expect(fn () => resolve(UpdateEventCover::class)->handle($event, UploadedFile::fake()->create('cover.svg', 1, 'image/svg+xml')))
        ->toThrow(fn (ValidationException $exception): Expectation => expect($exception->errors())->toHaveKey('event.cover'));
});
