<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logs and profiles list top-level commits that are not deleted, newest or hottest first, so these indexes leave
     * out the replies and tombstones the queries would otherwise skip over. Replies all score 0, so the hot score
     * index over every commit is replaced rather than kept beside them.
     */
    public function up(): void
    {
        $topLevel = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? '' : 'where parent_id is null and deleted_at is null';

        DB::statement("create index commits_top_level_created_at_id_index on commits (created_at, id) {$topLevel}");
        DB::statement("create index commits_top_level_hot_score_id_index on commits (hot_score, id) {$topLevel}");
        DB::statement("create index commits_top_level_user_id_created_at_id_index on commits (user_id, created_at, id) {$topLevel}");

        Schema::table('commits', function (Blueprint $table): void {
            $table->dropIndex(['hot_score', 'id']);
            $table->index('link_preview_id');
        });
    }
};
