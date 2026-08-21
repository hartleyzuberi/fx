<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_cannot_open_content_administration(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.content-audit.index'))->assertForbidden();
    }

    public function test_administrators_can_open_content_administration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.content-audit.index'))->assertOk();
    }
}
