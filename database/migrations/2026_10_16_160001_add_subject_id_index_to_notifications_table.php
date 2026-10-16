<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres does not index foreign keys on its own, so deleting a commit would scan every notification for it.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index('subject_id');
        });
    }
};
