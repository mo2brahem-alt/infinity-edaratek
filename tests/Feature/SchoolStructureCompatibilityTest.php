<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolStructureCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_structure_page_works_when_user_permission_columns_are_missing(): void
    {
        $this->seedManagerRole();

        $manager = $this->createSchoolManagerWithSchool('SCH-992001');
        $this->createGlobalDepartmentAndRole();
        $this->dropUserStructurePermissionColumns();

        $this->actingAs($manager)
            ->get(route('manager.structure.index'))
            ->assertOk();
    }

    public function test_school_users_api_index_works_when_user_permission_columns_are_missing(): void
    {
        $this->seedManagerRole();

        $manager = $this->createSchoolManagerWithSchool('SCH-992002');
        $this->createGlobalDepartmentAndRole();
        $this->dropUserStructurePermissionColumns();

        $this->actingAs($manager)
            ->getJson(route('api.school.users.index'))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    private function seedManagerRole(): void
    {
        Role::query()->firstOrCreate([
            'name' => 'school_manager',
            'guard_name' => 'web',
        ]);
    }

    private function dropUserStructurePermissionColumns(): void
    {
        $columns = [
            'can_manage_student_structure',
            'can_manage_student_attendance',
            'can_manage_academic_planning',
            'can_manage_student_leaves',
            'can_manage_leave_types',
            'can_manage_school_calendar',
            'can_manage_school_holidays',
        ];

        $existing = collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn('users', $column))
            ->values()
            ->all();

        if (count($existing) === 0) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    /**
     * @return array{0: Department, 1: DepartmentRole}
     */
    private function createGlobalDepartmentAndRole(): array
    {
        $department = Department::query()->create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::query()->create([
            'department_id' => $department->id,
            'name' => 'Registrar',
            'is_active' => true,
        ]);

        return [$department, $departmentRole];
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

