<?php

declare(strict_types=1);

use App\Enums\PollDuration;
use App\Jobs\AnnounceClosedPolls;
use App\Models\Notification;
use App\Models\Poll;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('announces the polls that closed within the last hour on its first run', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
    $closed = Poll::factory()->sequence(
        ['duration' => PollDuration::OneHour, 'opened_at' => '2026-10-20 10:30:00'],
        ['duration' => PollDuration::SevenDays, 'opened_at' => '2026-10-13 12:00:00'],
    )->count(2)->create();
    Poll::factory()->sequence(
        ['duration' => PollDuration::OneHour, 'opened_at' => '2026-10-20 10:00:00'],
        ['duration' => PollDuration::OneHour, 'opened_at' => '2026-10-20 11:30:00'],
        ['duration' => PollDuration::OneDay, 'opened_at' => '2026-10-19 12:00:01'],
    )->count(3)->create();
    Poll::factory()->unopened()->create();

    dispatch_sync(new AnnounceClosedPolls());

    expect(Notification::query()->pluck('commit_id')->all())->toEqualCanonicalizing($closed->pluck('commit_id')->all());
});

it('picks up where the previous run left off, however long ago', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
    $poll = Poll::factory()->create(['duration' => PollDuration::OneHour, 'opened_at' => '2026-10-20 11:30:00']);
    dispatch_sync(new AnnounceClosedPolls());

    $this->travelTo(CarbonImmutable::parse('2026-10-20 15:00:00'));
    dispatch_sync(new AnnounceClosedPolls());

    expect(Notification::query()->sole()->commit_id)->toBe($poll->commit_id);
});

it('is dispatched every minute', function (): void {
    $event = collect(resolve(Schedule::class)->events())
        ->sole(fn (Event $event): bool => $event->description === AnnounceClosedPolls::class);

    expect($event)->toBeInstanceOf(CallbackEvent::class)
        ->expression->toBe('* * * * *');
});
