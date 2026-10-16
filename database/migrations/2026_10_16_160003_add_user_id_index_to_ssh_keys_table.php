<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres does not index foreign keys on its own, so every lookup of a user's ssh keys would scan them all.
     */
    public function up(): void
    {
        Schema::table('ssh_keys', function (Blueprint $table): void {
            $table->index('user_id');
        });
    }
};
