<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $source_version_id
 * @property int $physical_page
 * @property string|null $logical_page
 * @property array<string, mixed>|null $markers
 * @property-read SourceVersion $version
 */
class SourcePage extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['markers' => 'array'];
    }

    /** @return BelongsTo<SourceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SourceVersion::class, 'source_version_id');
    }
}
