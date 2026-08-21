<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $concept_id
 * @property string|null $learning_objective_id
 * @property string $question_type
 * @property string|null $structured_type
 * @property string $structured_status
 * @property string $question_version
 * @property string $prompt
 * @property float $points
 * @property array<string, mixed>|null $choices
 * @property array<string, mixed>|null $structured_choices
 * @property array<string, mixed>|null $answer_key
 * @property array<string, mixed>|null $structured_answer_key
 */
class Question extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'choices' => 'array',
            'structured_choices' => 'array',
            'answer_key' => 'array',
            'structured_answer_key' => 'array',
            'metadata' => 'array',
            'requires_working' => 'boolean',
        ];
    }

    public function hasApprovedStructuredVariant(): bool
    {
        return $this->structured_status === 'approved'
            && is_string($this->structured_type)
            && $this->structured_type !== ''
            && is_array($this->structured_answer_key);
    }
}
