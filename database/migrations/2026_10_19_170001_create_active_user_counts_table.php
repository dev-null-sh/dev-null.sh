<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_user_counts', function (Blueprint $table): void {
            $table->date('seen_at')->primary();
            $table->unsignedInteger('count');
        });
    }
};
