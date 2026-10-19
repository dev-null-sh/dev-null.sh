<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `sub` of the Apple ID a user linked, so the mobile app can sign them in with Apple on a new device. Apple
     * never creates an account: it is only ever a second way into one made with GitHub.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('apple_id')->nullable()->unique();
        });
    }
};
