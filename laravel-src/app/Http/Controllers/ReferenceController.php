<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\LearningUnit;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceController extends Controller
{
    public function show(LearningUnit $unit): Response
    {
        $enrollment = Enrollment::query()->where('user_id', request()->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id && $unit->unit_type === 'appendix', 404);
        $blocks = $unit->blocks()->with(['mappings.segment.page.version.document'])->get();

        return Inertia::render('resources/show', [
            'unit' => ['title' => $unit->title, 'metadata' => $unit->metadata],
            'blocks' => $blocks->map(fn ($block): array => [
                'id' => $block->id,
                'type' => $block->block_type,
                'title' => $block->title,
                'body' => $block->body,
                'sources' => $block->mappings->map(fn ($mapping): array => [
                    'document' => $mapping->segment->page->version->document->title,
                    'page' => $mapping->segment->page->physical_page,
                ])->unique(fn (array $source): string => $source['document'].'-'.$source['page'])->values(),
            ]),
        ]);
    }
}
