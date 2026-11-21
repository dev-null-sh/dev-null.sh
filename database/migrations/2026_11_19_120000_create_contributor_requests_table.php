<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributor_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('project_url', 255);
            $table->string('kind', 20);
            $table->string('status', 20)->default('open');
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index(['status', 'created_at', 'id']);
            $table->index(['status', 'kind', 'created_at', 'id']);
        });
    }
};
