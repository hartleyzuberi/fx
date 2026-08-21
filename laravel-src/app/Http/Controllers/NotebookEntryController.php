<?php

namespace App\Http\Controllers;

use App\Models\ContentBlock;
use App\Models\Enrollment;
use App\Models\UnitProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NotebookEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'learning_unit_id' => ['nullable', 'string', 'exists:learning_units,id'],
            'content_block_id' => ['nullable', 'string', 'exists:content_blocks,id'],
            'notebook_type' => ['required', Rule::in(['concept', 'chart', 'macro', 'trading_journal'])],
            'entry_type' => ['required', Rule::in(['note', 'own_words', 'note_this', 'reflection'])],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'required_without:physical_task_completed', 'string', 'max:20000'],
            'physical_task_completed' => ['sometimes', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $enrollment = Enrollment::query()->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        if ($data['learning_unit_id'] ?? null) {
            $progress = UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $data['learning_unit_id'])->firstOrFail();
            abort_if($progress->status === 'locked', 403, 'This notebook task is not available yet.');
        }
        if (($data['content_block_id'] ?? null) && ($data['learning_unit_id'] ?? null)) {
            abort_unless(ContentBlock::query()->where('id', $data['content_block_id'])->where('learning_unit_id', $data['learning_unit_id'])->exists(), 422);
        }
        $attachment = $request->file('attachment')?->store('notebook-attachments', 'local');
        unset($data['attachment']);
        DB::table('notebook_entries')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $request->user()->id, ...$data,
            'body' => $data['body'] ?? null, 'attachment_path' => $attachment,
            'physical_task_completed' => (bool) ($data['physical_task_completed'] ?? false), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Saved to your notebook.');
    }
}
