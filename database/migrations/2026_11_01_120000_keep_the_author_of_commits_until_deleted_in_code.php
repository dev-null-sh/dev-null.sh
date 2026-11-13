<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Each statement takes its own lock, as briefly as it can.
     */
    public $withinTransaction = false;

    /**
     * Deleting a profile no longer clears the author of its commits: `DeleteUser` and `DeleteOrganization` clear it in
     * code first, and a deletion that misses one now fails instead of leaving a live commit without its author. Clearing
     * it in the database was only there while the code that posted commits without an author was still running.
     *
     * The foreign key is replaced in one statement, so commits are never left without one, and checked apart from it,
     * so the scan doesn't hold up new commits. Safe to run again.
     */
    public function up(): void
    {
        DB::statement('alter table commits drop constraint if exists commits_author_id_foreign, add constraint commits_author_id_foreign foreign key (author_id) references profiles (id) not valid');
        DB::statement('alter table commits validate constraint commits_author_id_foreign');
    }
};
