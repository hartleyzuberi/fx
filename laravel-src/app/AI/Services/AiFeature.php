<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiFeature
{
    public function enabled(string $key, bool $default): bool
    {
        if (! Schema::hasTable('feature_flags')) {
            return $default;
        }

        return (bool) Cache::remember("feature-flag:{$key}", 30, function () use ($key, $default): bool {
            $value = DB::table('feature_flags')->where('key', $key)->value('enabled');

            return $value === null ? $default : (bool) $value;
        });
    }

    public function forget(string $key): void
    {
        Cache::forget("feature-flag:{$key}");
    }
}
