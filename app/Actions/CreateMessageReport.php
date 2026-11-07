<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Queries\AdminsQuery;
use Illuminate\Support\Facades\DB;

final readonly class CreateMessageReport
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(User $user, Message $message, string $reason, ?string $details): void
    {
        DB::transaction(function () use ($user, $message, $reason, $details): void {
            // Match account deletion's lock order and recheck models that may have been deleted since the request began.
            User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            Message::query()->whereKey($message->id)->sharedLock()->firstOrFail();

            $report = MessageReport::query()->createOrFirst([
                'message_id' => $message->id,
                'reporter_id' => $user->id,
            ], [
                'reason' => $reason,
                'details' => $details,
            ]);

            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation, ['message_reports' => [$report->id]]));
        });
    }
}
