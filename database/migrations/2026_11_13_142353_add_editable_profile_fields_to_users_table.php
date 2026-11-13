<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_upload_path')->nullable();
            $table->string('sponsors_url', 2048)->nullable();
        });

        DB::table('users')
            ->join('profiles', 'profiles.id', '=', 'users.id')
            ->where('users.has_sponsors_listing', true)
            ->update([
                'sponsors_url' => new Expression("'https://github.com/sponsors/' || profiles.handle"),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_upload_path', 'sponsors_url']);
        });
    }
};
