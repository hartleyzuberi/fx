<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourcePage extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['markers' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SourceVersion::class, 'source_version_id');
    }
}
