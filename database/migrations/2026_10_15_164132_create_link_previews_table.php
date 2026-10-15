<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_previews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('url');
            $table->string('url_hash', 64)->unique();
            $table->string('title', 300)->nullable();
            $table->string('description', 500)->nullable();
            $table->text('image_url')->nullable();
            $table->string('site_name', 100)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }
};
