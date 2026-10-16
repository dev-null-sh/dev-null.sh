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
            $table->boolean('pasteis_mode')->default(false);
            $table->unsignedBigInteger('pasteis_received')->default(0);
            $table->unsignedTinyInteger('pasteis_given_today')->default(0);
            $table->date('pasteis_given_on')->nullable();
        });

        Schema::table('commits', function (Blueprint $table): void {
            $table->unsignedBigInteger('pasteis_count')->default(0);
        });
    }
};
