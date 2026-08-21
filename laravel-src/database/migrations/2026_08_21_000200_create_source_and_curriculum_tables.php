<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('source_kind', 32);
            $table->boolean('is_canonical')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('source_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_document_id')->constrained()->cascadeOnDelete();
            $table->string('version_label');
            $table->string('filename');
            $table->char('file_sha256', 64);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('physical_page_count');
            $table->json('metadata')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->unique(['source_document_id', 'file_sha256']);
        });

        Schema::create('source_pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('physical_page');
            $table->unsignedInteger('logical_page')->nullable();
            $table->unsignedInteger('source_order');
            $table->char('text_sha256', 64);
            $table->unsignedInteger('character_count')->default(0);
            $table->json('markers')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
            $table->unique(['source_version_id', 'physical_page']);
            $table->index(['source_version_id', 'source_order']);
        });

        Schema::create('source_segments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_page_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('source_segments')->nullOnDelete();
            $table->unsignedInteger('source_order');
            $table->string('section_identifier')->nullable()->index();
            $table->string('content_type', 40)->index();
            $table->json('heading_hierarchy')->nullable();
            $table->longText('content');
            $table->char('content_sha256', 64);
            $table->boolean('is_meaningful')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source_page_id', 'source_order']);
        });

        Schema::create('source_relations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('from_segment_id')->constrained('source_segments')->cascadeOnDelete();
            $table->foreignUlid('to_segment_id')->constrained('source_segments')->cascadeOnDelete();
            $table->string('relation_type', 32)->index();
            $table->decimal('confidence', 5, 4)->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['from_segment_id', 'to_segment_id', 'relation_type']);
        });

        Schema::create('source_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_page_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('resource_type', 40)->default('external');
            $table->string('review_status', 24)->default('unreviewed')->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('curriculum_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('supersedes_id')->nullable()->constrained('curriculum_versions')->nullOnDelete();
            $table->string('version_label');
            $table->string('status', 24)->default('draft')->index();
            $table->text('change_summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'version_label']);
        });

        Schema::create('learning_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('curriculum_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('learning_units')->cascadeOnDelete();
            $table->string('unit_type', 32)->index();
            $table->string('slug');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('materialized_path')->nullable()->index();
            $table->unsignedInteger('position');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->boolean('is_safety_critical')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_version_id', 'slug']);
            $table->index(['parent_id', 'position']);
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('block_type', 40)->index();
            $table->unsignedInteger('position');
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['learning_unit_id', 'position']);
        });

        Schema::create('content_mappings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_segment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('content_block_id')->constrained()->cascadeOnDelete();
            $table->string('mapping_type', 32)->index();
            $table->string('review_status', 24)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['source_segment_id', 'content_block_id', 'mapping_type']);
        });

        Schema::create('concepts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('curriculum_version_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('definition')->nullable();
            $table->boolean('is_safety_critical')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_version_id', 'slug']);
        });

        Schema::create('concept_learning_unit', function (Blueprint $table) {
            $table->foreignUlid('concept_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 24)->default('teaches');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->primary(['concept_id', 'learning_unit_id']);
        });

        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->text('statement');
            $table->unsignedInteger('position');
            $table->boolean('is_critical')->default(false);
            $table->timestamps();
        });

        Schema::create('rubrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_objective_id')->constrained()->cascadeOnDelete();
            $table->string('version_label')->default('1');
            $table->json('criteria');
            $table->json('misconceptions')->nullable();
            $table->unsignedSmallInteger('passing_score')->default(85);
            $table->timestamps();
            $table->unique(['learning_objective_id', 'version_label']);
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('assessment_type', 32)->index();
            $table->string('title');
            $table->unsignedSmallInteger('passing_score')->default(85);
            $table->boolean('is_gate')->default(false);
            $table->boolean('answers_protected')->default(true);
            $table->json('rules')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('rubric_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_type', 32)->index();
            $table->unsignedInteger('position');
            $table->text('prompt');
            $table->json('choices')->nullable();
            $table->json('answer_key')->nullable();
            $table->text('explanation')->nullable();
            $table->decimal('points', 7, 2)->default(1);
            $table->boolean('requires_working')->default(false);
            $table->timestamps();
            $table->unique(['assessment_id', 'position']);
        });

        Schema::create('unlock_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 32)->index();
            $table->foreignUlid('required_learning_unit_id')->nullable()->constrained('learning_units')->nullOnDelete();
            $table->foreignUlid('required_concept_id')->nullable()->constrained('concepts')->nullOnDelete();
            $table->string('operator', 16)->default('>=');
            $table->decimal('threshold', 10, 2)->nullable();
            $table->json('configuration')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('source_conflicts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('left_segment_id')->nullable()->constrained('source_segments')->nullOnDelete();
            $table->foreignUlid('right_segment_id')->nullable()->constrained('source_segments')->nullOnDelete();
            $table->string('conflict_type', 40);
            $table->text('description');
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_conflicts');
        Schema::dropIfExists('unlock_rules');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('rubrics');
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('concept_learning_unit');
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('content_mappings');
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('learning_units');
        Schema::dropIfExists('curriculum_versions');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('source_links');
        Schema::dropIfExists('source_relations');
        Schema::dropIfExists('source_segments');
        Schema::dropIfExists('source_pages');
        Schema::dropIfExists('source_versions');
        Schema::dropIfExists('source_documents');
    }
};
