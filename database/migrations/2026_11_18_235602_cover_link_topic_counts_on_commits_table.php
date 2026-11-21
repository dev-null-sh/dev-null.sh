<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('drop index concurrently if exists commits_link_preview_id_covering_index');
        DB::statement('create index concurrently commits_link_preview_id_covering_index on commits (link_preview_id) include (status, user_id, author_id, deleted_at)');
        DB::statement('drop index concurrently if exists commits_link_preview_id_index');
    }
};
