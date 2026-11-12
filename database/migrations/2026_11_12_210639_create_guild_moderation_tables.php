<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_moderator_removals', function (Blueprint $table): void {
            $table->string('github_id');
            $table->string('handle', 60);
            $table->timestamp('created_at');
            $table->primary(['github_id', 'handle']);
        });

        Schema::create('guild_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->text('details');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->unique(['guild_id', 'reporter_id']);
            $table->index('reporter_id');
        });
    }
};
