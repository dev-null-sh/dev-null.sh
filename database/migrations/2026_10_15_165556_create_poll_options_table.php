<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['poll_id', 'position']);
        });
    }
};
