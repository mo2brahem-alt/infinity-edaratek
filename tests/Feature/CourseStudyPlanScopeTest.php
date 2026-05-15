<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseStudyPlanScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_can_create_course_offering_with_study_plan_and_alert_days(): void
    {
        $context = $this->createPlannerContext();

        $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'sort_order' => 3,
                'usable_in_exams' => true,
                'is_active' => true,
                'alert_before_term_end_days' => 7,
                'study_plan_units' => [
                    [
                        'branch_name' => 'نحو',
                        'name' => 'الوحدة الأولى',
                        'sort_order' => 1,
                        'start_date' => '2026-09-05',
                        'end_date' => '2026-09-20',
                        'notes' => 'ملاحظات الوحدة',
                        'lessons' => [
                            [
                                'name' => 'الدرس الأول',
                                'sort_order' => 1,
                                'description' => 'وصف مختصر',
                                'topics' => [
                                    [
                                        'name' => 'الموضوع الأول',
                                        'sort_order' => 1,
                                        'description' => 'وصف الموضوع',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $offering = SchoolCourseOffering::query()
            ->where('school_id', $context['school']->id)
            ->where('school_term_id', $context['term']->id)
            ->where('school_stage_id', $context['stage']->id)
            ->where('school_stage_grade_id', $context['gradeA']->id)
            ->where('school_subject_id', $context['subject']->id)
            ->firstOrFail();

        $this->assertSame((int) $context['classroomA1']->id, (int) $offering->school_classroom_id);

        $this->assertDatabaseHas('school_course_offerings', [
            'id' => $offering->id,
            'school_id' => $context['school']->id,
            'school_stage_grade_id' => $context['gradeA']->id,
            'alert_before_term_end_days' => 7,
        ]);

        $this->assertDatabaseHas('school_course_plan_units', [
            'school_id' => $context['school']->id,
            'school_course_offering_id' => $offering->id,
            'branch_name' => 'نحو',
            'name' => 'الوحدة الأولى',
        ]);
        $this->assertDatabaseHas('school_course_plan_lessons', [
            'school_id' => $context['school']->id,
            'name' => 'الدرس الأول',
        ]);
        $this->assertDatabaseHas('school_course_plan_topics', [
            'school_id' => $context['school']->id,
            'name' => 'الموضوع الأول',
        ]);
    }

    public function test_course_offering_creation_fails_when_unit_starts_before_term(): void
    {
        $context = $this->createPlannerContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 5,
                'study_plan_units' => [
                    [
                        'name' => 'وحدة خارج الترم',
                        'start_date' => '2026-08-25',
                        'end_date' => '2026-09-10',
                        'lessons' => [],
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('study_plan_units');
    }

    public function test_course_offering_creation_fails_when_unit_branch_is_not_configured_for_subject(): void
    {
        $context = $this->createPlannerContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 5,
                'study_plan_units' => [
                    [
                        'branch_name' => 'فرع غير معتمد',
                        'name' => 'وحدة غير صالحة',
                        'start_date' => '2026-09-05',
                        'end_date' => '2026-09-10',
                        'lessons' => [],
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors([
                'study_plan_units.0.branch_name' => 'لا يمكن حفظ الوحدة لأن الفرع المحدد لا يتبع المادة المختارة.',
            ]);
    }

    public function test_course_offering_creation_fails_when_stage_grade_outside_school_scope(): void
    {
        $context = $this->createPlannerContext();
        $foreignSchool = $this->createForeignSchoolContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $foreignSchool['grade']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 2,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_stage_grade_id');
    }

    public function test_course_offering_creation_fails_when_unit_ends_after_term(): void
    {
        $context = $this->createPlannerContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 5,
                'study_plan_units' => [
                    [
                        'name' => 'وحدة خارج الترم',
                        'start_date' => '2026-12-20',
                        'end_date' => '2027-01-10',
                        'lessons' => [],
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('study_plan_units');
    }

    public function test_course_offering_creation_fails_when_multiple_units_exceed_term_boundaries(): void
    {
        $context = $this->createPlannerContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 5,
                'study_plan_units' => [
                    [
                        'name' => 'وحدة داخل الترم',
                        'start_date' => '2026-09-05',
                        'end_date' => '2026-09-15',
                        'lessons' => [],
                    ],
                    [
                        'name' => 'وحدة خارج الترم',
                        'start_date' => '2027-01-02',
                        'end_date' => '2027-01-06',
                        'lessons' => [],
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors([
                'study_plan_units' => 'لا يمكن حفظ الخطة الدراسية لأن تواريخ الوحدات تتجاوز حدود الترم.',
            ]);
    }

    public function test_course_offering_creation_fails_when_alert_days_exceed_term_length(): void
    {
        $context = $this->createPlannerContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 500,
                'study_plan_units' => [
                    [
                        'name' => 'وحدة داخل الترم',
                        'start_date' => '2026-09-03',
                        'end_date' => '2026-09-10',
                        'lessons' => [],
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('alert_before_term_end_days');
    }

    public function test_course_offering_creation_fails_when_study_plan_is_missing(): void
    {
        $context = $this->createPlannerContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 2,
                'study_plan_units' => [],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors([
                'study_plan_units' => 'لا يمكن إضافة المقرر بدون إضافة خطة دراسية واحدة على الأقل.',
            ]);
    }

    public function test_course_offering_creation_fails_when_alert_days_is_not_integer(): void
    {
        $context = $this->createPlannerContext();

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'school_stage_grade_id' => $context['gradeA']->id,
                'school_subject_id' => $context['subject']->id,
                'alert_before_term_end_days' => 'غير_صالح',
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('alert_before_term_end_days');
    }

    public function test_assignment_can_scope_teacher_to_selected_classrooms_within_same_grade(): void
    {
        $context = $this->createPlannerContext();

        $offering = SchoolCourseOffering::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_stage_grade_id' => $context['gradeA']->id,
            'school_classroom_id' => $context['classroomA1']->id,
            'school_subject_id' => $context['subject']->id,
            'is_active' => true,
            'usable_in_exams' => true,
            'sort_order' => 1,
            'alert_before_term_end_days' => 5,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $offering->id), [
                'teacher_user_id' => $context['teacher']->id,
                'school_classroom_ids' => [
                    $context['classroomA1']->id,
                    $context['classroomA2']->id,
                ],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $assignment = SchoolTeachingAssignment::query()
            ->where('school_id', $context['school']->id)
            ->where('school_course_offering_id', $offering->id)
            ->where('teacher_user_id', $context['teacher']->id)
            ->firstOrFail();

        $this->assertDatabaseHas('school_teaching_assignment_classrooms', [
            'school_id' => $context['school']->id,
            'school_teaching_assignment_id' => $assignment->id,
            'school_classroom_id' => $context['classroomA1']->id,
        ]);
        $this->assertDatabaseHas('school_teaching_assignment_classrooms', [
            'school_id' => $context['school']->id,
            'school_teaching_assignment_id' => $assignment->id,
            'school_classroom_id' => $context['classroomA2']->id,
        ]);
    }

    public function test_assignment_rejects_classrooms_outside_grade_and_outside_school_scope(): void
    {
        $context = $this->createPlannerContext();

        $offering = SchoolCourseOffering::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_stage_grade_id' => $context['gradeA']->id,
            'school_classroom_id' => $context['classroomA1']->id,
            'school_subject_id' => $context['subject']->id,
            'is_active' => true,
            'usable_in_exams' => true,
            'sort_order' => 1,
        ]);

        $outsideGradeResponse = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $offering->id), [
                'teacher_user_id' => $context['teacher']->id,
                'school_classroom_ids' => [
                    $context['classroomB1']->id,
                ],
            ]);

        $outsideGradeResponse
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_classroom_ids');

        $foreignSchool = $this->createForeignSchoolContext();
        $outsideSchoolResponse = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $offering->id), [
                'teacher_user_id' => $context['teacher']->id,
                'school_classroom_ids' => [
                    $foreignSchool['classroom']->id,
                ],
            ]);

        $outsideSchoolResponse
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_classroom_ids.0');
    }

    private function createPlannerContext(): array
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنظيم',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق أكاديمي',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School Main',
            'school_id' => 'SCH-PLAN-001',
            'phone' => '0500001111',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacher->assignRole('staff');

        $term = SchoolTerm::create([
            'school_id' => $school->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $gradeA = SchoolStageGrade::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'الصف الأول',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $gradeB = SchoolStageGrade::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'الصف الثاني',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $classroomA1 = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => $gradeA->name,
            'name' => '1-A',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $classroomA2 = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => $gradeA->name,
            'name' => '1-B',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $classroomB1 = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => $gradeB->name,
            'name' => '2-A',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $subject = SchoolSubject::create([
            'school_id' => $school->id,
            'name' => 'الرياضيات',
            'code' => 'MATH-1',
            'branches' => ['نحو', 'نصوص', 'قراءة'],
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        return compact(
            'school',
            'planner',
            'teacher',
            'term',
            'stage',
            'gradeA',
            'gradeB',
            'classroomA1',
            'classroomA2',
            'classroomB1',
            'subject'
        );
    }

    private function createForeignSchoolContext(): array
    {
        $region = EducationalDirectorate::query()->first() ?? EducationalDirectorate::create([
            'name' => 'Foreign Region',
            'governorate' => 'Jeddah',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School Foreign',
            'school_id' => 'SCH-PLAN-999',
            'phone' => '0500009999',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $grade = SchoolStageGrade::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'الصف الأول',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => $grade->name,
            'name' => 'X-1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return compact('school', 'stage', 'grade', 'classroom');
    }
}
