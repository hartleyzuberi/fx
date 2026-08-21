<?php

namespace App\Services\Trading;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PracticeAccessService
{
    public function assertChapterAvailable(User $user, int $chapter): void
    {
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('status', 'active')->first();
        abort_unless($enrollment, 403, 'Complete onboarding before using this practice system.');
        $unit = DB::table('learning_units')->where('curriculum_version_id', $enrollment->curriculum_version_id)->where('metadata->chapter', $chapter)->first();
        abort_unless($unit, 403);
        $status = DB::table('unit_progress')->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->value('status');
        abort_if($status === null || $status === 'locked', 403, "Chapter {$chapter} must be available before using this practice system.");
    }
}
