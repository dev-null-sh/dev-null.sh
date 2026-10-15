<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\EventDetails;
use App\Enums\NotificationType;
use App\Models\Event;
use App\Services\EmojiShortcodes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateEvent
{
    private const array SCHEDULE_COLUMNS = ['starts_at', 'ends_at', 'timezone', 'format', 'venue', 'address', 'platform', 'join_url'];

    public function __construct(
        private UpdateEventCover $updateEventCover,
        private CreateEventNotifications $createEventNotifications,
        private EmojiShortcodes $emojiShortcodes,
    ) {
        //
    }

    /**
     * Only a real change to when or where moves the sequence, marks the event updated, and notifies people going.
     */
    public function handle(Event $event, EventDetails $details, ?UploadedFile $cover = null): void
    {
        $replacedCover = DB::transaction(function () use ($event, $details, $cover): ?string {
            $event->fill([
                ...$details->toAttributes(),
                'venue' => $details->venue === null ? null : $this->emojiShortcodes->replace($details->venue),
            ]);

            $isRescheduled = $event->isDirty(self::SCHEDULE_COLUMNS);

            if ($isRescheduled) {
                $event->fill([
                    'sequence' => $event->sequence + 1,
                    'details_changed_at' => now(),
                ]);
            }

            $event->save();

            $replacedCover = $cover instanceof UploadedFile ? $this->updateEventCover->handle($event, $cover) : null;

            if ($isRescheduled) {
                $this->createEventNotifications->handle($event, NotificationType::EventChanged);
            }

            return $replacedCover;
        });

        if ($replacedCover !== null) {
            Storage::disk()->delete($replacedCover);
        }
    }
}
