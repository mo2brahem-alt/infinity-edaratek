<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_api_uses_configured_rate_limit(): void
    {
        config()->set('features.api.system_rate_limit_per_minute', 2);

        Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $endpoint = route('api.system.roles.index');

        $this->actingAs($superAdmin)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->getJson($endpoint)
            ->assertOk();

        $this->actingAs($superAdmin)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->getJson($endpoint)
            ->assertOk();

        $this->actingAs($superAdmin)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->getJson($endpoint)
            ->assertStatus(429);
    }

    public function test_school_api_uses_configured_rate_limit(): void
    {
        config()->set('features.api.school_rate_limit_per_minute', 2);

        Role::query()->firstOrCreate([
            'name' => 'school_manager',
            'guard_name' => 'web',
        ]);

        $manager = $this->createSchoolManagerWithSchool('SCH-990001');
        $endpoint = route('api.school.users.index');

        $this->actingAs($manager)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.11'])
            ->getJson($endpoint)
            ->assertOk();

        $this->actingAs($manager)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.11'])
            ->getJson($endpoint)
            ->assertOk();

        $this->actingAs($manager)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.11'])
            ->getJson($endpoint)
            ->assertStatus(429);
    }

    private function createSchoolManagerWithSchool(string $schoolCode): User
    {
        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $schoolPhone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => $schoolPhone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return $manager->fresh();
    }
}

