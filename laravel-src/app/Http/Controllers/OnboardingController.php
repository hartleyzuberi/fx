<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingRequest;
use App\Models\LearnerProfile;
use App\Services\Learning\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (request()->user()->learnerProfile?->onboarding_completed_at) {
            return to_route('dashboard');
        }

        return Inertia::render('onboarding', [
            'defaults' => [
                'timezone' => 'Africa/Nairobi',
                'ai_tutor_enabled' => false,
            ],
        ]);
    }

    public function store(StoreOnboardingRequest $request, EnrollmentService $enrollment): RedirectResponse
    {
        $data = $request->validated();
        $aiEnabled = (bool) $data['ai_tutor_enabled'];
        LearnerProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [...$data, 'ai_consent_at' => $aiEnabled ? now() : null, 'onboarding_completed_at' => now()],
        );
        $enrollment->enroll($request->user());

        return to_route('dashboard')->with('success', 'Your learning path is ready.');
    }
}
