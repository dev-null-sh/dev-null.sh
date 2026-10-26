<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bulk activity page counts the follows and stars of the last days, so these indexes read that window instead
     * of every follow and star ever given.
     */
    public function up(): void
    {
        Schema::table('follows', function (Blueprint $table): void {
            $table->index('created_at');
        });

        Schema::table('stars', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }
};
