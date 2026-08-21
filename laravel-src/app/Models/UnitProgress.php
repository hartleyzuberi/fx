<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $enrollment_id
 * @property string $learning_unit_id
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $passed_at
 * @property Carbon|null $mastered_at
 * @property Carbon|null $last_activity_at
 * @property-read LearningUnit $unit
 */
class UnitProgress extends Model
{
    use HasUlidPrimaryKey;

    protected $table = 'unit_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'passed_at' => 'datetime', 'mastered_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }
}
