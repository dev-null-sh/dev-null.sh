<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestion_dismissals', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('dismissed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'dismissed_id']);
            $table->index('dismissed_id');
        });
    }
};
