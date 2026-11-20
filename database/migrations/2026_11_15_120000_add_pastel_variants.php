<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Variant totals power the collection without retaining who gave each pastry. Commit counts disappear with
        // the commit; lifetime received counts survive commit deletion and disappear with the receiving account.
        // The last Diamond gift enforces the private weekly allowance and disappears with the giving account.
        Schema::table('commits', function (Blueprint $table): void {
            $table->unsignedInteger('pasteis_diamond_count')->default(0);
            $table->unsignedInteger('pasteis_frosted_count')->default(0);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('pasteis_diamond_received')->default(0);
            $table->unsignedInteger('pasteis_frosted_received')->default(0);
            $table->timestamp('diamond_pastel_given_at')->nullable();
        });
    }
};
