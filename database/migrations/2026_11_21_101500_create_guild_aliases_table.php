<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_aliases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('guild_id')->constrained()->cascadeOnDelete();
            $table->string('handle', 60)->unique();
            $table->index('guild_id');
        });
    }
};
