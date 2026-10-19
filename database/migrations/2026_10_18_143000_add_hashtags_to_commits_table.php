<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE commits ADD COLUMN hashtags jsonb');
        DB::statement('CREATE INDEX commits_hashtags_gin_index ON commits USING GIN (hashtags jsonb_path_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS commits_hashtags_gin_index');

        Schema::table('commits', function ($table): void {
            $table->dropColumn('hashtags');
        });
    }
};
