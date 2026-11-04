<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('location', 100)->nullable();
            $table->string('url', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'id']);
        });
    }
};
