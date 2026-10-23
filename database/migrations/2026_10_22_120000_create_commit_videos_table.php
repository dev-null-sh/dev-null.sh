<?php

declare(strict_types=1);

use App\Enums\CommitVideoStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_videos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default(CommitVideoStatus::Uploading->value);
            $table->unsignedInteger('size');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->float('duration')->nullable();
            $table->timestamps();
            $table->index(['commit_id', 'created_at']);
        });
    }
};
