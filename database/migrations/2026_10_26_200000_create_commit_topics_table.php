<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commits remember the topics they reference, like the GitHub organizations they mention, by the lowercase
     * reference written in the body, so a mention links to what it meant when it was written, even after a rename.
     * The primary key loads the topics of a commit, and the other index finds the commits of a topic, newest first as
     * commit ids are ordered by time.
     */
    public function up(): void
    {
        Schema::create('commit_topics', function (Blueprint $table): void {
            $table->foreignUuid('commit_id')->constrained();
            $table->string('reference');
            $table->string('topic_type');
            $table->uuid('topic_id');

            $table->primary(['commit_id', 'reference']);
            $table->index(['topic_type', 'topic_id', 'commit_id']);
        });
    }
};
