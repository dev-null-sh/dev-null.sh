<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commit_images', function (Blueprint $table): void {
            $table->string('mime_type')->default('image/webp');
            $table->unsignedInteger('duration_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commit_images', function (Blueprint $table): void {
            $table->dropColumn(['mime_type', 'duration_ms']);
        });
    }
};
