<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explore_topics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 10);
            $table->string('key', 160);
            $table->string('title', 240);
            $table->json('categories');
            $table->text('summary')->nullable();
            $table->timestamp('summary_generated_at')->nullable();
            $table->timestamp('summary_dirty_at')->nullable();
            $table->unsignedBigInteger('summary_revision')->default(0);
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'key']);
        });

        Schema::create('explore_topic_aliases', function (Blueprint $table): void {
            $table->string('alias', 160)->primary();
            $table->foreignUuid('topic_id')->constrained('explore_topics')->cascadeOnDelete();
        });

        Schema::create('explore_commit_topic', function (Blueprint $table): void {
            $table->foreignUuid('topic_id')->constrained('explore_topics')->cascadeOnDelete();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->primary(['topic_id', 'commit_id']);
            $table->index('commit_id');
        });

        Schema::create('explore_topic_subject', function (Blueprint $table): void {
            $table->foreignUuid('story_id')->constrained('explore_topics')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('explore_topics')->cascadeOnDelete();
            $table->primary(['story_id', 'subject_id']);
        });

        Schema::create('explore_summary_sources', function (Blueprint $table): void {
            $table->foreignUuid('topic_id')->constrained('explore_topics')->cascadeOnDelete();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->primary(['topic_id', 'commit_id']);
            $table->index('commit_id');
        });

        Schema::create('explore_classification_work', function (Blueprint $table): void {
            $table->foreignUuid('commit_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamp('eligible_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
        });

        Schema::create('explore_preferences', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('preferences');
        });

        Schema::create('explore_hidden_topics', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('topic_id')->constrained('explore_topics')->cascadeOnDelete();
            $table->primary(['user_id', 'topic_id']);
        });

        Schema::create('explore_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('topic_id')->constrained('explore_topics')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->timestamp('created_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->unique(['user_id', 'topic_id']);
        });
    }
};
