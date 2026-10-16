<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everyone who signed up before the onboarding existed counts as onboarded, so nobody is sent through it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarded_at')->nullable();
        });

        DB::table('users')->update(['onboarded_at' => now()]);
    }
};
