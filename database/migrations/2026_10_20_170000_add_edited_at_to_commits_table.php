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
        Schema::table('commits', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('amendment_locked_at')->nullable();
            $table->uuid('revision_id')->nullable();
        });

        Schema::create('commit_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('mentions')->nullable();
            $table->timestamp('created_at');
        });

        DB::statement("UPDATE commits SET amendment_locked_at = CURRENT_TIMESTAMP WHERE created_at > CURRENT_TIMESTAMP - INTERVAL '15 minutes' AND EXISTS (SELECT 1 FROM commits AS interaction WHERE interaction.parent_id = commits.id OR interaction.quoted_commit_id = commits.id)");
    }
};
