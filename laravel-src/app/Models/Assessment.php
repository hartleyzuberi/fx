<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $learning_unit_id
 * @property string $assessment_type
 * @property string $title
 * @property float $passing_score
 * @property bool $is_gate
 * @property bool $answers_protected
 * @property array<string, mixed>|null $rules
 * @property Collection<int, Question> $questions
 */
class Assessment extends Model
{
    use HasUlidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rules' => 'array', 'is_gate' => 'boolean', 'answers_protected' => 'boolean'];
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }
}
