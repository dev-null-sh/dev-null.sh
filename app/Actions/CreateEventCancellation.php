<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

final readonly class CreateEventCancellation
{
    public function __construct(private CreateEventCancellationNotifications $createEventCancellationNotifications)
    {
        //
    }

    public function handle(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $event->update(['cancelled_at' => now()]);

            $this->createEventCancellationNotifications->handle($event);
        });
    }
}
