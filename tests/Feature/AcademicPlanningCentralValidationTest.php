<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolCalendarSetting;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolHoliday;
use App\Models\SchoolStage;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPlanningCentralValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_can_be_created_on_valid_working_day_within_same_school(): void
    {
        $context = $this->createPlanningContext('SCH-995001');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'day_of_week' => 1,
                'session_index' => 2,
                'starts_at' => '09:00',
                'ends_at' => '09:45',
            ]));

        $response->assertRedirect();

        $this->assertDatabaseHas('school_class_schedules', [
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 2,
        ]);
    }

    public function test_schedule_creation_fails_for_weekly_off_day_with_arabic_message(): void
    {
        $context = $this->createPlanningContext('SCH-995002');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'day_of_week' => 6,
                'session_index' => 3,
            ]));

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('day_of_week');

        $this->assertStringContainsString(
            'عطلة أسبوعية',
            (string) session('errors')->first('day_of_week')
        );
    }

    public function test_schedule_creation_fails_when_term_session_date_is_holiday(): void
    {
        $context = $this->createPlanningContext('SCH-995003');

        SchoolHoliday::query()->create([
            'school_id' => $context['school']->id,
            'name' => 'اليوم الوطني',
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-15',
            'return_date' => '2026-10-16',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'schedule_scope' => SchoolClassSchedule::SCOPE_TERM,
                'session_date' => '2026-10-15',
                'day_of_week' => null,
                'session_index' => 4,
            ]));

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('session_date');

        $this->assertStringContainsString(
            'عطلة رسمية',
            (string) session('errors')->first('session_date')
        );
    }

    public function test_schedule_creation_fails_when_term_session_date_is_exceptional_leave_day(): void
    {
        $context = $this->createPlanningContext('SCH-995004');

        SchoolHoliday::query()->create([
            'school_id' => $context['school']->id,
            'name' => 'إجازة استثنائية',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
            'return_date' => '2026-10-21',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'schedule_scope' => SchoolClassSchedule::SCOPE_TERM,
                'session_date' => '2026-10-20',
                'day_of_week' => null,
                'session_index' => 5,
            ]));

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('session_date');

        $this->assertStringContainsString(
            'عطلة رسمية أو استثنائية',
            (string) session('errors')->first('session_date')
        );
    }

    public function test_schedule_creation_fails_when_time_is_outside_school_day_window(): void
    {
        $context = $this->createPlanningContext('SCH-995005');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'day_of_week' => 1,
                'session_index' => 2,
                'starts_at' => '07:00',
                'ends_at' => '07:45',
            ]));

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('starts_at');

        $this->assertStringContainsString(
            'خارج مواعيد اليوم الدراسي',
            (string) session('errors')->first('starts_at')
        );
    }

    public function test_schedule_creation_fails_on_teacher_time_overlap_in_same_school(): void
    {
        $context = $this->createPlanningContext('SCH-995006');

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'school_classroom_id' => $context['classroomB']->id,
                'teacher_user_id' => $context['teacherA']->id,
                'day_of_week' => 1,
                'session_index' => 3,
                'starts_at' => '09:30',
                'ends_at' => '10:15',
            ]));

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('teacher_user_id');

        $this->assertStringContainsString(
            'المعلم مرتبط بجدول آخر',
            (string) session('errors')->first('teacher_user_id')
        );
    }

    public function test_schedule_creation_fails_on_classroom_time_overlap_in_same_school(): void
    {
        $context = $this->createPlanningContext('SCH-995007');

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 2,
            'session_index' => 1,
            'starts_at' => '10:00',
            'ends_at' => '10:45',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'school_classroom_id' => $context['classroomA']->id,
                'teacher_user_id' => $context['teacherB']->id,
                'day_of_week' => 2,
                'session_index' => 4,
                'starts_at' => '10:30',
                'ends_at' => '11:00',
            ]));

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_classroom_id');

        $this->assertStringContainsString(
            'الفصل مشغول',
            (string) session('errors')->first('school_classroom_id')
        );
    }

    public function test_same_schedule_slot_can_be_created_in_other_school_without_cross_tenant_conflict(): void
    {
        $contextA = $this->createPlanningContext('SCH-995008');
        $contextB = $this->createPlanningContext('SCH-995009');

        SchoolClassSchedule::query()->create([
            'school_id' => $contextA['school']->id,
            'school_term_id' => $contextA['term']->id,
            'school_stage_id' => $contextA['stage']->id,
            'school_classroom_id' => $contextA['classroomA']->id,
            'school_subject_id' => $contextA['subject']->id,
            'teacher_user_id' => $contextA['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 3,
            'session_index' => 2,
            'starts_at' => '09:00',
            'ends_at' => '09:45',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($contextB['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($contextB, [
                'day_of_week' => 3,
                'session_index' => 2,
                'starts_at' => '09:00',
                'ends_at' => '09:45',
            ]));

        $response->assertRedirect();

        $this->assertDatabaseHas('school_class_schedules', [
            'school_id' => $contextB['school']->id,
            'school_term_id' => $contextB['term']->id,
            'day_of_week' => 3,
            'session_index' => 2,
        ]);
    }

    public function test_schedule_creation_fails_when_referencing_classroom_from_another_school(): void
    {
        $contextA = $this->createPlanningContext('SCH-995010');
        $contextB = $this->createPlanningContext('SCH-995011');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($contextA['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($contextA, [
                'school_classroom_id' => $contextB['classroomA']->id,
            ]));

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_classroom_id');
    }

    public function test_inactive_schedule_does_not_block_same_slot_or_time_conflicts(): void
    {
        $context = $this->createPlanningContext('SCH-995012');

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 2,
            'starts_at' => '09:00',
            'ends_at' => '09:45',
            'is_active' => false,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.store'), $this->schedulePayload($context, [
                'school_classroom_id' => $context['classroomA']->id,
                'teacher_user_id' => $context['teacherA']->id,
                'day_of_week' => 1,
                'session_index' => 2,
                'starts_at' => '09:00',
                'ends_at' => '09:45',
            ]));

        $response
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('school_class_schedules', 2);
        $this->assertDatabaseHas('school_class_schedules', [
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'day_of_week' => 1,
            'session_index' => 2,
            'starts_at' => '09:00',
            'ends_at' => '09:45',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{
     *     planner:User,
     *     school:School,
     *     term:SchoolTerm,
     *     stage:SchoolStage,
     *     classroomA:SchoolClassroom,
     *     classroomB:SchoolClassroom,
     *     subject:SchoolSubject,
     *     teacherA:User,
     *     teacherB:User
     * }
     */
    private function createPlanningContext(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::query()->create([
            'name' => 'التنسيق الأكاديمي',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::query()->create([
            'department_id' => $department->id,
            'name' => 'منسق الجداول',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $phone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => $phone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $planner->assignRole('staff');

        $teacherA = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacherA->assignRole('staff');

        $teacherB = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacherB->assignRole('staff');

        $term = SchoolTerm::query()->create([
            'school_id' => $school->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
            'school_day_start_time' => '08:00:00',
            'school_day_end_time' => '14:00:00',
        ]);

        $classroomA = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '1A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '1B',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $subject = SchoolSubject::query()->create([
            'school_id' => $school->id,
            'name' => 'Mathematics',
            'code' => 'MATH-' . substr($schoolCode, -3),
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::query()->create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacherA->id,
        ]);

        SchoolSubjectTeacherAssignment::query()->create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacherB->id,
        ]);

        SchoolCalendarSetting::query()->create([
            'school_id' => $school->id,
            'week_start_day' => 0,
            'weekly_off_days' => [5, 6],
        ]);

        return [
            'planner' => $planner->fresh(),
            'school' => $school,
            'term' => $term,
            'stage' => $stage,
            'classroomA' => $classroomA,
            'classroomB' => $classroomB,
            'subject' => $subject,
            'teacherA' => $teacherA,
            'teacherB' => $teacherB,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function schedulePayload(array $context, array $overrides = []): array
    {
        return array_merge([
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'day_of_month' => null,
            'session_date' => null,
            'session_index' => 1,
            'starts_at' => '08:30',
            'ends_at' => '09:15',
            'is_active' => true,
        ], $overrides);
    }
}
