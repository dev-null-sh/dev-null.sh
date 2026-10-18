<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteSponsorsOrg
{
    /**
     * Deletes the org with its history and images, and forgets the maintainer's sponsors, which were only kept for it.
     */
    public function handle(User $maintainer): void
    {
        $imagePaths = DB::transaction(function () use ($maintainer): array {
            $org = Conversation::sponsorsOrgOf($maintainer);

            $imagePaths = MessageImage::query()
                ->whereIn('message_id', Message::query()->where('conversation_id', $org?->id)->select('id'))
                ->pluck('path')
                ->all();

            $org?->delete();

            $maintainer->sponsorshipsAsMaintainer()->delete();

            return $imagePaths;
        });

        Storage::disk()->delete($imagePaths);
    }
}
