<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Enums\NotificationType;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('may post a series of commits', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), [
            'bodies' => ['Part one of the story', 'Part two of the story', 'Part three of the story'],
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    $parts = $user->commits()->oldest('series_position')->get();

    expect($parts->pluck('body')->all())->toBe(['Part one of the story', 'Part two of the story', 'Part three of the story'])
        ->and($parts->pluck('parent_id')->all())->toBe([null, $parts[0]->id, $parts[1]->id])
        ->and($parts->pluck('series_position')->all())->toBe([1, 2, 3]);
});

it('flashes the first part of the series with its size', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['First part', 'Second part']]);

    $first = $user->commits()->where('series_position', 1)->sole();

    $response->assertInertiaFlash('committed', [
        'id' => $first->id,
        'body' => 'First part',
        'series_size' => 2,
    ]);
});

it('tells users their series is pending review', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['First part', 'Second part']]);

    $response->assertInertiaFlash('toast', [
        'type' => 'info',
        'message' => 'Your series is pending review. Everyone will see it once it is approved.',
    ]);
});

it('publishes a series of an admin right away', function (): void {
    config(['app.admins' => ['admin']]);

    $admin = User::factory()->create(['username' => 'admin']);

    $response = $this->actingAs($admin)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['First part', 'Second part']]);

    $response->assertInertiaFlashMissing('toast');

    expect($admin->commits()->pluck('status')->unique()->all())->toBe([CommitStatus::Approved]);
});

it('requires between 2 and 10 parts', function (array $bodies): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => $bodies]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('bodies');

    expect(Commit::query()->count())->toBe(0);
})->with([
    'none' => [[]],
    'one' => [['Only part']],
    'eleven' => [array_fill(0, 11, 'Some part')],
]);

it('requires a list of parts', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['first' => 'First part', 'second' => 'Second part']]);

    $response->assertSessionHasErrors('bodies');

    expect(Commit::query()->count())->toBe(0);
});

it('validates every part like a commit and posts nothing when one is invalid', function (string $body, string $message): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['A fine first part', $body]]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors(['bodies.1' => $message])
        ->assertSessionDoesntHaveErrors('bodies.0');

    expect(Commit::query()->count())->toBe(0);
})->with([
    'blank' => ['   ', 'The commit field is required.'],
    'too short' => [' ab ', 'The commit field must be at least 3 characters.'],
    'too long' => [str_repeat('a', 281), 'The commit field must not be greater than 280 characters.'],
]);

it('spends one of the 10 commits a minute on each part', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($user)
            ->fromRoute('log.show', Log::Following)
            ->post(route('commit.store'), ['body' => "Commit {$i}"]);
    }

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['Part one', 'Part two', 'Part three', 'Part four', 'Part five']])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
        ]);

    expect($user->commits()->count())->toBe(6);

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['Part one', 'Part two', 'Part three', 'Part four']])
        ->assertSessionHasNoErrors();

    expect($user->commits()->count())->toBe(10);

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'One too many'])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
        ]);

    expect($user->commits()->count())->toBe(10);
});

it('attaches images to the first part of the series', function (): void {
    Storage::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), [
            'bodies' => ['Look at this', 'And a second thought'],
            'images' => [UploadedFile::fake()->image('first.jpg', 40, 30), UploadedFile::fake()->image('second.png', 10, 10)],
        ])
        ->assertSessionHasNoErrors();

    $parts = $user->commits()->with('images')->oldest('series_position')->get();

    expect($parts[0]->images)->toHaveCount(2)
        ->and($parts[1]->images)->toBeEmpty();
});

it('validates the images of a series like those of a commit', function (): void {
    Storage::fake();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), [
            'bodies' => ['First part', 'Second part'],
            'images' => [UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
        ]);

    $response->assertSessionHasErrors('images.0');

    expect(Commit::query()->count())->toBe(0);
});

it('notifies people mentioned in each part once the series is approved', function (): void {
    config(['app.admins' => ['admin']]);

    $mentioned = User::factory()->create(['username' => 'taylor']);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), ['bodies' => ['Paired with @taylor today', 'Thanks again @taylor']]);

    expect($mentioned->notifications()->where('type', NotificationType::Mentioned)->count())->toBe(2);
});

it('requires authentication to post a series', function (): void {
    $response = $this->post(route('commit-series.store'), ['bodies' => ['First part', 'Second part']]);

    $response->assertRedirectToRoute('home');

    expect(Commit::query()->count())->toBe(0);
});

it('uses the chosen card or no card for each part and defaults unchosen parts to their first link', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), [
            'bodies' => [
                'First https://example.com/one and https://example.com/chosen',
                'Second https://example.com/two',
                'Third https://example.com/three and https://example.com/four',
            ],
            'link_preview_urls' => [0 => 'https://example.com/chosen', 1 => ''],
        ])
        ->assertSessionHasNoErrors();

    $parts = $user->commits()->with('linkPreview')->oldest('series_position')->get();

    expect($parts)->toHaveCount(3)
        ->and($parts[0]->linkPreview?->url)->toBe('https://example.com/chosen')
        ->and($parts[1]->link_preview_id)->toBeNull()
        ->and($parts[2]->linkPreview?->url)->toBe('https://example.com/three')
        ->and($parts->pluck('series_position')->all())->toBe([1, 2, 3])
        ->and($parts->pluck('status')->unique()->all())->toBe([CommitStatus::Pending]);

    Queue::assertPushed(UnfurlLink::class, 2);
});

it('keeps a sparse card choice attached to its series part', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), [
            'bodies' => ['First https://example.com/one', 'Second https://example.com/two and https://example.com/chosen'],
            'link_preview_urls' => [1 => 'https://example.com/chosen'],
        ])
        ->assertSessionHasNoErrors();

    $parts = $user->commits()->with('linkPreview')->oldest('series_position')->get();

    expect($parts[0]->linkPreview?->url)->toBe('https://example.com/one')
        ->and($parts[1]->linkPreview?->url)->toBe('https://example.com/chosen');
});

it('rejects a series card that is not in that part without posting any parts', function (mixed $choice): void {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), [
            'bodies' => ['First https://example.com/one', 'Second https://example.com/two'],
            'link_preview_urls' => [1 => $choice],
        ])
        ->assertSessionHasErrors('link_preview_urls.1');

    expect(Commit::query()->count())->toBe(0);

    Queue::assertNothingPushed();
})->with([
    'another part' => ['https://example.com/one'],
    'outside the series' => ['https://example.com/other'],
    'not a string' => [123],
    'nested value' => [['https://example.com/two']],
]);

it('rejects a malformed series body when a card is supplied', function (): void {
    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit-series.store'), [
            'bodies' => ['First part', ['https://example.com/two']],
            'link_preview_urls' => [1 => 'https://example.com/two'],
        ])
        ->assertSessionHasErrors('bodies.1');

    expect(Commit::query()->count())->toBe(0);
});
