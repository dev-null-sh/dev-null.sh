<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('reactions_version')->default(0);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('reactions_version')->default(0);

            $table->index(['conversation_id', 'reactions_version']);
        });
    }
};
