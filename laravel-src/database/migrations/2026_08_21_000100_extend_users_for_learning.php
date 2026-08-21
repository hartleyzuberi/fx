<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 24)->default('student')->index();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('learner_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('knowledge_level', 32)->default('beginner');
            $table->string('trading_experience', 32)->default('none');
            $table->unsignedSmallInteger('study_hours_per_week')->default(4);
            $table->text('learning_objective')->nullable();
            $table->string('preferred_pace', 24)->default('steady');
            $table->string('timezone', 64)->default('Africa/Nairobi');
            $table->string('reminder_preference', 24)->default('none');
            $table->boolean('ai_tutor_enabled')->default(false);
            $table->timestamp('ai_consent_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
