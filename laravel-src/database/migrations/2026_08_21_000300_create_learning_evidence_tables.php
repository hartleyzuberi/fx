<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('curriculum_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'curriculum_version_id']);
        });

        Schema::create('unit_progress', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('locked')->index();
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->decimal('mastery_score', 5, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('passed_at')->nullable();
            $table->timestamp('mastered_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'learning_unit_id']);
        });

        Schema::create('learning_positions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('content_block_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('block_offset')->default(0);
            $table->timestamp('resumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('assessment_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 24)->default('in_progress')->index();
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('maximum_score', 7, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->json('grading_summary')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'assessment_id', 'attempt_number']);
        });

        Schema::create('answer_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->longText('answer_text')->nullable();
            $table->json('answer_data')->nullable();
            $table->longText('working')->nullable();
            $table->string('grading_status', 32)->default('pending')->index();
            $table->decimal('awarded_points', 7, 2)->nullable();
            $table->json('deterministic_result')->nullable();
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['assessment_attempt_id', 'question_id', 'revision']);
        });

        Schema::create('grading_recommendations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('answer_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('grader_type', 24)->index();
            $table->string('model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('status', 32);
            $table->unsignedSmallInteger('mastery_score');
            $table->json('correct_concepts');
            $table->json('missing_concepts');
            $table->json('misconceptions');
            $table->text('feedback');
            $table->text('follow_up_question')->nullable();
            $table->boolean('requires_remediation')->default(false);
            $table->json('citations')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('concept_mastery', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('concept_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('unseen')->index();
            $table->decimal('score', 5, 2)->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamp('next_review_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['enrollment_id', 'concept_id']);
        });

        Schema::create('mastery_evidence', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('concept_mastery_id')->constrained('concept_mastery')->cascadeOnDelete();
            $table->string('evidence_type', 32)->index();
            $table->string('evidence_id')->nullable()->index();
            $table->decimal('score', 5, 2);
            $table->decimal('weight', 5, 2)->default(1);
            $table->json('details')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('concept_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 32)->index();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->timestamp('due_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('notebook_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('learning_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('content_block_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notebook_type', 24)->index();
            $table->string('entry_type', 32)->default('note');
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->boolean('physical_task_completed')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'notebook_type']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('content_block_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'content_block_id']);
        });

        Schema::create('progression_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32)->index();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->json('rule_evidence');
            $table->string('actor_type', 24)->default('system');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_events');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('notebook_entries');
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('mastery_evidence');
        Schema::dropIfExists('concept_mastery');
        Schema::dropIfExists('grading_recommendations');
        Schema::dropIfExists('answer_attempts');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('learning_positions');
        Schema::dropIfExists('unit_progress');
        Schema::dropIfExists('enrollments');
    }
};
