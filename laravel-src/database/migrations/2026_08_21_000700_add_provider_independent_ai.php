<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('learner_profiles', 'allow_cloud_ai')) {
            Schema::table('learner_profiles', fn (Blueprint $table) => $table->boolean('allow_cloud_ai')->default(true)->after('ai_tutor_enabled'));
        }
        if (! Schema::hasColumn('learner_profiles', 'allow_local_ai')) {
            Schema::table('learner_profiles', fn (Blueprint $table) => $table->boolean('allow_local_ai')->default(true)->after('allow_cloud_ai'));
        }

        if (! Schema::hasColumn('tutor_messages', 'provider')) {
            Schema::table('tutor_messages', fn (Blueprint $table) => $table->string('provider', 32)->nullable()->after('model'));
        }
        if (! Schema::hasColumn('tutor_messages', 'client_request_id')) {
            Schema::table('tutor_messages', fn (Blueprint $table) => $table->string('client_request_id', 80)->nullable()->after('safety_status')->index());
        }

        $usageColumns = [
            'provider' => fn (Blueprint $table) => $table->string('provider', 32)->nullable()->after('feature')->index(),
            'task_type' => fn (Blueprint $table) => $table->string('task_type', 32)->nullable()->after('provider')->index(),
            'capability' => fn (Blueprint $table) => $table->string('capability', 32)->nullable()->after('task_type'),
            'fallback_used' => fn (Blueprint $table) => $table->boolean('fallback_used')->default(false)->after('latency_ms'),
            'error_code' => fn (Blueprint $table) => $table->string('error_code', 64)->nullable()->after('fallback_used'),
            'prompt_version' => fn (Blueprint $table) => $table->string('prompt_version', 64)->nullable()->after('error_code'),
            'request_id' => fn (Blueprint $table) => $table->string('request_id', 80)->nullable()->after('prompt_version')->index(),
        ];
        foreach ($usageColumns as $column => $definition) {
            if (! Schema::hasColumn('ai_usage_events', $column)) {
                Schema::table('ai_usage_events', $definition);
            }
        }

        if (! Schema::hasColumn('grading_recommendations', 'provider')) {
            Schema::table('grading_recommendations', fn (Blueprint $table) => $table->string('provider', 32)->nullable()->after('grader_type'));
        }
        if (! Schema::hasColumn('grading_recommendations', 'rubric_version')) {
            Schema::table('grading_recommendations', fn (Blueprint $table) => $table->string('rubric_version', 32)->nullable()->after('prompt_version'));
        }
        if (! Schema::hasColumn('grading_recommendations', 'course_version')) {
            Schema::table('grading_recommendations', fn (Blueprint $table) => $table->string('course_version', 64)->nullable()->after('rubric_version'));
        }

        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider', 32)->unique();
            $table->boolean('enabled')->nullable();
            $table->string('model')->nullable();
            $table->string('embedding_model')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('cost_class', 16)->nullable();
            $table->json('capabilities')->nullable();
            $table->decimal('daily_budget_usd', 12, 4)->nullable();
            $table->decimal('monthly_budget_usd', 12, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_routing_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('task_type', 32)->unique();
            $table->string('primary_provider', 32)->nullable();
            $table->json('fallback_providers')->nullable();
            $table->boolean('local_first')->default(false);
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_prompt_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 64);
            $table->string('version', 32);
            $table->longText('system_prompt');
            $table->char('content_sha256', 64);
            $table->boolean('active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key', 'version']);
        });

        Schema::create('ai_embedding_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_segment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('model');
            $table->char('content_sha256', 64);
            $table->unsignedInteger('dimensions');
            $table->longText('embedding');
            $table->timestamp('indexed_at');
            $table->timestamps();
            $table->unique(['source_segment_id', 'provider', 'model']);
            $table->index(['provider', 'model', 'content_sha256']);
        });

        Schema::create('ai_index_states', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('state', 24)->default('disabled')->index();
            $table->string('provider', 32)->nullable();
            $table->string('model')->nullable();
            $table->char('corpus_sha256', 64)->nullable();
            $table->unsignedInteger('record_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_index_states');
        Schema::dropIfExists('ai_embedding_records');
        Schema::dropIfExists('ai_prompt_versions');
        Schema::dropIfExists('ai_routing_rules');
        Schema::dropIfExists('ai_provider_settings');

        Schema::table('grading_recommendations', function (Blueprint $table) {
            $table->dropColumn(['provider', 'rubric_version', 'course_version']);
        });
        Schema::table('ai_usage_events', function (Blueprint $table) {
            $table->dropColumn(['provider', 'task_type', 'capability', 'fallback_used', 'error_code', 'prompt_version', 'request_id']);
        });
        Schema::table('tutor_messages', function (Blueprint $table) {
            $table->dropColumn(['provider', 'client_request_id']);
        });
        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->dropColumn(['allow_cloud_ai', 'allow_local_ai']);
        });
    }
};
