<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->string('username');

            $table->unique(['commit_id', 'username']);
            $table->index(['username', 'id']);
        });
    }
};
