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

        $phasePayload = [];
        foreach ($phases as $phase) {
            $phaseProgress = $progress->get($phase->id);
            $phaseStatus = $phaseProgress instanceof UnitProgress ? $phaseProgress->status : 'locked';
            $sessions = [];

            foreach ($phase->children as $unit) {
                $unitProgress = $progress->get($unit->id);
                $status = $unitProgress instanceof UnitProgress ? $unitProgress->status : 'locked';
                $metadata = is_array($unit->metadata) ? $unit->metadata : [];
                $sessions[] = [
                    'id' => $unit->id,
                    'slug' => $unit->slug,
                    'title' => $unit->title,
                    'schedule' => $metadata['schedule'] ?? null,
                    'status' => $status,
                    'lockReason' => $status === 'locked'
                        ? 'Master the preceding required session and its assessment before this session unlocks.'
                        : null,
                ];
            }

            $phasePayload[] = [
                'id' => $phase->id,
                'title' => $phase->title,
                'status' => $phaseStatus,
                'sessions' => $sessions,
            ];
        }

        return Inertia::render('course-map', ['phases' => $phasePayload]);
    }
}
