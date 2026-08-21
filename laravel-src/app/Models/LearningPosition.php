<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class LearningPosition extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resumed_at' => 'datetime'];
    }
}
