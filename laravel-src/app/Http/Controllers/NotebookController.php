<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotebookController extends Controller
{
    public function index(): Response
    {
        $entries = DB::table('notebook_entries')->leftJoin('learning_units', 'learning_units.id', '=', 'notebook_entries.learning_unit_id')
            ->where('notebook_entries.user_id', request()->user()->id)
            ->latest('notebook_entries.created_at')
            ->get(['notebook_entries.id', 'notebook_entries.notebook_type', 'notebook_entries.entry_type', 'notebook_entries.title', 'notebook_entries.body', 'notebook_entries.physical_task_completed', 'notebook_entries.created_at', 'learning_units.title as unit_title']);

        return Inertia::render('notebooks/index', ['entries' => $entries]);
    }
}
