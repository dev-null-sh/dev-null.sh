<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ExploreTopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteExploreHiddenTopic
{
    public function handle(User $user, ExploreTopic $topic): void
    {
        DB::table('explore_hidden_topics')->where('user_id', $user->id)->where('topic_id', $topic->id)->delete();
    }
}
