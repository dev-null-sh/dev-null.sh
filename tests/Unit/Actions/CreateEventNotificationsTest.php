<?php

declare(strict_types=1);

use App\Actions\CreateEventNotifications;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;

it('notifies every attendee but the host once per change', function (): void {
    $event = Event::factory()->create(['sequence' => 1]);
    $event->attendees()->attach([...User::factory()->count(2)->create()->modelKeys(), $event->commit->user_id]);

    resolve(CreateEventNotifications::class)->handle($event, NotificationType::EventChanged);
    resolve(CreateEventNotifications::class)->handle($event, NotificationType::EventChanged);

    expect(Notification::query()->count())->toBe(2)
        ->and(Notification::query()->pluck('type')->unique()->all())->toBe([NotificationType::EventChanged]);
});

it('notifies again for a later change or a cancellation', function (): void {
    $event = Event::factory()->create(['sequence' => 1]);
    $event->attendees()->attach(User::factory()->create());

    resolve(CreateEventNotifications::class)->handle($event, NotificationType::EventChanged);
    $event->update(['sequence' => 2]);
    resolve(CreateEventNotifications::class)->handle($event, NotificationType::EventCancelled);

    expect(Notification::query()->count())->toBe(2);
});

it('notifies nobody while the commit is not approved', function (): void {
    $event = Event::factory()->for(Commit::factory()->pending())->create();
    $event->attendees()->attach(User::factory()->create());

    resolve(CreateEventNotifications::class)->handle($event, NotificationType::EventChanged);

    expect(Notification::query()->count())->toBe(0);
});
