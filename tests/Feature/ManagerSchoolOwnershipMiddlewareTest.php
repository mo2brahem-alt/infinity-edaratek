<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\RoleRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerSchoolOwnershipMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_primary_manager_is_blocked_from_managed_routes_and_redirected_to_onboarding(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $primaryManager = User::factory()->create(['role' => 'school_manager']);
        $primaryManager->assignRole('school_manager');

        $ownedSchool = School::create([
            'directorate_id' => $region->id,
            'name' => 'Owned School',
            'school_id' => 'SCH-610001',
            'phone' => '0500006101',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $primaryManager->id,
        ]);

        $primaryManager->update(['school_id' => $ownedSchool->id]);

        $nonPrimaryManager = User::factory()->create(['role' => 'school_manager']);
        $nonPrimaryManager->assignRole('school_manager');
        $nonPrimaryManager->update(['school_id' => $ownedSchool->id]);

        $redirectService = app(RoleRedirectService::class);
        $this->assertSame(
            'manager.onboarding.show',
            $redirectService->redirectRouteFor($nonPrimaryManager->fresh())
        );

        $this->actingAs($nonPrimaryManager)
            ->get(route('manager.dashboard'))
            ->assertRedirect(route('manager.onboarding.show', absolute: false))
            ->assertSessionHas('warning', __('messages.assigned_manager_required'));

        $this->actingAs($nonPrimaryManager)
            ->get(route('manager.onboarding.show'))
            ->assertOk();
    }

    public function test_approved_manager_without_school_is_redirected_to_onboarding_instead_of_raw_403(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'school_id' => null,
        ]);
        $manager->assignRole('school_manager');

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertRedirect(route('manager.onboarding.show', absolute: false))
            ->assertSessionHas('warning', __('messages.manager_school_required'));
    }

    public function test_approved_manager_without_school_is_redirected_from_school_modules_to_onboarding(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'school_id' => null,
        ]);
        $manager->assignRole('school_manager');

        $this->actingAs($manager)
            ->get(route('school.student_structure.index'))
            ->assertRedirect(route('manager.onboarding.show', absolute: false))
            ->assertSessionHas('warning', __('messages.manager_school_required'));
    }

    public function test_missing_manager_school_json_request_returns_arabic_setup_message(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'school_id' => null,
        ]);
        $manager->assignRole('school_manager');

        $this->actingAs($manager)
            ->getJson(route('api.school.users.index'))
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.manager_school_required'));
    }

    public function test_onboarding_exposes_approved_but_school_missing_account_status(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'school_id' => null,
        ]);
        $manager->assignRole('school_manager');
        $plan = Plan::create([
            'name' => 'Manager Active Plan',
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'price' => 100,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);
        Subscription::create([
            'user_id' => $manager->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($manager)
            ->get(route('manager.onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Manager/Onboarding')
                ->where('accountStatus.key', 'approved_but_school_missing')
            );
    }

    public function test_non_primary_manager_can_recover_by_selecting_an_available_school_during_onboarding(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'West',
            'governorate' => 'Makkah',
        ]);

        $primaryManager = User::factory()->create(['role' => 'school_manager']);
        $primaryManager->assignRole('school_manager');

        $lockedSchool = School::create([
            'directorate_id' => $region->id,
            'name' => 'Locked School',
            'school_id' => 'SCH-620001',
            'phone' => '0500006201',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $primaryManager->id,
        ]);

        $primaryManager->update(['school_id' => $lockedSchool->id]);

        $availableSchool = School::create([
            'directorate_id' => $region->id,
            'name' => 'Available School',
            'school_id' => 'SCH-620002',
            'phone' => '0500006202',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $nonPrimaryManager = User::factory()->create(['role' => 'school_manager']);
        $nonPrimaryManager->assignRole('school_manager');
        $nonPrimaryManager->update(['school_id' => $lockedSchool->id]);

        $select = $this->actingAs($nonPrimaryManager)->post(route('manager.onboarding.select'), [
            'region_id' => $region->id,
            'school_id' => $availableSchool->id,
        ]);
        $select->assertOk();

        $nonPrimaryManager->refresh();
        $lockedSchool->refresh();
        $availableSchool->refresh();

        $this->assertSame($availableSchool->id, $nonPrimaryManager->school_id);
        $this->assertSame($primaryManager->id, $lockedSchool->manager_user_id);
        $this->assertSame($nonPrimaryManager->id, $availableSchool->manager_user_id);

        $redirectService = app(RoleRedirectService::class);
        $this->assertSame(
            'manager.dashboard',
            $redirectService->redirectRouteFor($nonPrimaryManager->fresh())
        );

        $this->actingAs($nonPrimaryManager)
            ->get(route('manager.dashboard'))
            ->assertOk();
    }
}

