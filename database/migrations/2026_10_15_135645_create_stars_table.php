<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stars', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'commit_id']);
            $table->index('commit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stars');
    }
};
