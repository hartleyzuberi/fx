<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceSegment extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['heading_hierarchy' => 'array', 'metadata' => 'array', 'is_meaningful' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SourcePage::class, 'source_page_id');
    }
}
