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

        $payload = [];
        foreach ($blocks as $block) {
            $sources = [];
            $seen = [];
            foreach ($block->mappings as $mapping) {
                $document = $mapping->segment->page->version->document->title;
                $page = $mapping->segment->page->physical_page;
                $key = $document.'-'.$page;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $sources[] = ['document' => $document, 'page' => $page];
            }

            $payload[] = [
                'id' => $block->id,
                'type' => $block->block_type,
                'title' => $block->title,
                'body' => $block->body,
                'sources' => $sources,
            ];
        }

        return Inertia::render('resources/show', [
            'unit' => ['title' => $unit->title, 'metadata' => $unit->metadata],
            'blocks' => $payload,
        ]);
    }
}
