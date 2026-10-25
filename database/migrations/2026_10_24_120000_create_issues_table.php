<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issues opened with /feature and /bug remember who reported them, so comments on GitHub can notify them here. A number
     * is only unique within its repository, so a change of the configured repository never hands an issue to someone else.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('repository');
            $table->unsignedInteger('number');
            $table->string('title', 256);
            $table->string('url');
            $table->timestamps();

            $table->unique(['repository', 'number']);
            $table->index('user_id');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignUuid('issue_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('url', 2048)->nullable();

            $table->index('issue_id');
        });
    }
};
