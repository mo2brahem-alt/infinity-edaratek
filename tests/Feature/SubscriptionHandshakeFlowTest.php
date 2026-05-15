<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\EducationStage;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\UserApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionHandshakeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_to_end_subscription_onboarding_and_two_step_handshake(): void
    {
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $supervisorPlan = Plan::create([
            'name' => 'Supervisor Basic',
            'role_type' => Plan::ROLE_SUPERVISOR,
            'price' => 120,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);

        $managerPlan = Plan::create([
            'name' => 'Manager Basic',
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'price' => 80,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'North Region',
            'governorate' => 'Riyadh',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-310001',
            'phone' => '0500000101',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-310002',
            'phone' => '0500000102',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $plansResponse = $this->get(route('plans.index', ['role_type' => Plan::ROLE_SUPERVISOR]));
        $plansResponse->assertOk();
        $plansResponse->assertJsonFragment(['id' => $supervisorPlan->id]);

        $registerSupervisor = $this->post(route('register.supervisor.store'), [
            'plan_id' => $supervisorPlan->id,
            'name' => 'Supervisor One',
            'email' => 'supervisor.one@example.com',
            'mobile' => '0500000201',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $registerSupervisor->assertRedirect(route('welcome', ['registration' => 'pending-approval'], absolute: false));
        $this->assertGuest();

        $supervisor = User::query()->where('email', 'supervisor.one@example.com')->firstOrFail();
        $this->assertSame('supervisor', $supervisor->role);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $supervisor->id,
            'plan_id' => $supervisorPlan->id,
            'status' => Subscription::STATUS_PENDING,
        ]);
        $supervisor = $this->approveUser($supervisor);

        $selectSupervisorSchools = $this->actingAs($supervisor)->post(route('supervisor.onboarding.select'), [
            'region_id' => $region->id,
            'school_ids' => [$schoolA->id, $schoolB->id],
        ]);
        $selectSupervisorSchools->assertOk();
        $selectSupervisorSchools->assertJsonFragment(['created_count' => 2]);

        $requestA = SchoolSupervisionRequest::query()
            ->where('school_id', $schoolA->id)
            ->where('supervisor_id', $supervisor->id)
            ->firstOrFail();

        $this->assertSame(SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED, $requestA->status);

        $duplicateSelection = $this->actingAs($supervisor)->post(route('supervisor.onboarding.select'), [
            'region_id' => $region->id,
            'school_ids' => [$schoolA->id],
        ]);
        $duplicateSelection->assertStatus(422);
        $duplicateSelection->assertJsonPath('skipped_school_ids.0', $schoolA->id);

        $this->assertDatabaseCount('school_supervision_requests', 2);

        $this->actingAs($supervisor)->post(route('logout'));

        $registerManager = $this->post(route('register.manager.plan.store'), [
            'plan_id' => $managerPlan->id,
            'name' => 'Manager One',
            'email' => 'manager.one@example.com',
            'mobile' => '0500000301',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $registerManager->assertRedirect(route('welcome', ['registration' => 'pending-approval'], absolute: false));
        $this->assertGuest();

        $manager = User::query()->where('email', 'manager.one@example.com')->firstOrFail();
        $this->assertSame('school_manager', $manager->role);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $manager->id,
            'plan_id' => $managerPlan->id,
            'status' => Subscription::STATUS_PENDING,
        ]);
        $manager = $this->approveUser($manager);

        $managerOnboarding = $this->actingAs($manager)->post(route('manager.onboarding.select'), [
            'region_id' => $region->id,
            'school_id' => $schoolA->id,
        ]);
        $managerOnboarding->assertOk();
        $manager->refresh();

        $managerRequests = $this->actingAs($manager)->get(route('manager.requests.index'));
        $managerRequests->assertOk();
        $managerRequests->assertJsonFragment(['id' => $requestA->id]);

        $managerApprove = $this->actingAs($manager)->post(route('manager.requests.approve', $requestA));
        $managerApprove->assertOk();

        $requestA->refresh();
        $this->assertSame(SchoolSupervisionRequest::STATUS_MANAGER_APPROVED, $requestA->status);

        $supervisorConfirm = $this->actingAs($supervisor)->post(route('supervisor.requests.confirm', $requestA));
        $supervisorConfirm->assertOk();

        $requestA->refresh();
        $schoolA->refresh();

        $this->assertSame(SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION, $requestA->status);
        $this->assertSame($supervisor->id, $schoolA->supervisor_id);
        $this->assertSame($manager->id, $schoolA->manager_user_id);
        $this->assertSame(School::STATUS_ACTIVE, $schoolA->status);
    }

    public function test_supervisor_selection_handles_legacy_stale_manager_reference_without_fk_failure(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This regression scenario uses MySQL foreign key checks.');
        }

        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Legacy Region',
            'governorate' => 'Riyadh',
        ]);

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Legacy Manager Reference School',
            'school_id' => 'SCH-LEGACY-001',
            'phone' => '0500000991',
            'status' => School::STATUS_SUSPENDED,
            'manager_user_id' => null,
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('schools')
                ->where('id', $school->id)
                ->update(['manager_user_id' => 999999]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $selectSupervisorSchools = $this->actingAs($supervisor)->post(route('supervisor.onboarding.select'), [
            'region_id' => $region->id,
            'school_ids' => [$school->id],
        ]);

        $selectSupervisorSchools->assertOk();
        $selectSupervisorSchools->assertJsonFragment(['created_count' => 1]);

        $createdRequest = SchoolSupervisionRequest::query()
            ->where('school_id', $school->id)
            ->where('supervisor_id', $supervisor->id)
            ->firstOrFail();

        $this->assertNull($createdRequest->manager_id);

        $school->refresh();
        $this->assertNull($school->manager_user_id);
        $this->assertSame(School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL, (string) $school->supervision_status);
    }

    public function test_manager_cannot_select_school_linked_to_another_manager(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $plan = Plan::create([
            'name' => 'Manager Basic',
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'price' => 80,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $existingManager = User::factory()->create(['role' => 'school_manager']);
        $existingManager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Locked School',
            'school_id' => 'SCH-320001',
            'phone' => '0500000401',
            'status' => School::STATUS_SUSPENDED,
            'manager_user_id' => $existingManager->id,
        ]);

        $registerManager = $this->post(route('register.manager.plan.store'), [
            'plan_id' => $plan->id,
            'name' => 'Manager Two',
            'email' => 'manager.two@example.com',
            'mobile' => '0500000402',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $registerManager->assertRedirect(route('welcome', ['registration' => 'pending-approval'], absolute: false));
        $this->assertGuest();

        $manager = User::query()->where('email', 'manager.two@example.com')->firstOrFail();
        $manager = $this->approveUser($manager);

        $selectResponse = $this->actingAs($manager)->postJson(route('manager.onboarding.select'), [
            'region_id' => $region->id,
            'school_id' => $school->id,
        ]);

        $selectResponse->assertStatus(422);
    }

    public function test_manager_can_create_school_during_onboarding_and_complete_same_supervision_workflow(): void
    {
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $managerPlan = Plan::create([
            'name' => 'Manager Create School Plan',
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'price' => 99,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'New Registration Region',
            'governorate' => 'Riyadh',
        ]);

        $registerManager = $this->post(route('register.manager.plan.store'), [
            'plan_id' => $managerPlan->id,
            'name' => 'Manager Create School',
            'email' => 'manager.create-school@example.com',
            'mobile' => '0500000501',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $registerManager->assertRedirect(route('welcome', ['registration' => 'pending-approval'], absolute: false));
        $this->assertGuest();

        $manager = User::query()->where('email', 'manager.create-school@example.com')->firstOrFail();
        $manager = $this->approveUser($manager);
        $educationStage = EducationStage::query()->create([
            'name' => 'ابتدائي',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $createSchool = $this->actingAs($manager)->post(route('manager.onboarding.schools.store'), [
            'region_id' => $region->id,
            'school_type' => School::TYPE_MIXED,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'Manager Created School',
            'phone' => '0500000502',
            'address' => 'Riyadh',
        ]);

        $createSchool->assertStatus(201);

        $createdSchoolId = (int) $createSchool->json('school.id');
        $this->assertGreaterThan(0, $createdSchoolId);

        $manager->refresh();
        $this->assertSame($createdSchoolId, (int) $manager->school_id);

        $createdSchool = School::query()->findOrFail($createdSchoolId);
        $this->assertSame($manager->id, (int) $createdSchool->manager_user_id);
        $this->assertSame(School::STATUS_SUSPENDED, $createdSchool->status);
        $this->assertSame(School::SUPERVISION_STATUS_SUSPENDED, $createdSchool->supervision_status);

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');

        $availableSchools = $this->actingAs($supervisor)
            ->get(route('supervisor.onboarding.schools', $region->id));

        $availableSchools->assertOk();
        $availableSchools->assertJsonFragment([
            'id' => $createdSchoolId,
            'name' => 'Manager Created School',
        ]);

        $selectBySupervisor = $this->actingAs($supervisor)->post(route('supervisor.onboarding.select'), [
            'region_id' => $region->id,
            'school_ids' => [$createdSchoolId],
        ]);

        $selectBySupervisor->assertOk();
        $selectBySupervisor->assertJsonFragment(['created_count' => 1]);

        $supervisionRequest = SchoolSupervisionRequest::query()
            ->where('school_id', $createdSchoolId)
            ->where('supervisor_id', $supervisor->id)
            ->firstOrFail();

        $this->assertSame(SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED, $supervisionRequest->status);

        $approveByManager = $this->actingAs($manager)->post(route('manager.requests.approve', $supervisionRequest));
        $approveByManager->assertOk();

        $supervisionRequest->refresh();
        $this->assertSame(SchoolSupervisionRequest::STATUS_MANAGER_APPROVED, $supervisionRequest->status);

        $confirmBySupervisor = $this->actingAs($supervisor)->post(route('supervisor.requests.confirm', $supervisionRequest));
        $confirmBySupervisor->assertOk();

        $supervisionRequest->refresh();
        $createdSchool->refresh();

        $this->assertSame(SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION, $supervisionRequest->status);
        $this->assertSame(School::STATUS_ACTIVE, $createdSchool->status);
        $this->assertSame(School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION, $createdSchool->supervision_status);
        $this->assertSame($supervisor->id, (int) $createdSchool->supervisor_id);
    }

    public function test_manager_cannot_create_new_school_if_he_already_owns_another_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Ownership Region',
            'governorate' => 'Jeddah',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $ownedSchool = School::create([
            'directorate_id' => $region->id,
            'name' => 'Already Owned School',
            'school_id' => 'SCH-330001',
            'phone' => '0500003301',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $ownedSchool->id]);
        $educationStage = EducationStage::query()->create([
            'name' => 'متوسط',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $createSchool = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'region_id' => $region->id,
            'school_type' => School::TYPE_BOYS,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'Another School',
            'phone' => '0500003302',
        ]);

        $createSchool->assertStatus(422);

        $this->assertDatabaseMissing('schools', [
            'name' => 'Another School',
            'phone' => '0500003302',
        ]);
    }

    private function approveUser(User $user): User
    {
        Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
        ]);
        $admin->assignRole('super_admin');

        return app(UserApprovalService::class)->approve($user, $admin);
    }
}
