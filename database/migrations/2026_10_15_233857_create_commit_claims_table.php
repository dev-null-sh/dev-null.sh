<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('commit_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commit_claims');
    }
};
