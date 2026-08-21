<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CurriculumVersion;
use App\Models\LearningUnit;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentProgressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_unlocks_the_next_session_only_after_quiz_and_notebook_evidence(): void
    {
        [$first, $second, $assessment, $question] = $this->curriculum();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), $this->profile());
        $this->actingAs($user)->post(route('notebooks.store'), [
            'learning_unit_id' => $first->id, 'content_block_id' => null,
            'notebook_type' => 'concept', 'entry_type' => 'own_words',
            'body' => 'A quote is relative: one euro is worth 1.17 dollars.',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('assessments.attempts.store', $first), [
            'answers' => [$question->id => 'A euro is worth 1.17 dollars.'],
        ])->assertRedirect(route('assessments.show', $first));

        $enrollmentId = $this->getConnection()->table('enrollments')->where('user_id', $user->id)->value('id');
        $this->assertDatabaseHas('assessment_attempts', ['enrollment_id' => $enrollmentId, 'assessment_id' => $assessment->id, 'passed' => true, 'score' => 100]);
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $first->id, 'status' => 'passed']);
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $second->id, 'status' => 'available']);
    }

    public function test_a_misconception_creates_a_new_failed_attempt_without_unlocking(): void
    {
        [$first, $second, $assessment, $question] = $this->curriculum();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), $this->profile());

        $this->actingAs($user)->post(route('assessments.attempts.store', $first), [
            'answers' => [$question->id => 'One dollar buys 1.17 euros.'],
        ])->assertRedirect();

        $enrollmentId = $this->getConnection()->table('enrollments')->where('user_id', $user->id)->value('id');
        $this->assertDatabaseHas('assessment_attempts', ['enrollment_id' => $enrollmentId, 'assessment_id' => $assessment->id, 'passed' => false, 'score' => 0]);
        $this->assertDatabaseHas('answer_attempts', ['question_id' => $question->id, 'grading_status' => 'misconception']);
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $second->id, 'status' => 'locked']);
    }

    /** @return array{LearningUnit, LearningUnit, Assessment, Question} */
    private function curriculum(): array
    {
        $course = Course::query()->create(['slug' => 'assessment-course', 'title' => 'Assessment course', 'is_active' => true]);
        $version = CurriculumVersion::query()->create(['course_id' => $course->id, 'version_label' => 'v1', 'status' => 'published', 'published_at' => now()]);
        $phase = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'unit_type' => 'phase', 'slug' => 'phase', 'title' => 'Phase', 'position' => 1]);
        $first = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'first', 'title' => 'First', 'position' => 1]);
        $second = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'second', 'title' => 'Second', 'position' => 2]);
        ContentBlock::query()->create(['learning_unit_id' => $first->id, 'block_type' => 'paragraph', 'position' => 1, 'body' => 'Quotes are relative.', 'is_required' => true]);
        $assessment = Assessment::query()->create(['learning_unit_id' => $first->id, 'assessment_type' => 'chapter_quiz', 'title' => 'Quiz', 'passing_score' => 85, 'is_gate' => true, 'answers_protected' => true]);
        $question = Question::query()->create(['assessment_id' => $assessment->id, 'question_type' => 'free_response', 'position' => 1, 'prompt' => 'What does EUR/USD = 1.1700 mean?', 'answer_key' => ['kind' => 'propositions', 'required' => [['one euro', 'a euro', 'euro'], ['1.17 dollars', '1.17 us dollars']], 'contradictions' => ['one dollar buys 1.17 euros']], 'explanation' => 'One euro is worth 1.17 dollars.', 'points' => 1]);

        return [$first, $second, $assessment, $question];
    }

    private function profile(): array
    {
        return ['knowledge_level' => 'new', 'trading_experience' => 'none', 'study_hours_per_week' => 4, 'learning_objective' => 'Build a disciplined foundation before testing a strategy.', 'preferred_pace' => 'steady', 'timezone' => 'Africa/Nairobi', 'reminder_preference' => 'none', 'ai_tutor_enabled' => false];
    }
}
