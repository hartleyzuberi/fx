<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('practice_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status', 24)->default('submitted')->index();
            $table->json('response');
            $table->string('attachment_path')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->unique(['practice_assignment_id', 'user_id', 'sequence'], 'practice_observations_sequence_unique');
            $table->index(['user_id', 'practice_assignment_id', 'status'], 'practice_observations_progress_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_observations');
    }
};
