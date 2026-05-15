<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssociationHandshakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_manager_registration_page_redirects_to_plan_based_registration(): void
    {
        $response = $this->get(route('register.manager'));

        $response->assertRedirect(route('register.manager.plan', absolute: false));
    }

    public function test_legacy_manager_registration_submission_redirects_without_creating_manager_account(): void
    {
        $response = $this->post(route('register.manager.store'), [
            'name' => 'مدير قديم',
            'email' => 'legacy-manager@example.com',
            'mobile' => '0500000001',
            'school_id' => 1,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register.manager.plan', absolute: false));

        $this->assertDatabaseMissing('users', [
            'email' => 'legacy-manager@example.com',
        ]);
    }
}
