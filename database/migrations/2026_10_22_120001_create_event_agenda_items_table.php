<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_agenda_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->timestamp('starts_at');
            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
        });
    }
};
