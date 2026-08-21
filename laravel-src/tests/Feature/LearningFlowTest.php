<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CurriculumVersion;
use App\Models\LearningUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_creates_profile_enrollment_and_only_first_session_is_available(): void
    {
        [$first, $second] = $this->publishedCourse();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('onboarding.store'), [
            'knowledge_level' => 'new',
            'trading_experience' => 'none',
            'study_hours_per_week' => 5,
            'learning_objective' => 'Build a disciplined foundation before testing a strategy.',
            'preferred_pace' => 'steady',
            'timezone' => 'Africa/Nairobi',
            'reminder_preference' => 'weekly',
            'ai_tutor_enabled' => false,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('learner_profiles', ['user_id' => $user->id, 'timezone' => 'Africa/Nairobi', 'ai_tutor_enabled' => false]);
        $enrollmentId = (string) $this->getConnection()->table('enrollments')->where('user_id', $user->id)->value('id');
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $first->id, 'status' => 'available']);
        $this->assertDatabaseHas('unit_progress', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $second->id, 'status' => 'locked']);
        $this->assertDatabaseHas('learning_positions', ['enrollment_id' => $enrollmentId, 'learning_unit_id' => $first->id]);
    }

    public function test_a_learner_cannot_open_a_locked_session(): void
    {
        [, $second] = $this->publishedCourse();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), $this->onboardingPayload());

        $this->actingAs($user)->get(route('sessions.show', $second))->assertForbidden();
    }

    public function test_resume_position_rejects_a_block_from_another_session(): void
    {
        [$first, $second] = $this->publishedCourse();
        $foreignBlock = ContentBlock::query()->create([
            'learning_unit_id' => $second->id, 'block_type' => 'paragraph', 'position' => 1,
            'body' => 'A later concept.', 'is_required' => true,
        ]);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), $this->onboardingPayload());

        $this->actingAs($user)->putJson(route('sessions.position.update', $first), [
            'content_block_id' => $foreignBlock->id,
        ])->assertUnprocessable();
    }

    /** @return array{LearningUnit, LearningUnit} */
    private function publishedCourse(): array
    {
        $course = Course::query()->create(['slug' => 'test-course', 'title' => 'Test course', 'is_active' => true]);
        $version = CurriculumVersion::query()->create(['course_id' => $course->id, 'version_label' => 'v1', 'status' => 'published', 'published_at' => now()]);
        $phase = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'unit_type' => 'phase', 'slug' => 'phase-1', 'title' => 'Foundations', 'position' => 1]);
        $first = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'chapter-1', 'title' => 'Chapter 1', 'position' => 1]);
        $second = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'parent_id' => $phase->id, 'unit_type' => 'session', 'slug' => 'chapter-2', 'title' => 'Chapter 2', 'position' => 2]);
        ContentBlock::query()->create(['learning_unit_id' => $first->id, 'block_type' => 'paragraph', 'position' => 1, 'body' => 'A first concept.', 'is_required' => true]);

        return [$first, $second];
    }

    /** @return array<string, mixed> */
    private function onboardingPayload(): array
    {
        return [
            'knowledge_level' => 'new', 'trading_experience' => 'none', 'study_hours_per_week' => 4,
            'learning_objective' => 'Build a disciplined foundation before testing a strategy.',
            'preferred_pace' => 'steady', 'timezone' => 'Africa/Nairobi',
            'reminder_preference' => 'none', 'ai_tutor_enabled' => false,
        ];
    }
}
