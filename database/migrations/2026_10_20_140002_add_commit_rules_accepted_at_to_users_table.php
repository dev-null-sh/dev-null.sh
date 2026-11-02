<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only accounts made from now on read the commit rules before their first commit: everyone already here is left
     * as they are, whether they posted or not.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('commit_rules_accepted_at')->nullable();
        });

        DB::table('users')->update(['commit_rules_accepted_at' => now()]);
    }
};
