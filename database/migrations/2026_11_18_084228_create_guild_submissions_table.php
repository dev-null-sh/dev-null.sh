<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('handle', 60);
            $table->text('name');
            $table->text('description');
            $table->string('emoji');
            $table->string('icon_path')->nullable();
            $table->boolean('remove_icon')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'handle']);
            $table->index(['created_at', 'id']);
        });
    }
};
