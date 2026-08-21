<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'knowledge_level' => ['required', Rule::in(['new', 'beginner', 'intermediate', 'advanced'])],
            'trading_experience' => ['required', Rule::in(['none', 'demo', 'live', 'both'])],
            'study_hours_per_week' => ['required', 'integer', 'min:1', 'max:40'],
            'learning_objective' => ['required', 'string', 'min:10', 'max:1000'],
            'preferred_pace' => ['required', Rule::in(['gentle', 'steady', 'intensive'])],
            'timezone' => ['required', 'timezone:all'],
            'reminder_preference' => ['required', Rule::in(['none', 'daily', 'weekdays', 'weekly'])],
            'ai_tutor_enabled' => ['required', 'boolean'],
        ];
    }
}
