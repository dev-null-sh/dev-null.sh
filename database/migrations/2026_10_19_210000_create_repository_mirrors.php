<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_mirrors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('repository');
            $table->string('repository_id');
            $table->unique(['user_id', 'repository_id']);
            $table->string('branch');
            $table->boolean('private');
            $table->text('token');
            $table->boolean('mirror_commits')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('repository_reactions', function (Blueprint $table): void {
            $table->foreignUuid('repository_mirror_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->text('flavor');
            $table->timestamps();
            $table->primary(['repository_mirror_id', 'commit_id']);
        });
        Schema::create('repository_mirror_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_mirror_id')->constrained()->cascadeOnDelete();
            $table->text('payload');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['repository_mirror_id', 'delivered_at', 'id'], 'mirror_pending_events');
        });
    }
};
