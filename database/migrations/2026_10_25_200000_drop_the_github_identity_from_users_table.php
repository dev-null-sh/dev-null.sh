<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The GitHub identity of users lives on their profile now. The users the previous code signed up during its deploy
     * get theirs first, then every user must have a profile, and the old copy on the users table goes.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO profiles (id, type, github_id, handle, name, avatar_url, bio, location, website, github_synced_at, created_at, updated_at)
            SELECT gen_random_uuid(), 'user', github_id, username, name, avatar_url, bio, location, website, github_synced_at, created_at, updated_at
            FROM users
            WHERE NOT EXISTS (SELECT 1 FROM profiles WHERE profiles.github_id = users.github_id)
            ON CONFLICT (github_id) DO NOTHING
            SQL);

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('github_id')->references('github_id')->on('profiles');
            $table->dropColumn(['username', 'name', 'avatar_url', 'bio', 'location', 'website', 'github_synced_at']);
        });
    }
};
