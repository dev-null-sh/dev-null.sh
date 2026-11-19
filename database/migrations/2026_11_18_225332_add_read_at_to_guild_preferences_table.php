<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_preferences', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable();
        });
    }
};
