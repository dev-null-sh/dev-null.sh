<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmark_collections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('bookmark_collection_items', function (Blueprint $table): void {
            $table->foreignUuid('bookmark_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bookmark_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['bookmark_collection_id', 'bookmark_id']);
            $table->index('bookmark_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmark_collection_items');
        Schema::dropIfExists('bookmark_collections');
    }
};
