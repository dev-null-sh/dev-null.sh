<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each part of a series already replies to the part before it, so the parts stay behind as a thread of self-replies.
     */
    public function up(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->dropIndex(['series_id', 'series_position']);
        });

        Schema::table('commits', function (Blueprint $table): void {
            $table->dropColumn(['series_id', 'series_position', 'series_size']);
        });
    }
};
