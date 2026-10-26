<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timeouts go by GitHub account, like bans, so deleting the account and signing up again doesn't end one early or
     * reset how many the account has had.
     */
    public function up(): void
    {
        Schema::create('user_timeouts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('github_id');
            $table->string('reason');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['github_id', 'ends_at']);
        });
    }
};
