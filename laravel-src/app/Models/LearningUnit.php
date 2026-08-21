<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $curriculum_version_id
 * @property string|null $parent_id
 * @property string $unit_type
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property int $position
 * @property int|null $estimated_minutes
 * @property bool $is_safety_critical
 * @property array<string, mixed>|null $metadata
 * @property-read LearningUnit|null $parent
 * @property-read Collection<int, LearningUnit> $children
 * @property-read Collection<int, ContentBlock> $blocks
 */
class LearningUnit extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['curriculum_version_id', 'parent_id', 'unit_type', 'slug', 'title', 'summary', 'materialized_path', 'position', 'estimated_minutes', 'is_safety_critical', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'is_safety_critical' => 'boolean'];
    }

    /** @return BelongsTo<LearningUnit, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<LearningUnit, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /** @return HasMany<ContentBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('position');
    }
}
