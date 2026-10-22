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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('cover_image_path')->nullable();
            $table->timestamp('cover_image_approved_at')->nullable();
        });

        // The moderation queue lists covers waiting for review, which are few, oldest first...
        DB::statement('create index users_pending_cover_image_path_index on users (cover_image_path) where cover_image_path is not null and cover_image_approved_at is null');
    }
};
