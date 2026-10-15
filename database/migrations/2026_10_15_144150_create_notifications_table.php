<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->foreignUuid('commit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['recipient_id', 'key']);
            $table->index(['recipient_id', 'created_at']);
            $table->index('actor_id');
            $table->index('commit_id');
        });
    }
};
