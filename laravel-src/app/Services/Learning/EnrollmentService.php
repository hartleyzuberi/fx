<?php

namespace App\Services\Learning;

use App\Models\CurriculumVersion;
use App\Models\Enrollment;
use App\Models\LearningPosition;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnrollmentService
{
    public function enroll(User $user): Enrollment
    {
        return DB::transaction(function () use ($user): Enrollment {
            $version = CurriculumVersion::query()->where('status', 'published')->latest('published_at')->first();
            if (! $version) {
                throw new RuntimeException('No published curriculum is available. Run course:ingest first.');
            }

            $enrollment = Enrollment::query()->firstOrCreate(
                ['user_id' => $user->id, 'curriculum_version_id' => $version->id],
                ['status' => 'active', 'started_at' => now()],
            );
            $units = LearningUnit::query()->where('curriculum_version_id', $version->id)->orderBy('position')->get();
            $firstSession = $units->firstWhere('unit_type', 'session');
            foreach ($units as $unit) {
                $available = $unit->id === $firstSession?->id || ($unit->unit_type === 'phase' && $unit->position === 1);
                UnitProgress::query()->firstOrCreate(
                    ['enrollment_id' => $enrollment->id, 'learning_unit_id' => $unit->id],
                    ['status' => $available ? 'available' : 'locked'],
                );
            }

            if ($firstSession) {
                LearningPosition::query()->firstOrCreate(
                    ['enrollment_id' => $enrollment->id],
                    ['learning_unit_id' => $firstSession->id, 'content_block_id' => $firstSession->blocks()->value('id')],
                );
            }

            return $enrollment;
        });
    }
}
