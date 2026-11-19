<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignUuid('guild_id')->nullable()->after('timeout_id')->constrained()->cascadeOnDelete();
            $table->index('guild_id');
        });
    }
};
