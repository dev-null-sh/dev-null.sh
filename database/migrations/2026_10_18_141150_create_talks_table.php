<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('description', 4000);
            $table->date('presented_on');
            $table->string('slides_url', 2048)->nullable();
            $table->string('recording_url', 2048)->nullable();
            $table->string('event_name', 200)->nullable();
            $table->boolean('is_unlisted')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'published_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['published_at', 'is_unlisted']);
        });
    }
};
