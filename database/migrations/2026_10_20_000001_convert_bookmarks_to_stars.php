<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            insert into stars (user_id, commit_id, created_at, updated_at)
            select user_id, commit_id, created_at, updated_at from bookmarks
            on conflict (user_id, commit_id) do nothing
            SQL);
    }
};
