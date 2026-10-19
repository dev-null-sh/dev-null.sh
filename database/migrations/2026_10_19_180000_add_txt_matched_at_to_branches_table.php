<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->timestamp('txt_matched_at')->nullable()->after('verification_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('txt_matched_at');
        });
    }
};
