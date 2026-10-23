<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_gifs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('provider_gif_id', 64);
            $table->text('url');
            $table->text('page_url');
            $table->string('title', 300)->nullable();
            $table->string('username', 100)->nullable();
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->timestamps();
        });
    }
};
