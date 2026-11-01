<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links count as at most 23 characters towards a poll option's length, as in a commit's body, so a label can be
     * longer than the 50 characters it is measured at.
     */
    public function up(): void
    {
        Schema::table('poll_options', function (Blueprint $table): void {
            $table->text('label')->change();
        });
    }
};
