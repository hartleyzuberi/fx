<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\ContentMapping;
use App\Models\Course;
use App\Models\CurriculumVersion;
use App\Models\Enrollment;
use App\Models\LearnerProfile;
use App\Models\LearningUnit;
use App\Models\SourceDocument;
use App\Models\SourcePage;
use App\Models\SourceSegment;
use App\Models\SourceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoAiCourseExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_the_course_returns_cited_retrieval_when_every_provider_is_off(): void
    {
        config(['ai.enabled' => false, 'ai.semantic_search_enabled' => false]);
        Http::fake();
        [$user, $unit] = $this->mappedLesson();

        $response = $this->actingAs($user)->postJson(route('tutor.store', $unit), ['prompt' => 'Why is margin different from risk?', 'request_id' => 'no-ai-1']);

        $response->assertOk()->assertJson(['generated' => false, 'runtime_mode' => 'RETRIEVAL_ONLY', 'safety_status' => 'retrieval_only'])
            ->assertJsonPath('citations.0.document', 'Canonical Forex Course')->assertJsonPath('citations.0.page', 42);
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_usage_events', 0);
        $this->assertDatabaseHas('tutor_messages', ['role' => 'user', 'client_request_id' => 'no-ai-1']);
        $this->assertDatabaseHas('tutor_messages', ['role' => 'assistant', 'client_request_id' => 'no-ai-1', 'safety_status' => 'retrieval_only']);
    }

    public function test_search_course_works_without_ai(): void
    {
        config(['ai.enabled' => false, 'ai.semantic_search_enabled' => false]);
        [$user, $unit] = $this->mappedLesson();

        $this->actingAs($user)->getJson(route('course.search', ['q' => 'margin risk']))->assertOk()
            ->assertJsonPath('mode', 'lexical')->assertJsonPath('results.0.slug', $unit->slug)
            ->assertJsonPath('results.0.document', 'Canonical Forex Course');
    }

    /** @return array{User, LearningUnit} */
    private function mappedLesson(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        LearnerProfile::query()->create(['user_id' => $user->id, 'ai_tutor_enabled' => false, 'allow_cloud_ai' => false, 'allow_local_ai' => false, 'onboarding_completed_at' => now()]);
        $course = Course::query()->create(['slug' => 'forex', 'title' => 'Forex', 'is_active' => true]);
        $version = CurriculumVersion::query()->create(['course_id' => $course->id, 'version_label' => 'v1', 'status' => 'published', 'published_at' => now()]);
        $unit = LearningUnit::query()->create(['curriculum_version_id' => $version->id, 'unit_type' => 'session', 'slug' => 'margin-and-risk', 'title' => 'Margin and risk', 'position' => 1]);
        Enrollment::query()->create(['user_id' => $user->id, 'curriculum_version_id' => $version->id, 'status' => 'active', 'started_at' => now()]);
        $block = ContentBlock::query()->create(['learning_unit_id' => $unit->id, 'block_type' => 'paragraph', 'position' => 1, 'body' => 'Margin and risk are different.', 'is_required' => true]);
        $document = SourceDocument::query()->create(['key' => 'canonical', 'title' => 'Canonical Forex Course', 'source_kind' => 'pdf', 'is_canonical' => true]);
        $sourceVersion = SourceVersion::query()->create(['source_document_id' => $document->id, 'version_label' => 'v1', 'filename' => 'course.pdf', 'file_sha256' => str_repeat('a', 64), 'file_size_bytes' => 100, 'physical_page_count' => 100]);
        $page = SourcePage::query()->create(['source_version_id' => $sourceVersion->id, 'physical_page' => 42, 'source_order' => 42, 'text_sha256' => str_repeat('b', 64), 'character_count' => 90, 'extracted_text' => 'Margin is collateral. Planned risk depends on size and stop distance.']);
        $segment = SourceSegment::query()->create(['source_page_id' => $page->id, 'source_order' => 1, 'content_type' => 'paragraph', 'content' => 'Margin is collateral set aside for a leveraged position. Planned risk depends on position size and stop distance.', 'content_sha256' => str_repeat('c', 64), 'is_meaningful' => true]);
        ContentMapping::query()->create(['source_segment_id' => $segment->id, 'content_block_id' => $block->id, 'mapping_type' => 'canonical', 'review_status' => 'approved']);

        return [$user->load('learnerProfile'), $unit];
    }
}
