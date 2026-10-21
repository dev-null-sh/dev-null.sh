<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ExploreTopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateExploreFeedback
{
    public function handle(User $user, ExploreTopic $topic, string $reason): void
    {
        DB::transaction(function () use ($user, $topic, $reason): void {
            DB::table('explore_hidden_topics')->insertOrIgnore(['user_id' => $user->id, 'topic_id' => $topic->id]);
            if ($reason !== 'not_interested') {
                DB::table('explore_reports')->insertOrIgnore([
                    'id' => Str::uuid7()->toString(), 'user_id' => $user->id, 'topic_id' => $topic->id,
                    'reason' => $reason, 'created_at' => now(),
                ]);
            }
        });
    }
}
