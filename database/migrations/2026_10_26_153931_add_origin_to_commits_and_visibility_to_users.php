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
            $table->string('source')->nullable();
            $table->boolean('is_ai')->default(false);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->json('hidden_commit_channels')->default('[]');
        });
    }
};
