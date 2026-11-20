<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('automatic_streak_shields')->default(true);
            // Start at deployment, rather than notifying years of historical events.
            $table->timestamp('streak_shield_notifications_started_at')->useCurrent();
        });

        Schema::create('streak_shield_preferences', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('effective_on');
            $table->boolean('enabled');
            $table->primary(['user_id', 'effective_on']);
        });
    }
};
