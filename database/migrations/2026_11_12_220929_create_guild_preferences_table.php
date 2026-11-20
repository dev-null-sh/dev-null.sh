<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_preferences', function (Blueprint $table): void {
            $table->foreignUuid('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->primary(['guild_id', 'user_id']);
            $table->index('user_id');
        });
    }
};
