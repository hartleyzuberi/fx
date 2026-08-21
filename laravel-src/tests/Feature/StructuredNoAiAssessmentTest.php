<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\CurriculumVersion;
use App\Models\LearningUnit;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StructuredNoAiAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_ai_learner_can_pass_structured_quiz_and_gate_without_human_review(): void
    {
        config(['ai.semantic_grading_enabled' => false]);
        [$first, $second, $quiz, $quizQuestion, $gate, $gateQuestion] = $this->curriculum();
        $learner = User::factory()->create();

        $this->actingAs($learner)->post(route('onboarding.store'), $this->profile());
        $this->actingAs($learner)->post(route('notebooks.store'), [
            'learning_unit_id' => $first->id,
            'notebook_type' => 'concept',
            'entry_type' => 'own_words',
            'body' => 'A currency quote is a relative price.',
        ])->assertRedirect();

        $assignmentId = (string) Str::ulid();
        DB::table('practice_assignments')->insert([
            'id' => $assignmentId,
            'learning_unit_id' => $first->id,
            'assignment_type' => 'gate_evidence',
            'title' => 'Gate A evidence',
            'instructions' => 'Record platform and regulator competence.',
            'requirements' => json_encode(['evidence_key' => 'gate_a_platform_and_regulator_competence', 'attachment_required' => false], JSON_THROW_ON_ERROR),
            'required_observations' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($learner)->post(route('exercises.store', [$first, $assignmentId]), [
            'response' => 'I completed the platform operations and recorded the regulator verification source used.',
        ])->assertRedirect();
        $this->assertDatabaseHas('practice_observations', [
            'practice_assignment_id' => $assignmentId,
            'user_id' => $learner->id,
            'sequence' => 1,
        ]);

        $this->actingAs($learner)->get(route('assessments.show', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.questions.0.type', 'single_choice')
                ->where('assessment.questions.0.structuredFallback', true)
                ->has('assessment.questions.0.choices', 4)
                ->missing('assessment.questions.0.answer_key'));

        $this->actingAs($learner)->post(route('assessments.attempts.store', $first), [
            'answers' => [$quizQuestion->id => 'correct_quote'],
        ])->assertRedirect(route('assessments.show', $first));

        $enrollmentId = $this->getConnection()->table('enrollments')->where('user_id', $learner->id)->value('id');
        $this->assertDatabaseHas('assessment_attempts', [
            'enrollment_id' => $enrollmentId,
            'assessment_id' => $quiz->id,
            'passed' => true,
            'score' => 100,
        ]);
        $this->assertDatabaseHas('unit_progress', [
            'enrollment_id' => $enrollmentId,
            'learning_unit_id' => $first->id,
            'status' => 'awaiting_gate',
        ]);

        $this->actingAs($learner)->get(route('gates.show', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.mode', 'deterministic')
                ->where('assessment.questions.0.type', 'single_choice'));

        $this->actingAs($learner)->post(route('gates.store', $first), [
            'answers' => [$gateQuestion->id => 'correct_gate'],
        ])->assertRedirect();

        $this->assertDatabaseHas('assessment_attempts', [
            'enrollment_id' => $enrollmentId,
            'assessment_id' => $gate->id,
            'status' => 'graded',
            'passed' => true,
            'score' => 100,
        ]);
        $this->assertDatabaseHas('unit_progress', [
            'enrollment_id' => $enrollmentId,
            'learning_unit_id' => $first->id,
            'status' => 'passed',
        ]);
        $this->assertDatabaseHas('unit_progress', [
            'enrollment_id' => $enrollmentId,
            'learning_unit_id' => $second->id,
            'status' => 'available',
        ]);
        $this->assertDatabaseCount('ai_usage_events', 0);
    }

    /** @return array{LearningUnit, LearningUnit, Assessment, Question, Assessment, Question} */
    private function curriculum(): array
    {
        $course = Course::query()->create(['slug' => 'structured-course', 'title' => 'Structured course', 'is_active' => true]);
        $version = CurriculumVersion::query()->create(['course_id' => $course->id, 'version_label' => 'v1', 'status' => 'published', 'published_at' => now()]);
        $phase = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'unit_type' => 'phase', 'slug' => 'phase', 'title' => 'Phase', 'position' => 1]);
        $first = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'first-structured', 'title' => 'First', 'position' => 1]);
        $second = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'after-structured', 'title' => 'Second', 'position' => 2]);

        $quiz = Assessment::query()->create([
            'learning_unit_id' => $first->id,
            'assessment_type' => 'chapter_quiz',
            'title' => 'Quiz',
            'passing_score' => 85,
            'answers_protected' => true,
        ]);
        $quizQuestion = Question::query()->create([
            'assessment_id' => $quiz->id,
            'question_type' => 'free_response',
            'position' => 1,
            'prompt' => 'What does EUR/USD = 1.1700 mean?',
            'answer_key' => ['kind' => 'propositions', 'required' => [['one euro'], ['1.17 dollars']]],
            'structured_type' => 'single_choice',
            'structured_status' => 'approved',
            'structured_choices' => [
                ['id' => 'reversed', 'text' => 'One dollar equals 1.17 euros.'],
                ['id' => 'correct_quote', 'text' => 'One euro equals 1.17 U.S. dollars.'],
                ['id' => 'same', 'text' => 'The two currencies have equal value.'],
                ['id' => 'absolute', 'text' => 'The quote only describes the euro.'],
            ],
            'structured_answer_key' => ['correct' => 'correct_quote', 'misconceptions' => ['reversed' => 'reversed_base_quote']],
            'points' => 1,
        ]);

        $gate = Assessment::query()->create([
            'learning_unit_id' => $first->id,
            'assessment_type' => 'gate_exam',
            'title' => 'Gate A',
            'passing_score' => 85,
            'is_gate' => true,
            'answers_protected' => true,
            'rules' => ['deterministic_self_study' => true],
        ]);
        $gateQuestion = Question::query()->create([
            'assessment_id' => $gate->id,
            'question_type' => 'single_choice',
            'status' => 'approved',
            'position' => 1,
            'prompt' => 'Which statement correctly preserves the relative quote meaning?',
            'choices' => [
                ['id' => 'wrong_gate', 'text' => 'One dollar equals 1.17 euros.'],
                ['id' => 'correct_gate', 'text' => 'One euro equals 1.17 U.S. dollars.'],
            ],
            'answer_key' => ['correct' => 'correct_gate'],
            'metadata' => ['completion_role' => 'structured_gate', 'source_question_id' => $quizQuestion->id],
            'points' => 1,
        ]);

        return [$first, $second, $quiz, $quizQuestion, $gate, $gateQuestion];
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        return [
            'knowledge_level' => 'new',
            'trading_experience' => 'none',
            'study_hours_per_week' => 4,
            'learning_objective' => 'Build a disciplined foundation before testing a strategy.',
            'preferred_pace' => 'steady',
            'timezone' => 'Africa/Nairobi',
            'reminder_preference' => 'none',
            'ai_tutor_enabled' => false,
        ];
    }
}
