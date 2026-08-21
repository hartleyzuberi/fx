<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ResourceLibraryController extends Controller
{
    public function __invoke(): Response
    {
        $enrollment = Enrollment::query()->where('user_id', request()->user()->id)->where('status', 'active')->firstOrFail();
        $resources = DB::table('learning_resources as resources')
            ->leftJoin('source_pages as pages', 'pages.id', '=', 'resources.source_page_id')
            ->where('resources.curriculum_version_id', $enrollment->curriculum_version_id)
            ->orderByRaw("case resources.resource_type when 'book' then 1 when 'academic_paper' then 2 else 3 end")
            ->orderBy('resources.title')
            ->get(['resources.id', 'resources.resource_type', 'resources.title', 'resources.url', 'resources.review_status', 'resources.verified_on', 'pages.physical_page as source_page']);
        $appendices = DB::table('learning_units')
            ->where('curriculum_version_id', $enrollment->curriculum_version_id)
            ->where('unit_type', 'appendix')
            ->orderBy('position')
            ->get(['slug', 'title', 'metadata']);

        return Inertia::render('resources/index', [
            'resources' => $resources,
            'appendices' => $appendices->map(fn ($appendix): array => [
                'slug' => $appendix->slug,
                'title' => $appendix->title,
                'pages' => json_decode($appendix->metadata, true)['canonical_pages'] ?? null,
            ]),
        ]);
    }
}
