<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_voices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('message_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('duration_ms');
            $table->text('waveform');
            $table->timestamps();
        });
    }
};
