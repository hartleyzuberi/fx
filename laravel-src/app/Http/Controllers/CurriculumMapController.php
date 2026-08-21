<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumMapController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $user = request()->user();
        if (! $user->learnerProfile?->onboarding_completed_at) {
            return to_route('onboarding.create');
        }
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $progress = UnitProgress::query()->where('enrollment_id', $enrollment->id)->get()->keyBy('learning_unit_id');
        $phases = LearningUnit::query()->where('curriculum_version_id', $enrollment->curriculum_version_id)
            ->where('unit_type', 'phase')->with('children')->orderBy('position')->get();

        return Inertia::render('course-map', [
            'phases' => $phases->map(fn (LearningUnit $phase): array => [
                'id' => $phase->id, 'title' => $phase->title,
                'status' => $progress->get($phase->id)?->status ?? 'locked',
                'sessions' => $phase->children->map(fn (LearningUnit $unit): array => [
                    'id' => $unit->id, 'slug' => $unit->slug, 'title' => $unit->title,
                    'schedule' => $unit->metadata['schedule'] ?? null,
                    'status' => $progress->get($unit->id)?->status ?? 'locked',
                    'lockReason' => ($progress->get($unit->id)?->status ?? 'locked') === 'locked' ? 'Master the preceding required session and its assessment before this session unlocks.' : null,
                ])->values(),
            ])->values(),
        ]);
    }
}
