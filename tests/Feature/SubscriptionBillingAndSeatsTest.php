<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionUserAddon;
use App\Models\User;
use App\Services\Subscription\SubscriptionPricingService;
use App\Services\Subscription\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionBillingAndSeatsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_super_admin_can_create_plan_with_monthly_yearly_and_extra_seat_pricing(): void
    {
        $admin = $this->makeUserWithRole('super_admin');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'School Pro',
                'role_type' => Plan::ROLE_SCHOOL_MANAGER,
                'price' => 1000,
                'monthly_price' => 1000,
                'yearly_price' => 11000,
                'included_users_count' => 10,
                'extra_user_monthly_price' => 60,
                'billing_cycle' => Plan::BILLING_MONTHLY,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'name' => 'School Pro',
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'monthly_price' => 1000,
            'yearly_price' => 11000,
            'included_users_count' => 10,
            'extra_user_monthly_price' => 60,
        ]);
    }

    public function test_subscription_service_stores_monthly_and_yearly_snapshots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 09:00:00'));

        $plan = $this->makeManagerPlan();
        $manager = $this->makeUserWithRole('school_manager');
        $service = app(SubscriptionService::class);

        $monthly = $service->createForUser($manager, $plan, true, null, Plan::BILLING_MONTHLY);
        $yearly = $service->createForUser($manager, $plan, true, null, Plan::BILLING_YEARLY);

        $this->assertSame(Plan::BILLING_MONTHLY, $monthly->billing_cycle);
        $this->assertSame('1000.00', $monthly->base_price);
        $this->assertSame(10, (int) $monthly->included_users_count);
        $this->assertSame('60.00', $monthly->extra_user_monthly_price);
        $this->assertSame('2026-05-01', $monthly->ends_at->toDateString());

        $this->assertSame(Plan::BILLING_YEARLY, $yearly->billing_cycle);
        $this->assertSame('11000.00', $yearly->base_price);
        $this->assertSame(10, (int) $yearly->included_users_count);
        $this->assertSame('60.00', $yearly->extra_user_monthly_price);
        $this->assertSame('2027-04-01', $yearly->ends_at->toDateString());
    }

    public function test_initial_manager_registration_estimate_counts_extra_users_for_monthly_and_yearly_cycles(): void
    {
        $plan = $this->makeManagerPlan();
        $service = app(SubscriptionPricingService::class);

        $monthly = $service->initialSubscriptionEstimate($plan, Plan::BILLING_MONTHLY, 13);
        $yearly = $service->initialSubscriptionEstimate($plan, Plan::BILLING_YEARLY, 13);

        $this->assertSame(10, $monthly['included_users_count']);
        $this->assertSame(13, $monthly['requested_users_count']);
        $this->assertSame(3, $monthly['extra_users_count']);
        $this->assertSame('180.00', $monthly['extra_users_amount']);
        $this->assertSame('1180.00', $monthly['total_price']);

        $this->assertSame(12, $yearly['billing_months']);
        $this->assertSame(3, $yearly['extra_users_count']);
        $this->assertSame('2160.00', $yearly['extra_users_amount']);
        $this->assertSame('13160.00', $yearly['total_price']);
    }

    public function test_manager_registration_stores_server_calculated_extra_user_total_snapshot(): void
    {
        $plan = $this->makeManagerPlan();

        $this->post(route('register.manager.plan.store'), [
            'plan_id' => $plan->id,
            'billing_cycle' => Plan::BILLING_YEARLY,
            'extra_users_count' => 3,
            'name' => 'Manager Extra Users',
            'email' => 'manager.extra-users@example.com',
            'mobile' => '0507654321',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('welcome', ['registration' => 'pending-approval'], absolute: false));

        $manager = User::query()->where('email', 'manager.extra-users@example.com')->firstOrFail();
        $subscription = Subscription::query()
            ->where('user_id', $manager->id)
            ->where('plan_id', $plan->id)
            ->firstOrFail();

        $this->assertSame(Plan::BILLING_YEARLY, $subscription->billing_cycle);
        $this->assertSame('11000.00', $subscription->base_price);
        $this->assertSame(13, (int) $subscription->meta['requested_users_count']);
        $this->assertSame(3, (int) $subscription->meta['initial_extra_users_count']);
        $this->assertSame('60.00', $subscription->meta['initial_extra_user_monthly_price']);
        $this->assertSame('2160.00', $subscription->meta['initial_extra_users_amount']);
        $this->assertSame('13160.00', $subscription->meta['initial_subscription_total_price']);
    }

    public function test_extra_seat_cost_uses_fixed_30_day_accounting_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 09:00:00'));

        $subscription = Subscription::query()->create([
            'user_id' => $this->makeUserWithRole('school_manager')->id,
            'plan_id' => $this->makeManagerPlan()->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Plan::BILLING_YEARLY,
            'base_price' => 11000,
            'included_users_count' => 10,
            'extra_user_monthly_price' => 60,
            'starts_at' => now(),
            'ends_at' => now()->addDays(130),
        ]);

        $service = app(SubscriptionPricingService::class);

        $oneSeat = $service->calculateExtraSeats($subscription, 1);
        $threeSeats = $service->calculateExtraSeats($subscription, 3);

        $this->assertSame(130, $oneSeat['remaining_days']);
        $this->assertSame('2.00', $oneSeat['daily_price']);
        $this->assertSame('260.00', $oneSeat['amount']);
        $this->assertSame('780.00', $threeSeats['amount']);
    }

    public function test_expired_subscription_cannot_add_extra_seats(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 09:00:00'));

        $subscription = Subscription::query()->create([
            'user_id' => $this->makeUserWithRole('school_manager')->id,
            'plan_id' => $this->makeManagerPlan()->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'base_price' => 1000,
            'included_users_count' => 10,
            'extra_user_monthly_price' => 60,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $this->expectException(ValidationException::class);

        app(SubscriptionPricingService::class)->calculateExtraSeats($subscription, 1);
    }

    public function test_adding_user_inside_included_limit_does_not_create_extra_addon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 09:00:00'));

        [$school, $manager] = $this->makeManagedSchool('SCH-SEAT-000');
        [$department, $departmentRole] = $this->makeDepartmentAndRole();
        Role::findOrCreate('staff', 'web');

        $plan = $this->makeManagerPlan(includedUsersCount: 10);
        $this->makeActiveSubscription($manager, $plan, $school->id);

        for ($i = 1; $i <= 9; $i++) {
            $this->makeStaff($school->id, $department->id, $departmentRole->id, "inside-limit-{$i}@example.com");
        }

        $addon = app(SubscriptionPricingService::class)->reserveSeatsForSchoolStaff($manager, $school->id);

        $this->assertNull($addon);
        $this->assertDatabaseCount('subscription_user_addons', 0);
    }

    public function test_extra_seat_calculation_is_scoped_to_current_school_and_ignores_tampered_ids(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 09:00:00'));

        [$schoolA, $managerA] = $this->makeManagedSchool('SCH-SEAT-001');
        [$schoolB, $managerB] = $this->makeManagedSchool('SCH-SEAT-002');
        [$department, $departmentRole] = $this->makeDepartmentAndRole();
        Role::findOrCreate('staff', 'web');

        $plan = $this->makeManagerPlan(includedUsersCount: 1);
        $subscriptionA = $this->makeActiveSubscription($managerA, $plan, $schoolA->id);
        $subscriptionB = $this->makeActiveSubscription($managerB, $plan, $schoolB->id);

        $this->makeStaff($schoolA->id, $department->id, $departmentRole->id, 'seat-a-existing@example.com');
        $this->makeStaff($schoolB->id, $department->id, $departmentRole->id, 'seat-b-existing@example.com');

        $this->actingAs($managerA)
            ->postJson(route('api.school.users.store'), [
                'name' => 'New Staff',
                'email' => 'new.staff@example.com',
                'mobile' => '0501234567',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'department_id' => $department->id,
                'department_role_id' => $departmentRole->id,
                'role_names' => ['staff'],
                'school_id' => $schoolB->id,
                'subscription_id' => $subscriptionB->id,
            ])
            ->assertCreated()
            ->assertJsonPath('seat_addon.added_seats_count', 1)
            ->assertJsonPath('seat_addon.daily_price', '2.00')
            ->assertJsonPath('seat_addon.amount', '260.00');

        $this->assertDatabaseHas('users', [
            'email' => 'new.staff@example.com',
            'school_id' => $schoolA->id,
        ]);

        $this->assertDatabaseHas('subscription_user_addons', [
            'subscription_id' => $subscriptionA->id,
            'school_id' => $schoolA->id,
            'added_seats_count' => 1,
            'remaining_days' => 130,
            'amount' => 260,
            'status' => SubscriptionUserAddon::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseMissing('subscription_user_addons', [
            'subscription_id' => $subscriptionB->id,
            'school_id' => $schoolB->id,
        ]);

        $addon = SubscriptionUserAddon::query()
            ->where('subscription_id', $subscriptionA->id)
            ->where('school_id', $schoolA->id)
            ->firstOrFail();

        $this->assertSame($subscriptionA->ends_at->toDateTimeString(), $addon->ends_at->toDateTimeString());
    }

    private function makeManagerPlan(int $includedUsersCount = 10): Plan
    {
        return Plan::query()->create([
            'name' => 'Manager Pro',
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'price' => 1000,
            'monthly_price' => 1000,
            'yearly_price' => 11000,
            'included_users_count' => $includedUsersCount,
            'extra_user_monthly_price' => 60,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);
    }

    private function makeActiveSubscription(User $manager, Plan $plan, int $schoolId): Subscription
    {
        return Subscription::query()->create([
            'user_id' => $manager->id,
            'plan_id' => $plan->id,
            'school_id' => $schoolId,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Plan::BILLING_YEARLY,
            'base_price' => 11000,
            'included_users_count' => (int) $plan->included_users_count,
            'extra_user_monthly_price' => 60,
            'starts_at' => now(),
            'ends_at' => now()->addDays(130),
        ]);
    }

    /**
     * @return array{0: School, 1: User}
     */
    private function makeManagedSchool(string $schoolCode): array
    {
        $manager = $this->makeUserWithRole('school_manager');
        $directorate = EducationalDirectorate::query()->create([
            'name' => 'Directorate '.$schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $school = School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'School '.$schoolCode,
            'school_id' => $schoolCode,
            'phone' => '050'.substr(preg_replace('/\D/', '', $schoolCode), -7),
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [$school, $manager->fresh()];
    }

    /**
     * @return array{0: Department, 1: DepartmentRole}
     */
    private function makeDepartmentAndRole(): array
    {
        $department = Department::query()->create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $role = DepartmentRole::query()->create([
            'department_id' => $department->id,
            'name' => 'Administrative Staff',
            'is_active' => true,
        ]);

        return [$department, $role];
    }

    private function makeStaff(int $schoolId, int $departmentId, int $departmentRoleId, string $email): User
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email' => $email,
            'school_id' => $schoolId,
            'department_id' => $departmentId,
            'department_role_id' => $departmentRoleId,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        return $staff;
    }

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::findOrCreate($roleName, 'web');

        $user = User::factory()->create([
            'role' => $roleName,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
