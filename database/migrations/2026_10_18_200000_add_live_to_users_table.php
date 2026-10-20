<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('live_platform')->nullable();
            $table->string('live_url', 2048)->nullable();
            $table->timestamp('live_ends_at')->nullable();
        });
    }
};
