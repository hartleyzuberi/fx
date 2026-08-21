<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $source_segment_id
 * @property string $content_block_id
 * @property string $mapping_type
 * @property string|null $review_status
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property-read SourceSegment $segment
 */
class ContentMapping extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['source_segment_id', 'content_block_id', 'mapping_type', 'review_status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<SourceSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(SourceSegment::class, 'source_segment_id');
    }
}
