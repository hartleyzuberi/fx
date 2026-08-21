<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $source_page_id
 * @property array<string, mixed>|null $heading_hierarchy
 * @property array<string, mixed>|null $metadata
 * @property bool $is_meaningful
 * @property-read SourcePage $page
 */
class SourceSegment extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['heading_hierarchy' => 'array', 'metadata' => 'array', 'is_meaningful' => 'boolean'];
    }

    /** @return BelongsTo<SourcePage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(SourcePage::class, 'source_page_id');
    }
}
