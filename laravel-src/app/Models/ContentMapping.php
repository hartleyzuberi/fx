<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMapping extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['source_segment_id', 'content_block_id', 'mapping_type', 'review_status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SourceSegment::class, 'source_segment_id');
    }
}
