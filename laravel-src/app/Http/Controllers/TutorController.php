<?php

namespace App\Http\Controllers;

use App\Exceptions\TutorUnavailableException;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Services\Tutor\TutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TutorController extends Controller
{
    public function store(Request $request, LearningUnit $unit, TutorService $tutor): JsonResponse
    {
        $data = $request->validate(['prompt' => ['required', 'string', 'min:3', 'max:5000'], 'thread_id' => ['nullable', 'string'], 'request_id' => ['nullable', 'string', 'max:80']]);
        $enrollment = Enrollment::query()->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        $threadId = $data['thread_id'] ?? (string) Str::ulid();
        if (isset($data['thread_id'])) {
            abort_unless(DB::table('tutor_threads')->where('id', $threadId)->where('user_id', $request->user()->id)->exists(), 404);
        } else {
            DB::table('tutor_threads')->insert(['id' => $threadId, 'user_id' => $request->user()->id, 'learning_unit_id' => $unit->id, 'thread_type' => 'lesson', 'title' => Str::limit($data['prompt'], 100), 'created_at' => now(), 'updated_at' => now()]);
        }
        if (isset($data['request_id'])) {
            $existing = DB::table('tutor_messages')->where('tutor_thread_id', $threadId)->where('client_request_id', $data['request_id'])->where('role', 'assistant')->first();
            if ($existing) {
                return response()->json(['thread_id' => $threadId, 'message' => $existing->content, 'citations' => json_decode($existing->citations ?? '[]', true), 'safety_status' => $existing->safety_status, 'duplicate' => true]);
            }
        }
        DB::table('tutor_messages')->insert(['id' => (string) Str::ulid(), 'tutor_thread_id' => $threadId, 'role' => 'user', 'content' => $data['prompt'], 'safety_status' => 'pending', 'client_request_id' => $data['request_id'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        $assessmentIds = DB::table('assessments')->where('learning_unit_id', $unit->id)->pluck('id');
        $activeAssessment = $assessmentIds->isNotEmpty() && ! DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->whereIn('assessment_id', $assessmentIds)->where('passed', true)->exists();
        $protectedPrompts = $activeAssessment ? array_values(DB::table('questions')->whereIn('assessment_id', $assessmentIds)->pluck('prompt')->map(fn ($prompt): string => (string) $prompt)->all()) : [];

        try {
            $reply = $tutor->reply($request->user(), $unit, $data['prompt'], $activeAssessment, $protectedPrompts);
        } catch (TutorUnavailableException $exception) {
            DB::table('tutor_messages')->insert(['id' => (string) Str::ulid(), 'tutor_thread_id' => $threadId, 'role' => 'assistant', 'content' => $exception->getMessage(), 'safety_status' => 'unavailable', 'client_request_id' => $data['request_id'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['thread_id' => $threadId, 'error' => $exception->getMessage()], 503);
        }
        DB::table('tutor_messages')->insert(['id' => (string) Str::ulid(), 'tutor_thread_id' => $threadId, 'role' => 'assistant', 'content' => $reply['text'], 'citations' => json_encode($reply['citations'], JSON_THROW_ON_ERROR), 'provider' => $reply['provider'], 'model' => $reply['model'], 'response_id' => $reply['response_id'], 'safety_status' => $reply['safety_status'], 'client_request_id' => $data['request_id'] ?? null, 'metadata' => json_encode(['runtime_mode' => $reply['runtime_mode'], 'generated' => $reply['generated']], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['thread_id' => $threadId, 'message' => $reply['text'], 'citations' => $reply['citations'], 'safety_status' => $reply['safety_status'], 'runtime_mode' => $reply['runtime_mode'], 'generated' => $reply['generated']]);
    }
}
