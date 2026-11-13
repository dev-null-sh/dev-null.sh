<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table): void {
            $table->boolean('admin_only')->default(false);
        });

        DB::table('features')->where('name', 'guilds')->update(['admin_only' => true]);
    }
};
