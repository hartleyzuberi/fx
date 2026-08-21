<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $course_id
 * @property string|null $supersedes_id
 * @property string $version_label
 * @property string $status
 * @property string|null $change_summary
 * @property Carbon|null $published_at
 * @property-read Course $course
 * @property-read Collection<int, LearningUnit> $units
 */
class CurriculumVersion extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['course_id', 'supersedes_id', 'version_label', 'status', 'change_summary', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<LearningUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(LearningUnit::class);
    }
}
