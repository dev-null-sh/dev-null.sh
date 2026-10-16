<?php

declare(strict_types=1);

use App\Enums\EventFormat;
use App\Enums\PollDuration;
use App\Enums\StreamPlatform;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function eventPayload(array $overrides = []): array
{
    return [
        'body' => 'The monthly meetup is back.',
        'event' => [
            'title' => 'Async PHP night',
            'starts_at' => now()->addDays(10)->format('Y-m-d').'T19:00',
            'ends_at' => now()->addDays(10)->format('Y-m-d').'T21:30',
            'timezone' => 'Europe/Kyiv',
            'format' => 'onsite',
            'venue' => 'Coworking Hall',
            'address' => '12 Market Square, Lviv',
            ...$overrides,
        ],
    ];
}

it('creates an on-site event with the commit, stored in UTC', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), eventPayload())->assertRedirect()->assertSessionHasNoErrors();

    $event = $user->commits()->sole()->event;
    $expected = now()->addDays(10)->setTimezone('Europe/Kyiv')->setTime(19, 0)->utc();

    expect($event?->title)->toBe('Async PHP night')
        ->and($event?->format)->toBe(EventFormat::Onsite)
        ->and($event?->starts_at->utc()->format('Y-m-d H:i'))->toBe($expected->format('Y-m-d H:i'))
        ->and($event?->timezone)->toBe('Europe/Kyiv')
        ->and($event?->platform)->toBeNull();
});

it('creates an online event and drops on-site fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), eventPayload([
        'format' => 'online',
        'platform' => 'youtube',
        'join_url' => 'https://youtube.com/live/abc',
    ]))->assertSessionHasNoErrors();

    $event = $user->commits()->sole()->event;

    expect($event?->platform)->toBe(StreamPlatform::Youtube)
        ->and($event?->join_url)->toBe('https://youtube.com/live/abc')
        ->and($event?->venue)->toBeNull()
        ->and($event?->address)->toBeNull();
});

it('stores a cover', function (): void {
    Storage::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), eventPayload([
        'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
    ]))->assertSessionHasNoErrors();

    expect($user->commits()->sole()->event?->cover_path)->toStartWith('event-covers/');
});

it('validates the event', function (array $overrides, string $error): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), eventPayload($overrides))->assertSessionHasErrors($error);

    expect(Commit::query()->count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'event.title'],
    'title too long' => [['title' => str_repeat('a', 121)], 'event.title'],
    'bad time format' => [['starts_at' => 'tomorrow'], 'event.starts_at'],
    'in the past' => [['starts_at' => now()->subDay()->format('Y-m-d').'T10:00', 'ends_at' => now()->subDay()->format('Y-m-d').'T11:00'], 'event.starts_at'],
    'ends before it starts' => [['ends_at' => now()->addDays(10)->format('Y-m-d').'T18:00'], 'event.ends_at'],
    'longer than 7 days' => [['ends_at' => now()->addDays(18)->format('Y-m-d').'T19:00'], 'event.ends_at'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'event.timezone'],
    'a zone browsers cannot show' => [['timezone' => 'Factory'], 'event.timezone'],
    'unknown format' => [['format' => 'telepathy'], 'event.format'],
    'on-site without venue' => [['venue' => ''], 'event.venue'],
    'on-site without address' => [['address' => ''], 'event.address'],
    'online without platform' => [['format' => 'online', 'join_url' => 'https://x.test'], 'event.platform'],
    'online without link' => [['format' => 'online', 'platform' => 'zoom'], 'event.join_url'],
    'plain http link' => [['format' => 'online', 'platform' => 'zoom', 'join_url' => 'http://zoom.us/j/1'], 'event.join_url'],
    'javascript link' => [['format' => 'online', 'platform' => 'other', 'join_url' => 'javascript:alert(1)'], 'event.join_url'],
    'svg cover' => [['cover' => UploadedFile::fake()->create('cover.svg', 1, 'image/svg+xml')], 'event.cover'],
]);

it('refuses an event on a reply or alongside images or a poll', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), [...eventPayload(), 'parent_id' => $parent->id])
        ->assertSessionHasErrors('event');

    $this->actingAs($user)->post(route('commit.store'), [...eventPayload(), 'images' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertSessionHasErrors('event');

    $this->actingAs($user)->post(route('commit.store'), [...eventPayload(), 'poll' => ['options' => ['First', 'Second'], 'duration' => PollDuration::OneDay->value]])
        ->assertSessionHasErrors('event');

    expect(Commit::query()->whereNotNull('parent_id')->count())->toBe(0);
});

it('keeps local wall time across daylight saving changes', function (string $local, string $utc): void {
    $this->travelTo('2027-03-01 00:00:00');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), eventPayload([
        'starts_at' => $local,
        'ends_at' => mb_substr($local, 0, 11).'23:00',
    ]))->assertSessionHasNoErrors();

    expect($user->commits()->sole()->event?->starts_at->utc()->format('Y-m-d H:i'))->toBe($utc);
})->with([
    'a time that does not exist' => ['2027-03-28T03:30', '2027-03-28 01:30'],
    'a time that happens twice' => ['2027-10-31T03:30', '2027-10-31 01:30'],
]);

it('accepts the legacy time zone names browsers still report', function (string $timezone): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), eventPayload(['timezone' => $timezone]))->assertSessionHasNoErrors();

    expect($user->commits()->sole()->event?->timezone)->toBe($timezone);
})->with(['Europe/Kiev', 'Asia/Calcutta']);

it('ignores an empty event field instead of failing', function (mixed $event): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Just a commit.', 'event' => $event])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole()->event)->toBeNull();
})->with([
    'an empty string' => [''],
    'null' => [null],
    'an empty list' => [[]],
]);
