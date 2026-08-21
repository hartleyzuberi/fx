<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\LearningPosition;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $user = request()->user();
        if (! $user->learnerProfile?->onboarding_completed_at) {
            return to_route('onboarding.create');
        }

        $enrollment = Enrollment::query()
            ->with('version.course')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
        $position = LearningPosition::query()->where('enrollment_id', $enrollment->id)->first();
        $current = $position ? LearningUnit::query()->find($position->learning_unit_id) : null;
        $sessionProgress = UnitProgress::query()->where('enrollment_id', $enrollment->id)
            ->whereHas('unit', fn ($query) => $query->where('unit_type', 'session'));
        $total = (clone $sessionProgress)->count();
        $complete = (clone $sessionProgress)->whereIn('status', ['passed', 'mastered'])->count();
        $reviewCount = DB::table('review_items')
            ->where('enrollment_id', $enrollment->id)
            ->whereNull('completed_at')
            ->count();

        return Inertia::render('dashboard', [
            'course' => [
                'title' => $enrollment->version->course->title,
                'version' => $enrollment->version->version_label,
            ],
            'currentUnit' => $current ? [
                'title' => $current->title,
                'slug' => $current->slug,
                'schedule' => $current->metadata['schedule'] ?? null,
                'estimatedMinutes' => $current->estimated_minutes,
            ] : null,
            'progress' => [
                'completed' => $complete,
                'total' => $total,
                'percent' => $total ? round(($complete / $total) * 100) : 0,
            ],
            'mastery' => [
                'mastered' => (clone $sessionProgress)->where('status', 'mastered')->count(),
                'needsReview' => $reviewCount,
            ],
            'profile' => [
                'pace' => $user->learnerProfile->preferred_pace,
                'studyHours' => $user->learnerProfile->study_hours_per_week,
                'aiTutorEnabled' => $user->learnerProfile->ai_tutor_enabled,
            ],
        ]);
    }
}
