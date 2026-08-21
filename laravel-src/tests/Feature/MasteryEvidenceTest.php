<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\CurriculumVersion;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Question;
use App\Models\User;
use App\Services\Learning\MasteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasteryEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_misconception_creates_mastery_evidence_and_a_due_review_item(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create(['slug' => 'mastery', 'title' => 'Mastery', 'is_active' => true]);
        $version = CurriculumVersion::query()->create(['course_id' => $course->id, 'version_label' => 'v1', 'status' => 'published']);
        $unit = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'unit_type' => 'session', 'slug' => 'unit', 'title' => 'Unit', 'position' => 1]);
        $enrollment = Enrollment::query()->create(['user_id' => $user->id, 'curriculum_version_id' => $version->id, 'status' => 'active', 'started_at' => now()]);
        $conceptId = (string) Str::ulid();
        DB::table('concepts')->insert(['id' => $conceptId, 'curriculum_version_id' => $version->id, 'slug' => 'quotes', 'name' => 'Quotes', 'created_at' => now(), 'updated_at' => now()]);
        $assessment = Assessment::query()->create(['learning_unit_id' => $unit->id, 'assessment_type' => 'chapter_quiz', 'title' => 'Quiz', 'passing_score' => 85]);
        $question = Question::query()->create(['assessment_id' => $assessment->id, 'concept_id' => $conceptId, 'question_type' => 'free_response', 'position' => 1, 'prompt' => 'Explain a quote.', 'points' => 1]);

        (new MasteryService)->recordAnswer($enrollment, $question, (string) Str::ulid(), [
            'status' => 'misconception', 'mastery_score' => 0, 'requires_remediation' => true,
        ]);

        $this->assertDatabaseHas('concept_mastery', ['enrollment_id' => $enrollment->id, 'concept_id' => $conceptId, 'status' => 'needs_review', 'score' => 0]);
        $this->assertDatabaseHas('mastery_evidence', ['evidence_type' => 'assessment_answer', 'score' => 0]);
        $this->assertDatabaseHas('review_items', ['enrollment_id' => $enrollment->id, 'concept_id' => $conceptId, 'reason' => 'misconception', 'priority' => 100]);
    }
}
