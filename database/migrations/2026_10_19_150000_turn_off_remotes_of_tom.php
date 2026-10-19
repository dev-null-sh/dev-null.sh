<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('github_id', CreateTomFriendship::GITHUB_ID)
            ->update(['remotes_enabled' => false]);
    }
};
