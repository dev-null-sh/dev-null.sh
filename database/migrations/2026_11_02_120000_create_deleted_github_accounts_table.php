<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The GitHub accounts of people who deleted their account, so looking their handle up never brings their page back
     * as an unclaimed one. Only those lookups read it, and nobody sees it. A row goes when its person signs in again.
     */
    public function up(): void
    {
        Schema::create('deleted_github_accounts', function (Blueprint $table): void {
            $table->string('github_id')->primary();
        });
    }
};
