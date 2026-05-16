<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_manager_sees_only_his_school_analytics(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Analytics Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');
        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = $this->createManagedSchool($region->id, $managerA, 'Analytics School A', 'SCH-DASH-A');
        $schoolB = $this->createManagedSchool($region->id, $managerB, 'Analytics School B', 'SCH-DASH-B');

        $classroomA = $this->createClassroom($schoolA, 'الأول', 'أ');
        $classroomB = $this->createClassroom($schoolB, 'الأول', 'ب');

        SchoolStudent::create([
            'school_id' => $schoolA->id,
            'school_classroom_id' => $classroomA->id,
            'full_name' => 'طالب من مدرسة المدير',
            'student_code' => 'DASH-A-1',
            'is_active' => true,
        ]);
        SchoolStudent::create([
            'school_id' => $schoolB->id,
            'school_classroom_id' => $classroomB->id,
            'full_name' => 'طالب من مدرسة أخرى',
            'student_code' => 'DASH-B-1',
            'is_active' => true,
        ]);

        $teacherA = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacherA->assignRole('staff');

        $teacherB = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolB->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacherB->assignRole('staff');

        $this->actingAs($managerA)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Manager/Dashboard')
                ->where('analytics.school.id', $schoolA->id)
                ->where('analytics.students.summary.total', 1)
                ->where('analytics.teachers.summary.total', 1)
                ->has('analytics.kpis')
                ->has('analytics.alerts')
            );
    }

    public function test_dashboard_filters_ignore_classroom_from_another_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Analytics Filter Region',
            'governorate' => 'Makkah',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');
        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = $this->createManagedSchool($region->id, $managerA, 'Filter School A', 'SCH-DASH-F-A');
        $schoolB = $this->createManagedSchool($region->id, $managerB, 'Filter School B', 'SCH-DASH-F-B');

        $classroomA = $this->createClassroom($schoolA, 'الأول', 'أ');
        $classroomB = $this->createClassroom($schoolB, 'الثاني', 'ب');

        SchoolStudent::create([
            'school_id' => $schoolA->id,
            'school_classroom_id' => $classroomA->id,
            'full_name' => 'طالب آمن',
            'student_code' => 'SAFE-DASH-1',
            'is_active' => true,
        ]);
        SchoolStudent::create([
            'school_id' => $schoolB->id,
            'school_classroom_id' => $classroomB->id,
            'full_name' => 'طالب خارج النطاق',
            'student_code' => 'UNSAFE-DASH-1',
            'is_active' => true,
        ]);

        $this->actingAs($managerA)
            ->get(route('manager.dashboard', ['classroom_id' => $classroomB->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Manager/Dashboard')
                ->where('analytics.filters.classroom_id', null)
                ->where('analytics.students.summary.total', 1)
                ->where('analytics.school.id', $schoolA->id)
            );
    }

    private function createManagedSchool(int $regionId, User $manager, string $name, string $schoolCode): School
    {
        $school = School::create([
            'directorate_id' => $regionId,
            'name' => $name,
            'school_id' => $schoolCode,
            'phone' => '0500000000',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return $school;
    }

    private function createClassroom(School $school, string $gradeName, string $name): SchoolClassroom
    {
        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'المرحلة الابتدائية ' . $school->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => $gradeName,
            'name' => $name,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
