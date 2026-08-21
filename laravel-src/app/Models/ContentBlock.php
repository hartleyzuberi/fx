<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $learning_unit_id
 * @property string $block_type
 * @property int $position
 * @property string|null $title
 * @property string|null $body
 * @property array<string, mixed>|null $payload
 * @property bool $is_required
 * @property-read LearningUnit $unit
 * @property-read Collection<int, ContentMapping> $mappings
 */
class ContentBlock extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['learning_unit_id', 'block_type', 'position', 'title', 'body', 'payload', 'is_required'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'is_required' => 'boolean'];
    }

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return HasMany<ContentMapping, $this> */
    public function mappings(): HasMany
    {
        return $this->hasMany(ContentMapping::class);
    }
}
