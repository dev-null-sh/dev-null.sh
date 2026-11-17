<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a person used the lite mobile app on a day they were seen, since it would otherwise read as web or other.
     * Only admins see it, as aggregates, and the person in their export. DeleteUser erases the rows with the account. Past
     * days say no: the app only had internal testers until now.
     */
    public function up(): void
    {
        Schema::table('user_active_days', function (Blueprint $table): void {
            $table->boolean('lite_mobile_app')->default(false);
        });
    }
};
