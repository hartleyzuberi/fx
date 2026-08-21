<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $concept_id
 * @property string $prompt
 * @property float $points
 * @property array<string, mixed>|null $answer_key
 */
class Question extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['choices' => 'array', 'answer_key' => 'array', 'requires_working' => 'boolean'];
    }
}
