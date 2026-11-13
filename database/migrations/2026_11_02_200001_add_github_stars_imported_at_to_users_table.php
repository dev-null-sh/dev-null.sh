<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the owners of the repositories a user starred on GitHub were imported, apart from their follows, so a
     * failure of one doesn't hold the other back.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('github_stars_imported_at')->nullable();
        });
    }
};
