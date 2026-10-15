<?php

declare(strict_types=1);

use App\Actions\CreateEventAttendance;
use App\Models\Event;
use App\Models\User;

it('records attendance once even when called twice', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $action = resolve(CreateEventAttendance::class);

    $action->handle($user, $event);
    $action->handle($user, $event);

    expect($event->attendees()->count())->toBeOne();
});
