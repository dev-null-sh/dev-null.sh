<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commits', 'branch')) {
            Schema::table('commits', function (Blueprint $table): void {
                $table->string('branch', 50)->nullable()->after('mentions');
            });
        }

        if (Schema::hasColumn('commits', 'hashtags')) {
            DB::statement(<<<'SQL'
                update commits
                set branch = left(hashtags->>0, 50)
                where hashtags is not null
                  and jsonb_typeof(hashtags) = 'array'
                  and jsonb_array_length(hashtags) > 0
                  and branch is null
                SQL);

            DB::statement('drop index if exists commits_hashtags_gin_index');

            Schema::table('commits', function (Blueprint $table): void {
                $table->dropColumn('hashtags');
            });
        }

        $indexes = collect(Schema::getIndexes('commits'))->pluck('name');

        if (! $indexes->contains('commits_branch_index')) {
            Schema::table('commits', function (Blueprint $table): void {
                $table->index('branch');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('commits', 'hashtags')) {
            Schema::table('commits', function (Blueprint $table): void {
                $table->jsonb('hashtags')->nullable()->after('mentions');
            });
        }

        if (Schema::hasColumn('commits', 'branch')) {
            DB::statement(<<<'SQL'
                update commits
                set hashtags = case
                    when branch is null then '[]'::jsonb
                    else jsonb_build_array(branch)
                end
                SQL);

            $indexes = collect(Schema::getIndexes('commits'))->pluck('name');

            if ($indexes->contains('commits_branch_index')) {
                Schema::table('commits', function (Blueprint $table): void {
                    $table->dropIndex(['branch']);
                });
            }

            Schema::table('commits', function (Blueprint $table): void {
                $table->dropColumn('branch');
            });
        }

        DB::statement('create index if not exists commits_hashtags_gin_index on commits using gin (hashtags jsonb_path_ops)');
    }
};
