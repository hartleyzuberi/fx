<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningUnit extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['curriculum_version_id', 'parent_id', 'unit_type', 'slug', 'title', 'summary', 'materialized_path', 'position', 'estimated_minutes', 'is_safety_critical', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'is_safety_critical' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('position');
    }
}
