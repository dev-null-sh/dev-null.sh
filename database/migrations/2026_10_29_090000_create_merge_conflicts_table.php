<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merge_conflicts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commit_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('ours_id')->constrained('commits')->cascadeOnDelete();
            $table->foreignUuid('theirs_id')->constrained('commits')->cascadeOnDelete();
            $table->timestamp('closes_at');
            $table->timestamps();
        });

        Schema::create('merge_conflict_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('merge_conflict_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('side');
            $table->timestamps();

            $table->unique(['merge_conflict_id', 'user_id']);
            $table->index(['user_id', 'id']);
        });
    }
};
