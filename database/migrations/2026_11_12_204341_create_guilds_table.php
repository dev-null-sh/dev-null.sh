<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guilds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('handle', 60)->unique();
            $table->string('name', 60);
            $table->text('description');
            $table->string('emoji');
            $table->string('icon_path')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }
};
