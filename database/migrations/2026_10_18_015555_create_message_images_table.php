<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_images', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->index(['message_id', 'position']);
        });
    }
};
