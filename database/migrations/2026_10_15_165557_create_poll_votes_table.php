<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'user_id']);
            $table->index(['poll_option_id', 'user_id']);
            $table->index(['user_id', 'id']);
        });
    }
};
