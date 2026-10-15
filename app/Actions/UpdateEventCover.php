<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Event;
use App\Services\Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final readonly class UpdateEventCover
{
    public function __construct(private Images $images)
    {
        //
    }

    /**
     * Stores the cover as WebP and returns the path of the one it replaced, which the caller erases once its
     * transaction has committed, so a rollback never points the event at a deleted file.
     */
    public function handle(Event $event, UploadedFile $cover): ?string
    {
        $image = $this->images->optimize($cover, 'event.cover');
        $previous = $event->cover_path;

        $event->update([
            'cover_path' => $image->storeAs('event-covers', Str::uuid7()->toString().'.webp'),
            'cover_width' => $image->width(),
            'cover_height' => $image->height(),
        ]);

        return $previous;
    }
}
