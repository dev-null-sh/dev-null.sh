<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When someone accepted the terms and privacy policy. The version they accepted is the one in force at that moment,
     * so it is not stored. Everyone already here is left empty: the apps ask them to accept the first time they open them.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('terms_accepted_at')->nullable();
        });
    }
};
