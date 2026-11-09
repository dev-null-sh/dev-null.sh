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
            $table->boolean('allows_embeds')->default(true);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('allows_embeds')->default(true);
        });
    }
};
