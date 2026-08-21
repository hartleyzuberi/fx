<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReviewQueueController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $user = request()->user();
        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return to_route('dashboard');
        }

        $rows = DB::table('review_items')
            ->join('concepts', 'concepts.id', '=', 'review_items.concept_id')
            ->where('review_items.enrollment_id', $enrollment->id)
            ->whereNull('review_items.completed_at')
            ->orderByDesc('review_items.priority')
            ->orderBy('review_items.due_at')
            ->get([
                'review_items.id',
                'review_items.concept_id',
                'review_items.reason',
                'review_items.priority',
                'review_items.due_at',
                'review_items.metadata',
                'concepts.name as concept_name',
                'concepts.definition as concept_definition',
            ]);

        $items = $rows->map(function (object $row): array {
            $metadata = json_decode((string) $row->metadata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            $answerAttemptId = isset($metadata['answer_attempt_id']) ? (string) $metadata['answer_attempt_id'] : null;

            $recommendation = $answerAttemptId
                ? DB::table('grading_recommendations')
                    ->where('answer_attempt_id', $answerAttemptId)
                    ->orderByDesc('created_at')
                    ->first(['feedback', 'missing_concepts', 'misconceptions', 'status'])
                : null;

            $unit = DB::table('concept_learning_unit')
                ->join('learning_units', 'learning_units.id', '=', 'concept_learning_unit.learning_unit_id')
                ->where('concept_learning_unit.concept_id', $row->concept_id)
                ->where('concept_learning_unit.relationship', 'teaches')
                ->orderByDesc('concept_learning_unit.weight')
                ->orderBy('learning_units.position')
                ->first(['learning_units.slug', 'learning_units.title']);

            return [
                'id' => (string) $row->id,
                'conceptId' => (string) $row->concept_id,
                'concept' => (string) $row->concept_name,
                'definition' => $row->concept_definition ? (string) $row->concept_definition : null,
                'reason' => (string) $row->reason,
                'priority' => (int) $row->priority,
                'dueAt' => (string) $row->due_at,
                'feedback' => $recommendation?->feedback ? (string) $recommendation->feedback : null,
                'missingConcepts' => $this->jsonList($recommendation?->missing_concepts ?? null),
                'misconceptions' => $this->jsonList($recommendation?->misconceptions ?? null),
                'gradingStatus' => $recommendation?->status ? (string) $recommendation->status : null,
                'unit' => $unit ? ['slug' => (string) $unit->slug, 'title' => (string) $unit->title] : null,
            ];
        })->values();

        return Inertia::render('review/index', [
            'items' => $items,
            'summary' => [
                'total' => $items->count(),
                'misconceptions' => $items->where('reason', 'misconception')->count(),
                'highPriority' => $items->where('priority', '>=', 90)->count(),
            ],
        ]);
    }

    /** @return list<string> */
    private function jsonList(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map('strval', array_filter($decoded, fn (mixed $item): bool => is_scalar($item))));
    }
}
