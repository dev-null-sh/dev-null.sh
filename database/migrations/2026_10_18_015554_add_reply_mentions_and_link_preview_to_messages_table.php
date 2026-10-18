<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('reply_to_id')->nullable()->after('body')->constrained('messages')->nullOnDelete();
            $table->text('mentions')->nullable()->after('reply_to_id');
            $table->foreignUuid('link_preview_id')->nullable()->after('mentions')->constrained()->nullOnDelete();
        });
    }
};
