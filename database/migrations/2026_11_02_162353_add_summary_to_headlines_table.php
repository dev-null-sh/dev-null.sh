<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shared recap and its creation time let hot topics reuse one public-text snapshot for two hours without
     * repeating AI requests. Both fields stay hidden until a surface explicitly exposes them. Expired recaps are
     * cleared, as are recaps affected by source deletion, rejection or account erasure; headline pruning removes both.
     */
    public function up(): void
    {
        Schema::table('headlines', function (Blueprint $table): void {
            $table->text('summary')->nullable();
            $table->timestamp('summary_created_at')->nullable();
        });
    }
};
