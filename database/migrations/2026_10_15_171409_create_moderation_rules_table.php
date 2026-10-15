<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('matches', 500);
            $table->string('does_not_match', 500)->nullable();
            $table->string('action');
            $table->string('strictness');
            $table->string('mode')->default('test');
            $table->string('rejection_message', 280)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['mode', 'position']);
        });
    }
};
