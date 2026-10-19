<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique();
            $table->string('description', 280)->nullable();
            $table->string('domain')->nullable();
            $table->string('verification_token', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_domain')->nullable();
            $table->timestamps();
        });

        Schema::create('tag_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['tag_id', 'user_id']);
        });

        Schema::create('tag_watches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tag_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_watches');
        Schema::dropIfExists('tag_members');
        Schema::dropIfExists('tags');
    }
};
