<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A guild can point at one YouTube or Twitch stream, the same three columns a user has, until that stream runs out.
     */
    public function up(): void
    {
        Schema::table('guilds', function (Blueprint $table): void {
            $table->string('live_platform')->nullable();
            $table->string('live_url', 2048)->nullable();
            $table->timestamp('live_ends_at')->nullable();
        });
    }
};
