<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the user wants pushed to their phones: follows, replies, mentions, and Remotes messages in direct remotes and
     * in orgs. Everything is on until they turn it off, since turning on notifications on the phone already asks.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('push_follows')->default(true);
            $table->boolean('push_replies')->default(true);
            $table->boolean('push_mentions')->default(true);
            $table->boolean('push_direct_remotes')->default(true);
            $table->boolean('push_org_remotes')->default(true);
        });
    }
};
