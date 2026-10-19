<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_commits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('body', 280)->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->index(['submitted_at', 'canceled_at', 'scheduled_at']);
            $table->index(['user_id', 'created_at']);
        });
    }
};
