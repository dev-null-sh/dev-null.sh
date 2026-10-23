<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When each participant's own mute of a remote ends, so it holds on every device. Only that participant sees it,
     * and it goes with their participant row when they leave or delete their account.
     */
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->timestamp('muted_until_at')->nullable();
        });
    }
};
