<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minesweeper_games', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->json('mines');
            $table->json('revealed');
            $table->unsignedSmallInteger('exploded_cell')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('finished_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(['commit_id', 'user_id', 'created_at']);
            $table->index(['commit_id', 'status', 'duration_ms']);
            $table->index(['user_id', 'status', 'duration_ms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minesweeper_games');
    }
};
