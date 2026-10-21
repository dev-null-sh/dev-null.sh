<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bans go by GitHub account rather than by user, so deleting the account and signing up again doesn't lift one.
     * The user carries when they were banned too, so every request can tell without looking the ban up.
     */
    public function up(): void
    {
        Schema::create('bans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('github_id')->unique();
            $table->string('reason');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('banned_at')->nullable();
        });
    }
};
