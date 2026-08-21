<?php

namespace App\Http\Controllers\Settings;

use App\AI\Services\RuntimeModeResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiPreferenceController extends Controller
{
    public function edit(Request $request, RuntimeModeResolver $modes): Response
    {
        $profile = $request->user()->learnerProfile;

        return Inertia::render('settings/ai-preferences', ['preferences' => ['enabled' => (bool) $profile?->ai_tutor_enabled,
            'allowCloud' => (bool) $profile?->allow_cloud_ai, 'allowLocal' => (bool) $profile?->allow_local_ai], 'runtimeMode' => $modes->resolve($request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['ai_tutor_enabled' => ['required', 'boolean'], 'allow_cloud_ai' => ['required', 'boolean'], 'allow_local_ai' => ['required', 'boolean']]);
        $request->user()->learnerProfile()->updateOrCreate(['user_id' => $request->user()->id], [...$data, 'ai_consent_at' => $data['ai_tutor_enabled'] ? now() : null]);

        return back()->with('success', 'AI preferences updated. Core learning remains available in every mode.');
    }
}
