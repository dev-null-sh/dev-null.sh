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
            $table->uuid('series_id')->nullable();
            $table->unsignedTinyInteger('series_position')->nullable();
            $table->unsignedTinyInteger('series_size')->nullable();

            $table->index(['series_id', 'series_position']);
        });
    }
};
