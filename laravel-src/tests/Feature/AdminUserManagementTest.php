<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users_and_change_student_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/users')->has('users.data', 2));
        $this->actingAs($admin)->patch(route('admin.users.update', $student), ['role' => 'reviewer', 'is_active' => false])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'role' => 'reviewer', 'is_active' => false]);
        $this->assertDatabaseHas('admin_audit_events', ['user_id' => $admin->id, 'event_type' => 'user_access_updated', 'subject_id' => (string) $student->id]);
    }

    public function test_admin_cannot_deactivate_self_or_remove_last_active_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->patch(route('admin.users.update', $admin), ['role' => 'student', 'is_active' => false])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'is_active' => true]);
    }

    public function test_inactive_user_is_blocked_from_authenticated_application_routes(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_student_cannot_open_user_or_ai_administration(): void
    {
        $student = User::factory()->create(['is_active' => true]);
        $this->actingAs($student)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.ai.index'))->assertForbidden();
    }

    public function test_ai_admin_props_never_contain_provider_api_keys(): void
    {
        config(['ai.providers.groq.api_key' => 'super-secret-value', 'ai.providers.groq.enabled' => true, 'ai.providers.groq.model' => 'configured-model']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $response = $this->actingAs($admin)->get(route('admin.ai.index'))->assertOk();

        $this->assertStringNotContainsString('super-secret-value', $response->getContent());
    }
}
