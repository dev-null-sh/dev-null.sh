<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brief commit descriptors can be shared by many commits. Only the name is public. Headlines disappear after fourteen days without a new
     * commit link; their associations are also erased with the commit's content or its author's account.
     */
    public function up(): void
    {
        Schema::create('headlines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->timestamps();
            $table->index('created_at');
        });
    }
};
