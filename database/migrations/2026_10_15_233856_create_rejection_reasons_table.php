<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rejection_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 40);
            $table->string('body', 280);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rejection_reasons');
    }
};
