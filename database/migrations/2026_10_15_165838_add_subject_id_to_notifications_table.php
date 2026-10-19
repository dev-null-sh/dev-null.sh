<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignUuid('subject_id')->nullable()->constrained('commits')->cascadeOnDelete();

            $table->index(['recipient_id', 'type', 'subject_id']);
        });
    }
};
