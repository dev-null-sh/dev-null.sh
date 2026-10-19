<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_followers', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('actor_url', 2048);
            $table->string('inbox_url', 2048);
            $table->string('shared_inbox_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'actor_url']);
        });
    }
};
