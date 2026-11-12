<?php

declare(strict_types=1);

use App\Enums\CommitType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->string('type')->default(CommitType::Standard->value);
        });

        Schema::create('launch_passengers', function (Blueprint $table): void {
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['commit_id', 'user_id']);
            $table->index(['commit_id', 'created_at']);
            $table->index('user_id');
        });
    }
};
