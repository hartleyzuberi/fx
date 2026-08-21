<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('assignment_type', 32)->index();
            $table->string('title');
            $table->text('instructions');
            $table->json('requirements');
            $table->unsignedInteger('required_observations')->nullable();
            $table->timestamps();
        });

        Schema::create('practice_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('practice_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 24)->default('draft')->index();
            $table->json('response');
            $table->string('attachment_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['practice_assignment_id', 'user_id', 'revision']);
        });

        Schema::create('strategy_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('supersedes_id')->nullable()->constrained('strategy_versions')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('draft')->index();
            $table->json('specification');
            $table->char('specification_sha256', 64);
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name', 'version_number']);
        });

        Schema::create('backtest_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('strategy_version_id')->constrained()->restrictOnDelete();
            $table->string('dataset_role', 24)->index();
            $table->string('instrument');
            $table->string('timeframe');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->json('cost_assumptions');
            $table->string('status', 24)->default('in_progress')->index();
            $table->json('metrics')->nullable();
            $table->timestamps();
        });

        Schema::create('backtest_observations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('backtest_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->dateTime('observed_at');
            $table->decimal('risk_amount', 16, 4)->nullable();
            $table->decimal('r_result', 10, 4)->nullable();
            $table->decimal('cost_amount', 16, 4)->default(0);
            $table->json('setup_snapshot');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['backtest_run_id', 'sequence']);
        });

        Schema::create('robustness_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('strategy_version_id')->constrained()->restrictOnDelete();
            $table->string('test_type', 32)->index();
            $table->json('parameters');
            $table->json('results')->nullable();
            $table->string('conclusion', 24)->nullable();
            $table->timestamps();
        });

        Schema::create('demo_programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('strategy_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('planned')->index();
            $table->decimal('starting_balance', 16, 2);
            $table->decimal('risk_percent', 6, 3);
            $table->unsignedInteger('required_observations')->default(100);
            $table->decimal('required_adherence_percent', 5, 2)->default(95);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trade_journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('demo_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('strategy_version_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence')->nullable();
            $table->string('mode', 24)->index();
            $table->string('instrument');
            $table->string('direction', 8);
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('entry_price', 20, 8);
            $table->decimal('stop_price', 20, 8);
            $table->decimal('exit_price', 20, 8)->nullable();
            $table->decimal('planned_risk', 16, 4);
            $table->decimal('r_result', 10, 4)->nullable();
            $table->boolean('rules_followed')->nullable();
            $table->json('checklist');
            $table->json('context')->nullable();
            $table->text('reflection')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->timestamps();
            $table->unique(['demo_program_id', 'sequence']);
        });

        Schema::create('readiness_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('readiness_type', 32)->index();
            $table->string('decision', 24);
            $table->json('predicate_results');
            $table->json('evidence_snapshot');
            $table->timestamp('assessed_at');
            $table->timestamps();
        });

        Schema::create('tutor_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('learning_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('thread_type', 24)->default('lesson');
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tutor_thread_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content');
            $table->json('citations')->nullable();
            $table->string('model')->nullable();
            $table->string('response_id')->nullable();
            $table->string('safety_status', 32)->default('allowed');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 32)->index();
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->default(0);
            $table->string('status', 24);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->json('configuration')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 48)->index();
            $table->string('subject_type');
            $table->string('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_events');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('ai_usage_events');
        Schema::dropIfExists('tutor_messages');
        Schema::dropIfExists('tutor_threads');
        Schema::dropIfExists('readiness_assessments');
        Schema::dropIfExists('trade_journal_entries');
        Schema::dropIfExists('demo_programs');
        Schema::dropIfExists('robustness_runs');
        Schema::dropIfExists('backtest_observations');
        Schema::dropIfExists('backtest_runs');
        Schema::dropIfExists('strategy_versions');
        Schema::dropIfExists('practice_submissions');
        Schema::dropIfExists('practice_assignments');
    }
};
