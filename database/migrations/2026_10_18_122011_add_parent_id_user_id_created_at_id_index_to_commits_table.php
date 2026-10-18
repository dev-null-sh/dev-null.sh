<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Threads are followed one step at a time through the author's earliest reply; top-level commits never continue one.
     */
    public function up(): void
    {
        $partial = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? '' : 'where deleted_at is null and parent_id is not null';

        DB::statement("create index commits_parent_id_user_id_created_at_id_index on commits (parent_id, user_id, created_at, id) {$partial}");
    }
};
