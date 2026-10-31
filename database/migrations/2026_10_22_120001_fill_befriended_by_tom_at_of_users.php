<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tom follows everyone he befriended until they block him.
     */
    public function up(): void
    {
        $tom = DB::table('users')->where('github_id', CreateTomFriendship::GITHUB_ID)->select('id');

        DB::table('users')
            ->whereIn('id', DB::table('follows')->whereIn('follower_id', $tom)->select('followed_id'))
            ->orWhereIn('id', DB::table('blocks')->whereIn('blocked_id', $tom)->select('blocker_id'))
            ->update(['befriended_by_tom_at' => now()]);
    }
};
