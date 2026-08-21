<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'role' => ['nullable', Rule::in(['student', 'reviewer', 'admin'])], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $users = User::query()->with('learnerProfile')
            ->withCount(['enrollments', 'enrollments as active_enrollments_count' => fn ($query) => $query->where('status', 'active')])
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($data['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->latest()->paginate(25)->withQueryString();
        $userRows = $users->getCollection()->map(function (User $user): array {
            $enrollment = DB::table('enrollments')->where('user_id', $user->id)->where('status', 'active')->latest()->first();
            $progress = $enrollment ? DB::table('unit_progress')->where('enrollment_id', $enrollment->id)
                ->selectRaw('count(*) as total, sum(case when status in (\'passed\', \'mastered\') then 1 else 0 end) as completed')->first() : null;

            return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role,
                'is_active' => (bool) $user->is_active, 'email_verified_at' => $user->email_verified_at, 'created_at' => $user->created_at,
                'ai_enabled' => (bool) $user->learnerProfile?->ai_tutor_enabled, 'enrollments_count' => $user->enrollments_count,
                'active_enrollments_count' => $user->active_enrollments_count, 'completed_units' => (int) ($progress ? $progress->completed : 0),
                'total_units' => (int) ($progress ? $progress->total : 0)];
        })->values()->all();

        return Inertia::render('admin/users', ['users' => ['data' => $userRows, 'links' => $users->linkCollection(), 'from' => $users->firstItem(), 'to' => $users->lastItem(), 'total' => $users->total()], 'filters' => $data,
            'summary' => ['total' => User::query()->count(), 'students' => User::query()->where('role', 'student')->count(),
                'admins' => User::query()->where('role', 'admin')->count(), 'inactive' => User::query()->where('is_active', false)->count()]]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(['student', 'reviewer', 'admin'])], 'is_active' => ['required', 'boolean']]);
        abort_if($request->user()->is($user) && ($data['role'] !== 'admin' || ! $data['is_active']), 422, 'You cannot demote or deactivate your own active administrator account.');
        $removesAdmin = $user->role === 'admin' && ($data['role'] !== 'admin' || ! $data['is_active']);
        abort_if($removesAdmin && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1, 422, 'At least one active administrator must remain.');
        $before = ['role' => $user->role, 'is_active' => (bool) $user->is_active];
        $user->forceFill($data)->save();
        DB::table('admin_audit_events')->insert(['id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'event_type' => 'user_access_updated',
            'subject_type' => 'user', 'subject_id' => (string) $user->id, 'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($data, JSON_THROW_ON_ERROR), 'ip_address' => $request->ip(), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'User access updated.');
    }
}
