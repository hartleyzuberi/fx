<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitProgress extends Model
{
    use HasUlidPrimaryKey;

    protected $table = 'unit_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'passed_at' => 'datetime', 'mastered_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }
}
