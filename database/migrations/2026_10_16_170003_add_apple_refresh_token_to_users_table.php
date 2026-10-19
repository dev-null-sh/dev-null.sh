<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The refresh token Apple hands over for the linked Apple ID, encrypted, only so its tokens can be revoked once the
     * Apple ID is unlinked or the account deleted, as Apple asks.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('apple_refresh_token')->nullable();
        });
    }
};
