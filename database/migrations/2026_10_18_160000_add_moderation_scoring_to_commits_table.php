<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->unsignedSmallInteger('moderation_score')->default(0);
            $table->json('moderation_signals')->nullable();
            $table->char('body_hash', 64)->nullable();

            $table->index('body_hash');
            $table->index(['status', 'moderation_score', 'created_at']);
        });
    }
};
