<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    public function index(LearningUnit $unit): Response
    {
        $enrollment = $this->enrollmentFor($unit);
        $assignments = DB::table('practice_assignments')->where('learning_unit_id', $unit->id)->orderBy('created_at')->get();
        $submissions = DB::table('practice_submissions')->where('user_id', request()->user()->id)->whereIn('practice_assignment_id', $assignments->pluck('id'))->orderByDesc('revision')->get()->unique('practice_assignment_id')->keyBy('practice_assignment_id');
        $observationCounts = DB::table('practice_observations')
            ->where('user_id', request()->user()->id)
            ->whereIn('practice_assignment_id', $assignments->pluck('id'))
            ->where('status', 'submitted')
            ->selectRaw('practice_assignment_id, COUNT(*) as aggregate')
            ->groupBy('practice_assignment_id')
            ->pluck('aggregate', 'practice_assignment_id');

        return Inertia::render('practice/exercises', [
            'unit' => ['slug' => $unit->slug, 'title' => $unit->title],
            'assignments' => $assignments->map(function ($assignment) use ($submissions, $observationCounts): array {
                $requirements = json_decode($assignment->requirements, true) ?: [];
                $evidenceMode = isset($requirements['evidence_key']);

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'instructions' => $assignment->instructions,
                    'requirements' => $requirements,
                    'evidenceMode' => $evidenceMode,
                    'requiredObservations' => $assignment->required_observations,
                    'observationCount' => (int) ($observationCounts[$assignment->id] ?? 0),
                    'latestSubmission' => isset($submissions[$assignment->id]) ? [
                        'revision' => $submissions[$assignment->id]->revision,
                        'status' => $submissions[$assignment->id]->status,
                        'response' => json_decode($submissions[$assignment->id]->response, true),
                    ] : null,
                ];
            }),
            'enrollmentId' => $enrollment->id,
        ]);
    }

    public function store(Request $request, LearningUnit $unit, string $assignment): RedirectResponse
    {
        $this->enrollmentFor($unit);
        $record = DB::table('practice_assignments')->where('id', $assignment)->where('learning_unit_id', $unit->id)->first();
        abort_unless($record, 404);
        $requirements = json_decode($record->requirements, true) ?: [];

        if (isset($requirements['evidence_key'])) {
            return $this->storeEvidenceObservation($request, $record, $requirements);
        }

        $data = $request->validate([
            'response' => ['required', 'string', 'min:2', 'max:30000'],
            'attachment' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'csv'])->max(10 * 1024)],
        ]);
        $revision = ((int) DB::table('practice_submissions')->where('practice_assignment_id', $assignment)->where('user_id', $request->user()->id)->max('revision')) + 1;
        $attachment = $request->file('attachment')?->store('practice-evidence', 'local');
        DB::table('practice_submissions')->insert([
            'id' => (string) Str::ulid(),
            'practice_assignment_id' => $assignment,
            'user_id' => $request->user()->id,
            'revision' => $revision,
            'status' => 'submitted',
            'response' => json_encode(['text' => $data['response']], JSON_THROW_ON_ERROR),
            'attachment_path' => $attachment,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "Exercise response saved as revision {$revision}.");
    }

    /** @param object $assignment @param array<string, mixed> $requirements */
    private function storeEvidenceObservation(Request $request, object $assignment, array $requirements): RedirectResponse
    {
        $attachmentRequired = (bool) ($requirements['attachment_required'] ?? false);
        $data = $request->validate([
            'response' => ['required', 'string', 'min:2', 'max:30000'],
            'attachment' => [
                $attachmentRequired ? 'required' : 'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'csv'])->max(10 * 1024),
            ],
        ]);
        $attachment = $request->file('attachment')?->store('practice-evidence', 'local');
        $sequence = ((int) DB::table('practice_observations')
            ->where('practice_assignment_id', $assignment->id)
            ->where('user_id', $request->user()->id)
            ->max('sequence')) + 1;

        DB::table('practice_observations')->insert([
            'id' => (string) Str::ulid(),
            'practice_assignment_id' => $assignment->id,
            'user_id' => $request->user()->id,
            'sequence' => $sequence,
            'status' => 'submitted',
            'response' => json_encode([
                'text' => $data['response'],
                'evidence_key' => $requirements['evidence_key'],
                'learner_attestation' => true,
            ], JSON_THROW_ON_ERROR),
            'attachment_path' => $attachment,
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $required = (int) ($assignment->required_observations ?? 1);
        $progress = min($sequence, $required);

        return back()->with('success', "Evidence observation {$sequence} saved. Progress: {$progress}/{$required} required observations.");
    }

    private function enrollmentFor(LearningUnit $unit): Enrollment
    {
        $enrollment = Enrollment::query()->where('user_id', request()->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        $progress = UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->firstOrFail();
        abort_if($progress->status === 'locked', 403);

        return $enrollment;
    }
}
