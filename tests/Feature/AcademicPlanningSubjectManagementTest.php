<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\School;
use App\Models\SchoolSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPlanningSubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_code_is_auto_generated_and_scoped_per_school(): void
    {
        [$managerA, $schoolA] = $this->createManagerWithSchool('SCH-971001');
        [$managerB, $schoolB] = $this->createManagerWithSchool('SCH-971002');

        $this->from(route('school.academic_planning.index'))
            ->actingAs($managerA)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Mathematics',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->from(route('school.academic_planning.index'))
            ->actingAs($managerA)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Science',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->from(route('school.academic_planning.index'))
            ->actingAs($managerB)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'History',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseHas('school_subjects', [
            'school_id' => $schoolA->id,
            'name' => 'Mathematics',
            'code' => 'SUB-0001',
        ]);

        $this->assertDatabaseHas('school_subjects', [
            'school_id' => $schoolA->id,
            'name' => 'Science',
            'code' => 'SUB-0002',
        ]);

        $this->assertDatabaseHas('school_subjects', [
            'school_id' => $schoolB->id,
            'name' => 'History',
            'code' => 'SUB-0001',
        ]);
    }

    public function test_manager_can_assign_subject_to_educational_teacher_even_without_staff_role_assignment(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-971003');

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $school->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Physics',
                'is_active' => true,
                'teacher_user_ids' => [$teacher->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $subject = SchoolSubject::query()
            ->where('school_id', $school->id)
            ->where('name', 'Physics')
            ->firstOrFail();

        $this->assertDatabaseHas('school_subject_teacher_assignments', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'academic_planning.subject.created',
            'entity_type' => 'school_subject',
            'entity_id' => $subject->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_subject_branches_can_be_saved_and_updated(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-971013');

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Arabic',
                'is_active' => true,
                'branches' => ['نحو', ' نصوص ', 'نحو', '', 'قراءة'],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $subject = SchoolSubject::query()
            ->where('school_id', $school->id)
            ->where('name', 'Arabic')
            ->firstOrFail();

        $this->assertSame(['نحو', 'نصوص', 'قراءة'], $subject->branches);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->put(route('school.academic_planning.subjects.update', $subject->id), [
                'name' => 'Arabic',
                'code' => $subject->code,
                'is_active' => true,
                'branches' => ['بلاغة', 'نصوص', 'بلاغة'],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $subject->refresh();
        $this->assertSame(['بلاغة', 'نصوص'], $subject->branches);
    }

    public function test_manager_can_assign_subject_to_legacy_teacher_role_without_staff_type(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-971011');

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $school->id,
            'school_staff_type' => null,
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Legacy Teacher Subject',
                'is_active' => true,
                'teacher_user_ids' => [$teacher->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $subject = SchoolSubject::query()
            ->where('school_id', $school->id)
            ->where('name', 'Legacy Teacher Subject')
            ->firstOrFail();

        $this->assertDatabaseHas('school_subject_teacher_assignments', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);
    }

    public function test_manager_can_assign_subject_to_staff_user_with_teacher_department_role_name(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-971012');

        $department = Department::query()->create([
            'name' => 'المعلمين',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::query()->create([
            'department_id' => $department->id,
            'name' => 'معلم',
            'is_active' => true,
        ]);

        $teacherLikeStaff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Role Name Teacher Subject',
                'is_active' => true,
                'teacher_user_ids' => [$teacherLikeStaff->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $subject = SchoolSubject::query()
            ->where('school_id', $school->id)
            ->where('name', 'Role Name Teacher Subject')
            ->firstOrFail();

        $this->assertDatabaseHas('school_subject_teacher_assignments', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacherLikeStaff->id,
        ]);
    }

    public function test_subject_creation_rejects_foreign_school_teacher_assignment(): void
    {
        [$manager, $schoolA] = $this->createManagerWithSchool('SCH-971004');
        [, $schoolB] = $this->createManagerWithSchool('SCH-971005');

        $foreignTeacher = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $schoolB->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Chemistry',
                'is_active' => true,
                'teacher_user_ids' => [$foreignTeacher->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('teacher_user_ids.0');

        $this->assertDatabaseMissing('school_subject_teacher_assignments', [
            'school_id' => $schoolA->id,
            'teacher_user_id' => $foreignTeacher->id,
        ]);
    }

    public function test_subject_creation_rejects_non_teacher_user_assignment(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-971006');

        $nonTeacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'school_staff_type' => User::SCHOOL_STAFF_ADMINISTRATIVE,
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.store'), [
                'name' => 'Biology',
                'is_active' => true,
                'teacher_user_ids' => [$nonTeacher->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('teacher_user_ids');
    }

    public function test_manager_cannot_update_subject_in_another_school(): void
    {
        [, $schoolA] = $this->createManagerWithSchool('SCH-971009');
        [$managerB] = $this->createManagerWithSchool('SCH-971010');

        $subject = SchoolSubject::query()->create([
            'school_id' => $schoolA->id,
            'name' => 'Geography',
            'code' => 'SUB-9001',
            'is_active' => true,
        ]);

        $this->actingAs($managerB)
            ->put(route('school.academic_planning.subjects.update', $subject->id), [
                'name' => 'Geography Updated',
                'code' => 'SUB-9001',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('school_subjects', [
            'id' => $subject->id,
            'school_id' => $schoolA->id,
            'name' => 'Geography',
            'code' => 'SUB-9001',
        ]);
    }

    public function test_updating_subject_teachers_tracks_diff_and_writes_audit_log(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-971007');

        $subject = SchoolSubject::query()->create([
            'school_id' => $school->id,
            'name' => 'Algebra',
            'code' => 'SUB-1001',
            'is_active' => true,
        ]);

        $teacherA = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $school->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);

        $teacherB = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $school->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);

        $teacherC = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $school->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.teachers.sync', $subject->id), [
                'teacher_user_ids' => [$teacherA->id, $teacherB->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->put(route('school.academic_planning.subjects.update', $subject->id), [
                'name' => 'Algebra',
                'code' => 'SUB-1001',
                'is_active' => true,
                'teacher_user_ids' => [$teacherA->id, $teacherC->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseHas('school_subject_teacher_assignments', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacherA->id,
        ]);
        $this->assertDatabaseHas('school_subject_teacher_assignments', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacherC->id,
        ]);
        $this->assertDatabaseMissing('school_subject_teacher_assignments', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacherB->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'academic_planning.subject.updated',
            'entity_type' => 'school_subject',
            'entity_id' => $subject->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_updating_subject_without_teacher_payload_keeps_existing_assignments(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-971008');

        $subject = SchoolSubject::query()->create([
            'school_id' => $school->id,
            'name' => 'Geometry',
            'code' => 'SUB-2001',
            'is_active' => true,
        ]);

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $school->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.subjects.teachers.sync', $subject->id), [
                'teacher_user_ids' => [$teacher->id],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->put(route('school.academic_planning.subjects.update', $subject->id), [
                'name' => 'Geometry Updated',
                'code' => 'SUB-2001',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseHas('school_subject_teacher_assignments', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);
    }

    /**
     * @return array{0: User, 1: School}
     */
    private function createManagerWithSchool(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $phone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

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
            'phone' => $phone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [$manager->fresh(), $school];
    }
}

