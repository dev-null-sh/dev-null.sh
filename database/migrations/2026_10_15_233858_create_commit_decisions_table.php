<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('reason', 280)->nullable();
            $table->timestamps();

            $table->index(['commit_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commit_decisions');
    }
};
