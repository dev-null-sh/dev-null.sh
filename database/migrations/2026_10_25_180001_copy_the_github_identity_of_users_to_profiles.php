<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copies the GitHub identity of the users who have no profile yet, so it can run again once the code still writing
     * to the users table during a deploy is gone.
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
    }
};
