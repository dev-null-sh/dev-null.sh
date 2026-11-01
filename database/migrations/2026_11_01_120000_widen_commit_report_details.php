<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A report raised to child safety keeps its old details beside the new ones, which can pass the 500 a person may write.
     */
    public function up(): void
    {
        Schema::table('commit_reports', function (Blueprint $table): void {
            $table->text('details')->nullable()->change();
        });
    }
};
