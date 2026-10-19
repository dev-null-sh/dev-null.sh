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
            $table->timestamp('claim_requested_at')->nullable()->after('verified_domain');
            $table->foreignUuid('claim_requested_by')
                ->nullable()
                ->after('claim_requested_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('claim_requested_by');
            $table->dropColumn('claim_requested_at');
        });
    }
};
