<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasteryService
{
    /** @param array<string, mixed> $result */
    public function recordAnswer(Enrollment $enrollment, Question $question, string $answerAttemptId, array $result): void
    {
        if (! $question->concept_id) {
            return;
        }

        $existing = DB::table('concept_mastery')
            ->where('enrollment_id', $enrollment->id)
            ->where('concept_id', $question->concept_id)
            ->first();
        $count = (int) ($existing->evidence_count ?? 0);
        $score = (float) $result['mastery_score'];
        $average = round((((float) ($existing->score ?? 0) * $count) + $score) / ($count + 1), 2);
        $status = $average >= 85 ? 'mastered' : ($average >= 50 ? 'developing' : 'needs_review');
        $nextReview = now()->addDays($status === 'mastered' ? 14 : ($status === 'developing' ? 7 : 1));
        $masteryId = $existing->id ?? (string) Str::ulid();

        DB::table('concept_mastery')->updateOrInsert(
            ['enrollment_id' => $enrollment->id, 'concept_id' => $question->concept_id],
            [
                'id' => $masteryId,
                'status' => $status,
                'score' => $average,
                'evidence_count' => $count + 1,
                'last_practiced_at' => now(),
                'next_review_at' => $nextReview,
                'created_at' => $existing->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        DB::table('mastery_evidence')->insert([
            'id' => (string) Str::ulid(),
            'concept_mastery_id' => $masteryId,
            'evidence_type' => 'assessment_answer',
            'evidence_id' => $answerAttemptId,
            'score' => $score,
            'weight' => 1,
            'details' => json_encode([
                'status' => $result['status'],
                'requires_remediation' => $result['requires_remediation'],
            ], JSON_THROW_ON_ERROR),
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($status === 'mastered') {
            DB::table('review_items')
                ->where('enrollment_id', $enrollment->id)
                ->where('concept_id', $question->concept_id)
                ->whereNull('completed_at')
                ->update(['completed_at' => now(), 'updated_at' => now()]);

            return;
        }

        if (! (bool) $result['requires_remediation']) {
            return;
        }

        $reason = $result['status'] === 'misconception' ? 'misconception' : 'low_mastery';
        $priority = $reason === 'misconception' ? 100 : 70;
        $pending = DB::table('review_items')
            ->where('enrollment_id', $enrollment->id)
            ->where('concept_id', $question->concept_id)
            ->whereNull('completed_at')
            ->first();

        if ($pending) {
            DB::table('review_items')->where('id', $pending->id)->update([
                'reason' => $reason,
                'priority' => max((int) $pending->priority, $priority),
                'due_at' => now()->addDay(),
                'metadata' => json_encode(['answer_attempt_id' => $answerAttemptId], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('review_items')->insert([
            'id' => (string) Str::ulid(),
            'enrollment_id' => $enrollment->id,
            'concept_id' => $question->concept_id,
            'reason' => $reason,
            'priority' => $priority,
            'due_at' => now()->addDay(),
            'metadata' => json_encode(['answer_attempt_id' => $answerAttemptId], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
