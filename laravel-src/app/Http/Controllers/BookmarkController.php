<?php

namespace App\Http\Controllers;

use App\Models\ContentBlock;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookmarkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['content_block_id' => ['required', 'string', 'exists:content_blocks,id']]);
        $enrollment = Enrollment::query()->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        $block = ContentBlock::query()->with('unit')->findOrFail($data['content_block_id']);
        abort_unless($block->unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        DB::table('bookmarks')->insertOrIgnore([
            'id' => (string) Str::ulid(), 'user_id' => $request->user()->id,
            'content_block_id' => $block->id, 'label' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Bookmark saved.');
    }

    public function destroy(Request $request, string $bookmark): RedirectResponse
    {
        DB::table('bookmarks')->where('id', $bookmark)->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Bookmark removed.');
    }
}
