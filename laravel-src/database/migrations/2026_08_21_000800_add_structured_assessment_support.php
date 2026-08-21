<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'learning_objective_id')) {
                $table->foreignUlid('learning_objective_id')->nullable()->after('concept_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('questions', 'source_segment_id')) {
                $table->foreignUlid('source_segment_id')->nullable()->after('rubric_id')->constrained('source_segments')->nullOnDelete();
            }
            if (! Schema::hasColumn('questions', 'difficulty')) {
                $table->string('difficulty', 32)->default('understanding')->after('question_type')->index();
            }
            if (! Schema::hasColumn('questions', 'status')) {
                $table->string('status', 24)->default('approved')->after('difficulty')->index();
            }
            if (! Schema::hasColumn('questions', 'question_version')) {
                $table->string('question_version', 32)->default('1')->after('status');
            }
            if (! Schema::hasColumn('questions', 'structured_type')) {
                $table->string('structured_type', 32)->nullable()->after('choices');
            }
            if (! Schema::hasColumn('questions', 'structured_choices')) {
                $table->json('structured_choices')->nullable()->after('structured_type');
            }
            if (! Schema::hasColumn('questions', 'structured_answer_key')) {
                $table->json('structured_answer_key')->nullable()->after('structured_choices');
            }
            if (! Schema::hasColumn('questions', 'structured_status')) {
                $table->string('structured_status', 24)->default('not_available')->after('structured_answer_key')->index();
            }
            if (! Schema::hasColumn('questions', 'metadata')) {
                $table->json('metadata')->nullable()->after('requires_working');
            }
        });

        Schema::table('answer_attempts', function (Blueprint $table): void {
            if (! Schema::hasColumn('answer_attempts', 'question_version')) {
                $table->string('question_version', 32)->nullable()->after('question_id');
            }
            if (! Schema::hasColumn('answer_attempts', 'grading_version')) {
                $table->string('grading_version', 32)->nullable()->after('grading_status');
            }
            if (! Schema::hasColumn('answer_attempts', 'question_snapshot')) {
                $table->json('question_snapshot')->nullable()->after('deterministic_result');
            }
        });
    }

    public function down(): void
    {
        Schema::table('answer_attempts', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['question_version', 'grading_version', 'question_snapshot'],
                fn (string $column): bool => Schema::hasColumn('answer_attempts', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasColumn('questions', 'learning_objective_id')) {
                $table->dropConstrainedForeignId('learning_objective_id');
            }
            if (Schema::hasColumn('questions', 'source_segment_id')) {
                $table->dropConstrainedForeignId('source_segment_id');
            }
            $columns = array_values(array_filter(
                ['difficulty', 'status', 'question_version', 'structured_type', 'structured_choices', 'structured_answer_key', 'structured_status', 'metadata'],
                fn (string $column): bool => Schema::hasColumn('questions', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
