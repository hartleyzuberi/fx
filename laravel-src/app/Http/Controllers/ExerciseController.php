<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    public function index(LearningUnit $unit): Response
    {
        $enrollment = $this->enrollmentFor($unit);
        $assignments = DB::table('practice_assignments')->where('learning_unit_id', $unit->id)->orderBy('created_at')->get();
        $submissions = DB::table('practice_submissions')->where('user_id', request()->user()->id)->whereIn('practice_assignment_id', $assignments->pluck('id'))->orderByDesc('revision')->get()->unique('practice_assignment_id')->keyBy('practice_assignment_id');

        return Inertia::render('practice/exercises', [
            'unit' => ['slug' => $unit->slug, 'title' => $unit->title],
            'assignments' => $assignments->map(fn ($assignment): array => [
                'id' => $assignment->id, 'title' => $assignment->title, 'instructions' => $assignment->instructions,
                'requirements' => json_decode($assignment->requirements, true),
                'latestSubmission' => isset($submissions[$assignment->id]) ? [
                    'revision' => $submissions[$assignment->id]->revision,
                    'status' => $submissions[$assignment->id]->status,
                    'response' => json_decode($submissions[$assignment->id]->response, true),
                ] : null,
            ]),
            'enrollmentId' => $enrollment->id,
        ]);
    }

    public function store(Request $request, LearningUnit $unit, string $assignment): RedirectResponse
    {
        $this->enrollmentFor($unit);
        $record = DB::table('practice_assignments')->where('id', $assignment)->where('learning_unit_id', $unit->id)->first();
        abort_unless($record, 404);
        $data = $request->validate(['response' => ['required', 'string', 'min:2', 'max:30000'], 'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,csv', 'max:10240']]);
        $revision = ((int) DB::table('practice_submissions')->where('practice_assignment_id', $assignment)->where('user_id', $request->user()->id)->max('revision')) + 1;
        $attachment = $request->file('attachment')?->store('practice-evidence', 'local');
        DB::table('practice_submissions')->insert([
            'id' => (string) Str::ulid(), 'practice_assignment_id' => $assignment, 'user_id' => $request->user()->id,
            'revision' => $revision, 'status' => 'submitted', 'response' => json_encode(['text' => $data['response']], JSON_THROW_ON_ERROR),
            'attachment_path' => $attachment, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', "Exercise response saved as revision {$revision}.");
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
