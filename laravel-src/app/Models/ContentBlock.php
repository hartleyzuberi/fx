<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentBlock extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['learning_unit_id', 'block_type', 'position', 'title', 'body', 'payload', 'is_required'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'is_required' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ContentMapping::class);
    }
}
