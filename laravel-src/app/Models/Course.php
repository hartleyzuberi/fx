<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property bool $is_active
 * @property-read Collection<int, CurriculumVersion> $versions
 */
class Course extends Model
{
    use HasUlidPrimaryKey;

    protected $fillable = ['slug', 'title', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<CurriculumVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }
}
