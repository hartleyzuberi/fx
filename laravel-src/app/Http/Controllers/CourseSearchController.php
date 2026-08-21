<?php

namespace App\Http\Controllers;

use App\Services\Tutor\CourseRetriever;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseSearchController extends Controller
{
    public function __invoke(Request $request, CourseRetriever $retriever): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:300']]);

        return response()->json(['results' => $retriever->search($data['q']), 'mode' => config('ai.semantic_search_enabled') ? 'hybrid' : 'lexical']);
    }
}
