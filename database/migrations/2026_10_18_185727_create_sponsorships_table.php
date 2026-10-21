<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorships', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('sponsorable_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('sponsor_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_one_time');
            $table->timestamp('lapses_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['sponsorable_id', 'sponsor_id']);
            $table->index('sponsor_id');
        });
    }
};
