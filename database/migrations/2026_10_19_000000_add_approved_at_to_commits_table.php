<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve the existing order for historical commits, while indexing the
     * approval-aware ordering of new commits on PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable();
        });

        DB::table('commits')
            ->where('status', CommitStatus::Approved->value)
            ->update(['approved_at' => DB::raw('created_at')]);

        DB::statement('create index commits_top_level_feed_at_id_index on commits ((coalesce(approved_at, created_at)), id) where parent_id is null and deleted_at is null');
    }
};
