<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which kinds of notifications the user chose to see, as `?type=` takes them: `all`, `none`, or a comma separated
     * list. `null` until they choose, which shows them all.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('notification_types')->nullable();
        });
    }
};
