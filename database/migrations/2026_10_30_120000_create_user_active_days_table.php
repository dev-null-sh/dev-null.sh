<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each day a person was seen, a date with no time, so retention can be read day by day for each signup cohort:
     * last_seen_at only keeps the latest day, and active_user_counts has no user ids. Only admins see it, as
     * aggregates, and the person in their export. DeleteUser erases the rows with the account. Everyone's last day is
     * copied in: people already seen today aren't recorded again until tomorrow, so today would be missing for them.
     */
    public function up(): void
    {
        Schema::create('user_active_days', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained();
            $table->date('seen_at');

            $table->primary(['user_id', 'seen_at']);
        });

        DB::table('user_active_days')->insertUsing(
            ['user_id', 'seen_at'],
            DB::table('users')->whereNotNull('last_seen_at')->select(['id', 'last_seen_at']),
        );
    }
};
