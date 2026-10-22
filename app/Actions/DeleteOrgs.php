<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageImage;
use App\Models\MessageVoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteOrgs
{
    /**
     * Deletes the orgs with everything sent in them. The database cascades their messages, images, and voice notes,
     * and the files are erased once the deletion commits, so a rollback keeps them.
     *
     * @param  Builder<Conversation>  $orgs
     */
    public function handle(Builder $orgs): void
    {
        $messageIds = Message::query()->whereIn('conversation_id', (clone $orgs)->select('id'))->select('id');

        $paths = [
            ...MessageImage::query()->whereIn('message_id', $messageIds)->get(['path'])->map(fn (MessageImage $image): string => $image->path),
            ...MessageVoice::query()->whereIn('message_id', $messageIds)->get(['path'])->map(fn (MessageVoice $voice): string => $voice->path),
        ];

        $orgs->delete();

        DB::afterCommit(fn (): bool => Storage::disk()->delete($paths));
    }
}
