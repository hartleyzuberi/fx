<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ContentBlock;
use App\Models\ContentMapping;
use App\Models\Enrollment;
use App\Models\LearningPosition;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function show(LearningUnit $unit): Response
    {
        $enrollment = Enrollment::query()->where('user_id', request()->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        $progress = UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->firstOrFail();
        abort_if($progress->status === 'locked', 403, 'Complete the required mastery gate before opening this session.');
        if ($progress->status === 'available') {
            $progress->update(['status' => 'in_progress', 'started_at' => now(), 'last_activity_at' => now()]);
        }
        $position = LearningPosition::query()->where('enrollment_id', $enrollment->id)->first();
        $blocks = $unit->blocks()->with(['mappings.segment.page.version.document'])->get();
        $bookmarks = DB::table('bookmarks')->where('user_id', request()->user()->id)->whereIn('content_block_id', $blocks->pluck('id'))->pluck('id', 'content_block_id');
        $gate = Assessment::query()->where('learning_unit_id', $unit->id)->whereIn('assessment_type', ['gate_exam', 'final_exam'])->first();

        $blockPayload = [];
        foreach ($blocks as $block) {
            if (! $block instanceof ContentBlock) {
                continue;
            }
            $sources = [];
            $seen = [];
            foreach ($block->mappings as $mapping) {
                if (! $mapping instanceof ContentMapping) {
                    continue;
                }
                $document = $mapping->segment->page->version->document->title;
                $page = $mapping->segment->page->physical_page;
                $relationship = $mapping->mapping_type;
                $key = $document.'-'.$page.'-'.$relationship;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $sources[] = [
                    'document' => $document,
                    'page' => $page,
                    'relationship' => $relationship,
                ];
            }

            $blockPayload[] = [
                'id' => $block->id,
                'type' => $block->block_type,
                'title' => $block->title,
                'body' => $block->body,
                'required' => $block->is_required,
                'bookmarkId' => $bookmarks[$block->id] ?? null,
                'sources' => $sources,
            ];
        }

        $metadata = is_array($unit->metadata) ? $unit->metadata : [];

        return Inertia::render('session/show', [
            'unit' => [
                'id' => $unit->id,
                'slug' => $unit->slug,
                'title' => $unit->title,
                'schedule' => $metadata['schedule'] ?? null,
                'estimatedMinutes' => $unit->estimated_minutes,
            ],
            'blocks' => $blockPayload,
            'resumeBlockId' => $position?->learning_unit_id === $unit->id ? $position->content_block_id : $blocks->first()?->id,
            'status' => $progress->status,
            'gate' => $gate ? ['title' => $gate->title, 'passingScore' => $gate->passing_score] : null,
            'exerciseCount' => DB::table('practice_assignments')->where('learning_unit_id', $unit->id)->count(),
        ]);
    }
}
