<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->text('details')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('reporter_id');
            $table->index(['dismissed_at', 'created_at', 'id']);
        });

        // One open report per reporter. Someone's behaviour can change, so a dismissed report doesn't stop a new one.
        DB::statement('create unique index user_reports_open_unique on user_reports (user_id, reporter_id) where dismissed_at is null');
    }
};
