<?php

namespace App\Http\Controllers;

use App\Models\ContentBlock;
use App\Models\Enrollment;
use App\Models\LearningPosition;
use App\Models\LearningUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionPositionController extends Controller
{
    public function update(Request $request, LearningUnit $unit): JsonResponse
    {
        $validated = $request->validate(['content_block_id' => ['required', 'string'], 'block_offset' => ['nullable', 'integer', 'min:0']]);
        $enrollment = Enrollment::query()->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        abort_unless(ContentBlock::query()->where('id', $validated['content_block_id'])->where('learning_unit_id', $unit->id)->exists(), 422);
        LearningPosition::query()->updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            ['learning_unit_id' => $unit->id, 'content_block_id' => $validated['content_block_id'], 'block_offset' => $validated['block_offset'] ?? 0, 'resumed_at' => now()],
        );

        return response()->json(['saved' => true]);
    }
}
