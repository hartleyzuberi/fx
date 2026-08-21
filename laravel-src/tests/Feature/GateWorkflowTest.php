<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\CurriculumVersion;
use App\Models\LearningUnit;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_gate_waits_for_manual_review_before_unlocking_the_next_stage(): void
    {
        [$first, $second, $quiz, $quizQuestion, $gate, $gateQuestion] = $this->curriculum();
        $learner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($learner)->post(route('onboarding.store'), $this->profile());
        $this->actingAs($learner)->post(route('notebooks.store'), [
            'learning_unit_id' => $first->id, 'notebook_type' => 'concept', 'entry_type' => 'own_words',
            'body' => 'One euro is worth 1.17 dollars.',
        ]);
        $this->actingAs($learner)->post(route('assessments.attempts.store', $first), [
            'answers' => [$quizQuestion->id => 'One euro is worth 1.17 dollars.'],
        ]);
        $enrollmentId = $this->getConnection()->table('enrollments')->where('user_id', $learner->id)->value('id');
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $first->id, 'status' => 'awaiting_gate']);
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $second->id, 'status' => 'locked']);

        $this->actingAs($learner)->post(route('gates.store', $first), [
            'answers' => [$gateQuestion->id => 'I can explain and demonstrate the required practical evidence.'],
        ])->assertRedirect();
        $attemptId = $this->getConnection()->table('assessment_attempts')->where('assessment_id', $gate->id)->value('id');
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attemptId, 'status' => 'awaiting_review', 'passed' => null]);

        $this->actingAs($admin)->patch(route('admin.gate-attempts.review', $attemptId), [
            'score' => 90, 'feedback' => 'The explanations and practical evidence meet the gate framework.',
        ])->assertRedirect();
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attemptId, 'status' => 'graded', 'passed' => true, 'score' => 90]);
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $second->id, 'status' => 'available']);
        $this->assertDatabaseHas('admin_audit_events', ['user_id' => $admin->id, 'event_type' => 'gate_reviewed', 'subject_id' => $attemptId]);
        $this->assertDatabaseHas('assessment_attempts', ['assessment_id' => $quiz->id, 'passed' => true]);
    }

    /** @return array{LearningUnit, LearningUnit, Assessment, Question, Assessment, Question} */
    private function curriculum(): array
    {
        $course = Course::query()->create(['slug' => 'gate-course', 'title' => 'Gate course', 'is_active' => true]);
        $version = CurriculumVersion::query()->create(['course_id' => $course->id, 'version_label' => 'v1', 'status' => 'published', 'published_at' => now()]);
        $phase = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'unit_type' => 'phase', 'slug' => 'phase', 'title' => 'Phase', 'position' => 1]);
        $first = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'first-gate', 'title' => 'First', 'position' => 1]);
        $second = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'after-gate', 'title' => 'Second', 'position' => 2]);
        $quiz = Assessment::query()->create(['learning_unit_id' => $first->id, 'assessment_type' => 'chapter_quiz', 'title' => 'Quiz', 'passing_score' => 85, 'answers_protected' => true]);
        $quizQuestion = Question::query()->create(['assessment_id' => $quiz->id, 'question_type' => 'free_response', 'position' => 1, 'prompt' => 'Explain the quote.', 'answer_key' => ['kind' => 'propositions', 'required' => [['one euro'], ['1.17 dollars']]], 'points' => 1]);
        $gate = Assessment::query()->create(['learning_unit_id' => $first->id, 'assessment_type' => 'gate_exam', 'title' => 'Gate A', 'passing_score' => 85, 'is_gate' => true, 'answers_protected' => true]);
        $gateQuestion = Question::query()->create(['assessment_id' => $gate->id, 'question_type' => 'free_response', 'position' => 1, 'prompt' => 'Defend your evidence.', 'answer_key' => ['kind' => 'manual_review'], 'points' => 1]);

        return [$first, $second, $quiz, $quizQuestion, $gate, $gateQuestion];
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        return ['knowledge_level' => 'new', 'trading_experience' => 'none', 'study_hours_per_week' => 4, 'learning_objective' => 'Build a disciplined foundation before testing a strategy.', 'preferred_pace' => 'steady', 'timezone' => 'Africa/Nairobi', 'reminder_preference' => 'none', 'ai_tutor_enabled' => false];
    }
}
