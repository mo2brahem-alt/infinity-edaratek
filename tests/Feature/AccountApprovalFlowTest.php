<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EducationalDirectorate;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\UserApprovalService;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_registration_creates_pending_account_redirects_home_and_sends_notifications(): void
    {
        $this->createRole('super_admin');
        $this->createRole('supervisor');

        $admin = $this->createSuperAdmin();
        $plan = $this->makePlan(Plan::ROLE_SUPERVISOR, 'Supervisor Plan');

        $response = $this->post(route('register.supervisor.store'), [
            'plan_id' => $plan->id,
            'name' => 'Supervisor Pending',
            'email' => 'pending.supervisor@example.com',
            'mobile' => '0500001111',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('welcome', ['registration' => 'pending-approval'], absolute: false));
        $response->assertSessionHas('success');
        $this->assertGuest();

        $user = User::query()->where('email', 'pending.supervisor@example.com')->firstOrFail();

        $this->assertSame('supervisor', $user->role);
        $this->assertFalse((bool) $user->is_active);
        $this->assertSame(User::APPROVAL_PENDING, $user->approval_status);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'user.approval.pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'user.approval.pending',
        ]);
    }

    public function test_manager_registration_creates_pending_account_redirects_home_and_sends_notifications(): void
    {
        $this->createRole('super_admin');
        $this->createRole('school_manager');

        $admin = $this->createSuperAdmin();
        $plan = $this->makePlan(Plan::ROLE_SCHOOL_MANAGER, 'Manager Plan');

        $response = $this->post(route('register.manager.plan.store'), [
            'plan_id' => $plan->id,
            'name' => 'Manager Pending',
            'email' => 'pending.manager@example.com',
            'mobile' => '0500001112',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('welcome', ['registration' => 'pending-approval'], absolute: false));
        $response->assertSessionHas('success');
        $this->assertGuest();

        $user = User::query()->where('email', 'pending.manager@example.com')->firstOrFail();

        $this->assertSame('school_manager', $user->role);
        $this->assertFalse((bool) $user->is_active);
        $this->assertSame(User::APPROVAL_PENDING, $user->approval_status);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'user.approval.pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'user.approval.pending',
        ]);
    }

    public function test_pending_account_cannot_login_or_enter_dashboard_before_approval(): void
    {
        $this->createRole('supervisor');

        $user = User::factory()->create([
            'role' => 'supervisor',
            'email' => 'blocked@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => false,
            'approval_status' => User::APPROVAL_PENDING,
            'approved_at' => null,
        ]);
        $user->assignRole('supervisor');

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $dashboardResponse = $this->actingAs($user)->get(route('dashboard'));

        $dashboardResponse->assertRedirect(route('login', absolute: false));
        $dashboardResponse->assertSessionHas('warning');
        $this->assertGuest();
    }

    public function test_super_admin_can_approve_pending_account_and_activate_subscription(): void
    {
        $this->createRole('super_admin');
        $this->createRole('supervisor');

        $admin = $this->createSuperAdmin();
        $user = $this->makePendingUser('supervisor', 'approve.me@example.com');
        $plan = $this->makePlan(Plan::ROLE_SUPERVISOR, 'Supervisor Approval Plan');
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
            'starts_at' => now(),
            'meta' => ['source' => 'test'],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('users.index'))
            ->post(route('users.approve', $user), [
                'reason' => 'مكتمل',
            ]);

        $response->assertRedirect(route('users.index', absolute: false));

        $user->refresh();

        $this->assertTrue((bool) $user->is_active);
        $this->assertSame(User::APPROVAL_APPROVED, $user->approval_status);
        $this->assertNotNull($user->approved_at);
        $this->assertSame($admin->id, $user->approved_by);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'user.approval.approved',
        ]);
    }

    public function test_super_admin_approval_activates_linked_school_manager_school(): void
    {
        $this->createRole('super_admin');
        $this->createRole('school_manager');

        $admin = $this->createSuperAdmin();
        $user = $this->makePendingUser('school_manager', 'approve.manager.school@example.com');
        $region = EducationalDirectorate::query()->create([
            'name' => 'Approval Region',
            'governorate' => 'Riyadh',
        ]);
        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'Pending Manager School',
            'school_id' => 'SCH-APP-0001',
            'phone' => '0500002001',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
        ]);
        $user->update(['school_id' => $school->id]);

        $response = $this->actingAs($admin)
            ->from(route('users.index'))
            ->post(route('users.approve', $user), [
                'reason' => 'مراجعة مكتملة',
            ]);

        $response->assertRedirect(route('users.index', absolute: false));

        $user->refresh();
        $school->refresh();

        $this->assertTrue((bool) $user->is_active);
        $this->assertSame(User::APPROVAL_APPROVED, $user->approval_status);
        $this->assertSame(School::STATUS_ACTIVE, $school->status);
        $this->assertSame($user->id, (int) $school->manager_user_id);
        $this->assertSame(School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM, $school->supervision_status);
    }

    public function test_super_admin_can_reject_pending_account_and_keep_it_blocked(): void
    {
        $this->createRole('super_admin');
        $this->createRole('school_manager');

        $admin = $this->createSuperAdmin();
        $user = $this->makePendingUser('school_manager', 'reject.me@example.com');
        $plan = $this->makePlan(Plan::ROLE_SCHOOL_MANAGER, 'Manager Rejection Plan');
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
            'starts_at' => now(),
            'meta' => ['source' => 'test'],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('users.index'))
            ->post(route('users.reject', $user), [
                'reason' => 'مرفوض',
            ]);

        $response->assertRedirect(route('users.index', absolute: false));

        $user->refresh();

        $this->assertFalse((bool) $user->is_active);
        $this->assertSame(User::APPROVAL_REJECTED, $user->approval_status);
        $this->assertNotNull($user->rejected_at);
        $this->assertSame($admin->id, $user->rejected_by);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => Subscription::STATUS_CANCELED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'user.approval.rejected',
        ]);
    }

    public function test_approved_account_can_login_after_super_admin_approval_and_resume_its_role_flow(): void
    {
        $this->createRole('super_admin');
        $this->createRole('supervisor');

        $admin = $this->createSuperAdmin();
        $user = $this->makePendingUser('supervisor', 'approved.login@example.com');
        $user->forceFill([
            'password' => Hash::make('Password123!'),
        ])->save();

        app(UserApprovalService::class)->approve($user, $admin);

        $response = $this->post('/login', [
            'email' => 'approved.login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('supervisor.onboarding.show', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_non_super_admin_cannot_approve_or_reject_pending_accounts(): void
    {
        $this->createRole('supervisor');
        $this->createRole('staff');

        $actor = User::factory()->create([
            'role' => 'staff',
        ]);
        $actor->assignRole('staff');

        $pendingUser = $this->makePendingUser('supervisor', 'pending.authorization@example.com');

        $this->actingAs($actor)
            ->post(route('users.approve', $pendingUser))
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('users.reject', $pendingUser))
            ->assertForbidden();
    }

    public function test_super_admin_users_index_exposes_pending_approvals(): void
    {
        $this->createRole('super_admin');
        $this->createRole('supervisor');

        $admin = $this->createSuperAdmin();
        $pendingUser = $this->makePendingUser('supervisor', 'pending.list@example.com');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->has('pendingApprovals', 1)
                ->where('pendingApprovals.0.email', $pendingUser->email)
                ->where('approvalStats.pending', 1)
            );
    }

    public function test_super_admin_users_index_exposes_school_account_overview(): void
    {
        $this->createRole('super_admin');
        $this->createRole('school_manager');
        $this->createRole('supervisor');

        $admin = $this->createSuperAdmin();
        $manager = User::factory()->create([
            'name' => 'مدير المدرسة التجريبية',
            'email' => 'manager.accounts@example.com',
            'role' => 'school_manager',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
        ]);
        $manager->assignRole('school_manager');
        $supervisor = User::factory()->create([
            'name' => 'المشرف التجريبي',
            'email' => 'supervisor.accounts@example.com',
            'role' => 'supervisor',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
        ]);
        $supervisor->assignRole('supervisor');
        $region = EducationalDirectorate::query()->create([
            'name' => 'نطاق الحسابات',
            'governorate' => 'الرياض',
        ]);
        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة الحسابات',
            'school_id' => 'SCH-ACC-0001',
            'school_type' => School::TYPE_MIXED,
            'phone' => '0500005001',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
            'manager_user_id' => $manager->id,
            'supervisor_id' => $supervisor->id,
        ]);
        $manager->update(['school_id' => $school->id]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->has('schools', 1)
                ->where('schools.0.name', 'مدرسة الحسابات')
                ->where('schools.0.status', School::STATUS_ACTIVE)
                ->where('schools.0.manager.name', 'مدير المدرسة التجريبية')
                ->where('schools.0.supervisor.name', 'المشرف التجريبي')
                ->where('schools.0.users_count', 1)
            );
    }

    public function test_welcome_page_exposes_pending_registration_notice_when_query_flag_is_present(): void
    {
        $this->get(route('welcome', ['registration' => 'pending-approval']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->where('registrationNotice', 'تم إرسال طلب الانضمام للمسؤول، وسيتم تفعيل الحساب بعد المراجعة.')
            );
    }

    public function test_super_admin_seeder_creates_sultan_account_once_with_hashed_password(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'sultan@edaratek.com')->firstOrFail();

        $this->assertSame('super_admin', $user->role);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue((bool) $user->is_active);
        $this->assertSame(User::APPROVAL_APPROVED, $user->approval_status);
        $this->assertTrue(Hash::check('AdminP@ss2000', $user->password));
        $this->assertNotSame('AdminP@ss2000', $user->password);
        $this->assertSame(1, User::query()->where('email', 'sultan@edaratek.com')->count());
    }

    public function test_admin_created_manager_is_immediately_approved_without_pending_queue(): void
    {
        $this->createRole('super_admin');
        $this->createRole('school_manager');

        $admin = $this->createSuperAdmin();
        $department = Department::query()->create([
            'name' => 'الإدارة العامة',
        ]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Manager From Admin',
            'email' => 'manager.from.admin@example.com',
            'mobile' => '0500001113',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_name' => 'school_manager',
            'department_id' => $department->id,
        ]);

        $response->assertRedirect();

        $user = User::query()->where('email', 'manager.from.admin@example.com')->firstOrFail();

        $this->assertTrue((bool) $user->is_active);
        $this->assertSame(User::APPROVAL_APPROVED, $user->approval_status);
        $this->assertSame($admin->id, $user->approved_by);
    }

    private function createRole(string $name): Role
    {
        return Role::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }

    private function createSuperAdmin(): User
    {
        $this->createRole('super_admin');

        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function makePlan(string $roleType, string $name): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'role_type' => $roleType,
            'price' => 100,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);
    }

    private function makePendingUser(string $role, string $email): User
    {
        $this->createRole($role);

        $user = User::factory()->create([
            'name' => ucfirst(str_replace('_', ' ', $role)).' Pending',
            'email' => $email,
            'role' => $role,
            'is_active' => false,
            'approval_status' => User::APPROVAL_PENDING,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
