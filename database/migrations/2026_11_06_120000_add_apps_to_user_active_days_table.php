<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which apps a person used on each day they were seen, since nothing else can tell: Fathom has no notion of an app
     * and can't see the native ones. Only admins see it, as aggregates, and the person in their export. DeleteUser erases
     * the rows with the account. Past days count as web, whatever was really used: adding the column with a default fills
     * them without rewriting the table, and the default then goes back to false for the days to come.
     */
    public function up(): void
    {
        Schema::table('user_active_days', function (Blueprint $table): void {
            $table->boolean('web_app')->default(true);
            $table->boolean('native_mobile_app')->default(false);
            $table->boolean('mac_app')->default(false);
            $table->boolean('other_app')->default(false);
        });

        DB::statement('alter table user_active_days alter column web_app set default false');
    }
};
