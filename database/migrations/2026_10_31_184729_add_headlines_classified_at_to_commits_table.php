<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records completed headline processing, including unsuitable posts, so backfills can resume without repeat AI calls.
     * Internal only and omitted from exports with headlines; erased with the commit's content or its author's account.
     */
    public function up(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->timestamp('headlines_classified_at')->nullable();
        });
    }
};
