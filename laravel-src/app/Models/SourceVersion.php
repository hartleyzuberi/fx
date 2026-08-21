<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $source_document_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $imported_at
 * @property-read SourceDocument $document
 */
class SourceVersion extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'imported_at' => 'datetime'];
    }

    /** @return BelongsTo<SourceDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id');
    }
}
