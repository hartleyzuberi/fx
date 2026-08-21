<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $ai_tutor_enabled
 * @property bool $allow_cloud_ai
 * @property bool $allow_local_ai
 */
class LearnerProfile extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['user_id', 'knowledge_level', 'trading_experience', 'study_hours_per_week', 'learning_objective', 'preferred_pace', 'timezone', 'reminder_preference', 'ai_tutor_enabled', 'allow_cloud_ai', 'allow_local_ai', 'ai_consent_at', 'onboarding_completed_at'];

    protected function casts(): array
    {
        return ['ai_tutor_enabled' => 'boolean', 'allow_cloud_ai' => 'boolean', 'allow_local_ai' => 'boolean', 'ai_consent_at' => 'datetime', 'onboarding_completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
