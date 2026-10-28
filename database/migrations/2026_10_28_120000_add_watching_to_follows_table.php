<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Watching lives on the follow, so unfollowing someone, or a block ending the follow, stops watching them too.
     */
    public function up(): void
    {
        Schema::table('follows', function (Blueprint $table): void {
            $table->boolean('watching')->default(false);
        });
    }
};
