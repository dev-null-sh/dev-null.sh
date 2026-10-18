<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->timestamp('pinned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'pinned_at']);
        });

        Schema::create('user_list_members', function (Blueprint $table): void {
            $table->foreignUuid('user_list_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_list_id', 'user_id']);
            $table->index('user_id');
        });
    }
};
