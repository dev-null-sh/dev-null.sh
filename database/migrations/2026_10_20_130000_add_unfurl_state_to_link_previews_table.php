<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_previews', function (Blueprint $table): void {
            $table->timestamp('unfurl_failed_at')->nullable()->after('fetched_at');
            $table->unsignedTinyInteger('publication_unfurl_retries')->default(0)->after('unfurl_failed_at');
        });
    }
};
