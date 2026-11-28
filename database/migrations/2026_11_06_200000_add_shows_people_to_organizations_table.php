<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether everyone sees the organization's members on a People tab of its page. Only its members see them until its
     * admins switch it on.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('shows_people')->default(false);
        });
    }
};
