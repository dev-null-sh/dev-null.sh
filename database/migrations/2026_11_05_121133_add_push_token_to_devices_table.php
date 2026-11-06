<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Firebase Cloud Messaging token the app on a phone gave for pushing to it, and whether the phone is an iPhone or
     * an Android one. It lives on the device so a phone has one at most, and it goes with the device when it is revoked
     * or the account deleted. The token is encrypted like web push endpoints, and its hash is unique, so a token only
     * ever belongs to the device that sent it last.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->char('push_token_hash', 64)->nullable()->unique();
            $table->text('push_token')->nullable();
            $table->string('push_platform', 10)->nullable();
        });
    }
};
