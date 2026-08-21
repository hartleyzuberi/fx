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
 * @property string $user_id
 * @property string $curriculum_version_id
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read CurriculumVersion $version
 * @property-read Collection<int, UnitProgress> $progress
 */
class Enrollment extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['user_id', 'curriculum_version_id', 'status', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<CurriculumVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'curriculum_version_id');
    }

    /** @return HasMany<UnitProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(UnitProgress::class);
    }
}
