<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPlanningAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_access_academic_planning_and_create_term_for_his_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Planning School',
            'school_id' => 'SCH-960001',
            'phone' => '0500009601',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->get(route('school.academic_planning.index'))
            ->assertOk();

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.terms.store'), [
                'name' => 'الترم الأول 2026',
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-15',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseHas('school_terms', [
            'school_id' => $school->id,
            'name' => 'الترم الأول 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-15',
        ]);
    }

    public function test_staff_with_configured_permission_can_access_academic_planning(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'West',
            'governorate' => 'Makkah',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-960002',
            'phone' => '0500009602',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $department = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'موظف الجداول',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('school.academic_planning.index'))
            ->assertOk();
    }

    public function test_staff_without_permission_is_forbidden_from_academic_planning(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'North',
            'governorate' => 'Tabuk',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-960003',
            'phone' => '0500009603',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $department = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'موظف إداري',
            'is_active' => true,
            'can_manage_academic_planning' => false,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('school.academic_planning.index'))
            ->assertForbidden();
    }

    public function test_approved_courses_tree_contains_current_school_courses_only(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Approved Courses Tree Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Approved Courses School A',
            'school_id' => 'SCH-960030',
            'phone' => '0500009630',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Approved Courses School B',
            'school_id' => 'SCH-960031',
            'phone' => '0500009631',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        $teacherA = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
        ]);
        $teacherA->assignRole('staff');

        $teacherB = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolB->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
        ]);
        $teacherB->assignRole('staff');

        $termA = SchoolTerm::create([
            'school_id' => $schoolA->id,
            'name' => 'Term A',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $termB = SchoolTerm::create([
            'school_id' => $schoolB->id,
            'name' => 'Term B',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Primary A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Primary B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $gradeA = SchoolStageGrade::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'name' => 'First Grade A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $gradeB = SchoolStageGrade::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => 'First Grade B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'grade_name' => $gradeA->name,
            'name' => 'A1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'grade_name' => $gradeB->name,
            'name' => 'B1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subjectA = SchoolSubject::create([
            'school_id' => $schoolA->id,
            'name' => 'Mathematics A',
            'code' => 'MATH-A',
            'is_active' => true,
        ]);

        $subjectB = SchoolSubject::create([
            'school_id' => $schoolB->id,
            'name' => 'Mathematics B',
            'code' => 'MATH-B',
            'is_active' => true,
        ]);

        $offeringA = SchoolCourseOffering::create([
            'school_id' => $schoolA->id,
            'school_term_id' => $termA->id,
            'school_stage_id' => $stageA->id,
            'school_stage_grade_id' => $gradeA->id,
            'school_classroom_id' => $classroomA->id,
            'school_subject_id' => $subjectA->id,
            'is_active' => true,
            'usable_in_exams' => true,
            'sort_order' => 1,
        ]);

        $offeringB = SchoolCourseOffering::create([
            'school_id' => $schoolB->id,
            'school_term_id' => $termB->id,
            'school_stage_id' => $stageB->id,
            'school_stage_grade_id' => $gradeB->id,
            'school_classroom_id' => $classroomB->id,
            'school_subject_id' => $subjectB->id,
            'is_active' => true,
            'usable_in_exams' => true,
            'sort_order' => 1,
        ]);

        SchoolTeachingAssignment::create([
            'school_id' => $schoolA->id,
            'school_course_offering_id' => $offeringA->id,
            'teacher_user_id' => $teacherA->id,
            'is_active' => true,
            'can_create_exam' => true,
            'can_update_exam' => true,
            'can_delete_exam' => false,
            'can_approve_exam' => false,
            'can_enter_exam_scores' => true,
            'can_edit_exam_scores' => true,
            'can_use_question_bank' => true,
        ]);

        SchoolTeachingAssignment::create([
            'school_id' => $schoolB->id,
            'school_course_offering_id' => $offeringB->id,
            'teacher_user_id' => $teacherB->id,
            'is_active' => true,
        ]);

        $this->actingAs($managerA)
            ->get(route('school.academic_planning.index', ['page' => 'subjects']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/AcademicPlanning')
                ->has('approvedCoursesTree', 1)
                ->where('approvedCoursesTree.0.id', $stageA->id)
                ->where('approvedCoursesTree.0.grades_count', 1)
                ->where('approvedCoursesTree.0.courses_count', 1)
                ->where('approvedCoursesTree.0.grades.0.id', $gradeA->id)
                ->where('approvedCoursesTree.0.grades.0.terms_count', 1)
                ->where('approvedCoursesTree.0.grades.0.terms.0.id', $termA->id)
                ->where('approvedCoursesTree.0.grades.0.terms.0.courses.0.id', $offeringA->id)
                ->where('approvedCoursesTree.0.grades.0.terms.0.courses.0.subject_name', 'Mathematics A')
                ->where('approvedCoursesTree.0.grades.0.terms.0.courses.0.teacher_name', $teacherA->name)
                ->has('courseAssignmentsTree', 1)
                ->where('courseAssignmentsTree.0.id', $stageA->id)
                ->where('courseAssignmentsTree.0.grades.0.id', $gradeA->id)
                ->where('courseAssignmentsTree.0.grades.0.terms.0.id', $termA->id)
                ->where('courseAssignmentsTree.0.grades.0.terms.0.courses.0.id', $offeringA->id)
                ->where('courseAssignmentsTree.0.grades.0.terms.0.courses.0.teacher_name', $teacherA->name)
            );
    }

    public function test_schedule_listing_supports_grade_filter_within_current_school_only(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Grade Filter Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Grade Filter School A',
            'school_id' => 'SCH-960040',
            'phone' => '0500009640',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Grade Filter School B',
            'school_id' => 'SCH-960041',
            'phone' => '0500009641',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        $teachingDepartment = Department::create([
            'name' => 'قسم المعلمين - فلتر الصف',
            'staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'school_id' => null,
        ]);

        $teacherA = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'department_id' => $teachingDepartment->id,
        ]);
        $teacherA->assignRole('staff');

        $teacherB = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolB->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'department_id' => $teachingDepartment->id,
        ]);
        $teacherB->assignRole('staff');

        $termA = SchoolTerm::create([
            'school_id' => $schoolA->id,
            'name' => 'Term A',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $termB = SchoolTerm::create([
            'school_id' => $schoolB->id,
            'name' => 'Term B',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Primary A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Primary B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA1 = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'grade_name' => 'الصف الأول',
            'name' => 'A1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA2 = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'grade_name' => 'الصف الثاني',
            'name' => 'A2',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $classroomB1 = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'grade_name' => 'الصف الأول',
            'name' => 'B1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subjectA = SchoolSubject::create([
            'school_id' => $schoolA->id,
            'name' => 'رياضيات',
            'code' => 'MATH-A',
            'is_active' => true,
        ]);

        $subjectB = SchoolSubject::create([
            'school_id' => $schoolB->id,
            'name' => 'علوم',
            'code' => 'SCI-B',
            'is_active' => true,
        ]);

        SchoolClassSchedule::create([
            'school_id' => $schoolA->id,
            'school_term_id' => $termA->id,
            'school_stage_id' => $stageA->id,
            'school_classroom_id' => $classroomA1->id,
            'school_subject_id' => $subjectA->id,
            'teacher_user_id' => $teacherA->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
            'is_active' => true,
        ]);

        SchoolClassSchedule::create([
            'school_id' => $schoolA->id,
            'school_term_id' => $termA->id,
            'school_stage_id' => $stageA->id,
            'school_classroom_id' => $classroomA2->id,
            'school_subject_id' => $subjectA->id,
            'teacher_user_id' => $teacherA->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 2,
            'session_index' => 2,
            'is_active' => true,
        ]);

        SchoolClassSchedule::create([
            'school_id' => $schoolB->id,
            'school_term_id' => $termB->id,
            'school_stage_id' => $stageB->id,
            'school_classroom_id' => $classroomB1->id,
            'school_subject_id' => $subjectB->id,
            'teacher_user_id' => $teacherB->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($managerA)
            ->get(route('school.academic_planning.index', [
                'term_id' => $termA->id,
                'scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'grade_name' => 'الصف الأول',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/AcademicPlanning')
                ->where('selectedGradeName', 'الصف الأول')
                ->has('schedules', 1)
                ->where('schedules.0.school_id', $schoolA->id)
                ->where('schedules.0.school_classroom_id', $classroomA1->id)
                ->where('schedules.0.classroom.grade_name', 'الصف الأول')
            );
    }

    public function test_schedule_update_is_scoped_to_user_school_only(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق الجداول',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'South',
            'governorate' => 'Jazan',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-960004',
            'phone' => '0500009604',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-960005',
            'phone' => '0500009605',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $teacherA = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'department_id' => $department->id,
        ]);
        $teacherA->assignRole('staff');

        $teacherB = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolB->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'department_id' => $department->id,
        ]);
        $teacherB->assignRole('staff');

        $termA = SchoolTerm::create([
            'school_id' => $schoolA->id,
            'name' => 'Term A',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'name' => '1A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subjectA = SchoolSubject::create([
            'school_id' => $schoolA->id,
            'name' => 'رياضيات',
            'code' => 'MATH-A',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $schoolA->id,
            'school_subject_id' => $subjectA->id,
            'teacher_user_id' => $teacherA->id,
        ]);

        $termB = SchoolTerm::create([
            'school_id' => $schoolB->id,
            'name' => 'Term B',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Middle',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => '2B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subjectB = SchoolSubject::create([
            'school_id' => $schoolB->id,
            'name' => 'علوم',
            'code' => 'SCI-B',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $schoolB->id,
            'school_subject_id' => $subjectB->id,
            'teacher_user_id' => $teacherB->id,
        ]);

        $foreignSchedule = SchoolClassSchedule::create([
            'school_id' => $schoolB->id,
            'school_term_id' => $termB->id,
            'school_stage_id' => $stageB->id,
            'school_classroom_id' => $classroomB->id,
            'school_subject_id' => $subjectB->id,
            'teacher_user_id' => $teacherB->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($planner)
            ->put(route('school.academic_planning.schedules.update', $foreignSchedule->id), [
                'school_term_id' => $termA->id,
                'school_stage_id' => $stageA->id,
                'school_classroom_id' => $classroomA->id,
                'school_subject_id' => $subjectA->id,
                'teacher_user_id' => $teacherA->id,
                'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'day_of_week' => 2,
                'session_index' => 3,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('school_class_schedules', [
            'id' => $foreignSchedule->id,
            'school_id' => $schoolB->id,
            'school_term_id' => $termB->id,
            'school_subject_id' => $subjectB->id,
        ]);
    }
}
