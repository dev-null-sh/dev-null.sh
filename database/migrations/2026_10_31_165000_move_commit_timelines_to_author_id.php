<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Batches lock only their own commits, and indexes are built without locking the table.
     */
    public $withinTransaction = false;

    /**
     * The commits the code still running during the first fill posted without an author are filled again, now that
     * every commit is posted with one, so the timelines of a profile's commits and threads can move to the author, and
     * their indexes with them. A build that fails leaves an invalid index behind, so each is dropped first, for the
     * migration to run again.
     */
    public function up(): void
    {
        while (DB::update('update commits set author_id = user_id where id in (select id from commits where author_id is null and user_id is not null limit 1000)') > 0) {
            //
        }

        DB::statement('drop index concurrently if exists commits_author_id_created_at_index');
        DB::statement('create index concurrently commits_author_id_created_at_index on commits (author_id, created_at) include (status, deleted_at)');
        DB::statement('drop index concurrently if exists commits_top_level_author_id_created_at_id_index');
        DB::statement('create index concurrently commits_top_level_author_id_created_at_id_index on commits (author_id, created_at, id) where parent_id is null and deleted_at is null');
        DB::statement('drop index concurrently if exists commits_parent_id_author_id_created_at_id_index');
        DB::statement('create index concurrently commits_parent_id_author_id_created_at_id_index on commits (parent_id, author_id, created_at, id) where deleted_at is null and parent_id is not null');
        DB::statement('drop index concurrently if exists commits_root_id_author_id_index');
        DB::statement('create index concurrently commits_root_id_author_id_index on commits (root_id, author_id)');
        DB::statement('drop index concurrently if exists commits_top_level_user_id_created_at_id_index');
        DB::statement('drop index concurrently if exists commits_parent_id_user_id_created_at_id_index');
        DB::statement('drop index concurrently if exists commits_root_id_user_id_index');
    }
};
