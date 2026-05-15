<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolCalendarSetting;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPlanningGridEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_grid_empty_selects_use_readable_placeholder_classes(): void
    {
        $content = file_get_contents(resource_path('js/Pages/School/AcademicPlanning.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('weekly-grid-select--empty', $content);
        $this->assertStringContainsString('--weekly-grid-select-placeholder-text', $content);
        $this->assertStringContainsString('اختر المادة', $content);
        $this->assertStringContainsString('اختر المعلم', $content);
    }

    public function test_weekly_grid_entries_are_loaded_for_selected_context(): void
    {
        $context = $this->createGridContext('SCH-996001');

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroom']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 2,
            'starts_at' => '08:30',
            'ends_at' => '09:15',
            'is_active' => true,
        ]);

        $this->actingAs($context['planner'])
            ->get(route('school.academic_planning.index', [
                'page' => 'schedules',
                'term_id' => $context['term']->id,
                'scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'stage_id' => $context['stage']->id,
                'grade_name' => $context['classroom']->grade_name,
                'classroom_id' => $context['classroom']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/AcademicPlanning')
                ->where('selectedStageId', $context['stage']->id)
                ->has('weeklyGrid.entries', 1)
                ->where('weeklyGrid.entries.0.school_subject_id', $context['subject']->id)
                ->where('weeklyGrid.entries.0.teacher_user_id', $context['teacherA']->id)
            );
    }

    public function test_grid_sync_can_create_update_and_delete_weekly_slots(): void
    {
        $context = $this->createGridContext('SCH-996002');

        $firstSlot = SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroom']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
            'starts_at' => '08:30',
            'ends_at' => '09:15',
            'is_active' => true,
        ]);

        $secondSlot = SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroom']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 2,
            'session_index' => 2,
            'starts_at' => '09:30',
            'ends_at' => '10:15',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.grid.sync'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'grade_name' => $context['classroom']->grade_name,
                'school_classroom_id' => $context['classroom']->id,
                'period_count' => 8,
                'cells' => [
                    [
                        'day_of_week' => 1,
                        'session_index' => 1,
                        'school_subject_id' => $context['subject']->id,
                        'teacher_user_id' => $context['teacherB']->id,
                        'starts_at' => '08:30',
                        'ends_at' => '09:15',
                        'notes' => 'تم تحديث المعلم',
                        'is_active' => true,
                    ],
                    [
                        'day_of_week' => 2,
                        'session_index' => 2,
                        'school_subject_id' => null,
                        'teacher_user_id' => null,
                        'starts_at' => null,
                        'ends_at' => null,
                        'notes' => null,
                        'is_active' => true,
                    ],
                    [
                        'day_of_week' => 3,
                        'session_index' => 4,
                        'school_subject_id' => $context['subject']->id,
                        'teacher_user_id' => $context['teacherA']->id,
                        'starts_at' => '10:30',
                        'ends_at' => '11:15',
                        'notes' => 'حصة جديدة',
                        'is_active' => true,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('school_class_schedules', [
            'id' => $firstSlot->id,
            'teacher_user_id' => $context['teacherB']->id,
            'notes' => 'تم تحديث المعلم',
        ]);

        $this->assertDatabaseMissing('school_class_schedules', [
            'id' => $secondSlot->id,
        ]);

        $this->assertDatabaseHas('school_class_schedules', [
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroom']->id,
            'day_of_week' => 3,
            'session_index' => 4,
            'teacher_user_id' => $context['teacherA']->id,
        ]);
    }

    public function test_grid_sync_rejects_weekly_off_days_server_side(): void
    {
        $context = $this->createGridContext('SCH-996003');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['planner'])
            ->post(route('school.academic_planning.schedules.grid.sync'), [
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'grade_name' => $context['classroom']->grade_name,
                'school_classroom_id' => $context['classroom']->id,
                'period_count' => 8,
                'cells' => [
                    [
                        'day_of_week' => 6,
                        'session_index' => 1,
                        'school_subject_id' => $context['subject']->id,
                        'teacher_user_id' => $context['teacherA']->id,
                        'starts_at' => '08:30',
                        'ends_at' => '09:15',
                        'notes' => null,
                        'is_active' => true,
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('day_of_week');
    }

    public function test_grid_sync_rejects_foreign_classroom_and_preserves_school_isolation(): void
    {
        $contextA = $this->createGridContext('SCH-996004');
        $contextB = $this->createGridContext('SCH-996005');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($contextA['planner'])
            ->post(route('school.academic_planning.schedules.grid.sync'), [
                'school_term_id' => $contextA['term']->id,
                'school_stage_id' => $contextA['stage']->id,
                'grade_name' => $contextB['classroom']->grade_name,
                'school_classroom_id' => $contextB['classroom']->id,
                'period_count' => 8,
                'cells' => [
                    [
                        'day_of_week' => 1,
                        'session_index' => 1,
                        'school_subject_id' => $contextA['subject']->id,
                        'teacher_user_id' => $contextA['teacherA']->id,
                        'starts_at' => '08:30',
                        'ends_at' => '09:15',
                        'notes' => null,
                        'is_active' => true,
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_classroom_id');

        $this->assertDatabaseMissing('school_class_schedules', [
            'school_id' => $contextB['school']->id,
            'school_classroom_id' => $contextB['classroom']->id,
            'session_index' => 1,
        ]);
    }

    public function test_word_export_returns_document_for_selected_weekly_grid(): void
    {
        $context = $this->createGridContext('SCH-996006');

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroom']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
            'starts_at' => '08:30',
            'ends_at' => '09:15',
            'is_active' => true,
        ]);

        $this->actingAs($context['planner'])
            ->get(route('school.academic_planning.schedules.grid.export', [
                'format' => 'word',
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'grade_name' => $context['classroom']->grade_name,
                'school_classroom_id' => $context['classroom']->id,
                'period_count' => 8,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/msword; charset=UTF-8')
            ->assertSeeText('الجدول الدراسي الأسبوعي');
    }

    public function test_pdf_export_returns_pdf_download_for_selected_weekly_grid(): void
    {
        $context = $this->createGridContext('SCH-996007');

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroom']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacherA']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 2,
            'session_index' => 3,
            'starts_at' => '09:30',
            'ends_at' => '10:15',
            'is_active' => true,
        ]);

        $this->actingAs($context['planner'])
            ->get(route('school.academic_planning.schedules.grid.export', [
                'format' => 'pdf',
                'school_term_id' => $context['term']->id,
                'school_stage_id' => $context['stage']->id,
                'grade_name' => $context['classroom']->grade_name,
                'school_classroom_id' => $context['classroom']->id,
                'period_count' => 8,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @return array{
     *     planner:User,
     *     school:School,
     *     term:SchoolTerm,
     *     stage:SchoolStage,
     *     classroom:SchoolClassroom,
     *     subject:SchoolSubject,
     *     teacherA:User,
     *     teacherB:User
     * }
     */
    private function createGridContext(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::query()->create([
            'name' => 'التنسيق الأكاديمي ' . $schoolCode,
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::query()->create([
            'department_id' => $department->id,
            'name' => 'منسق الجداول ' . $schoolCode,
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

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => '1A',
            'sort_order' => 1,
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
            'classroom' => $classroom,
            'subject' => $subject,
            'teacherA' => $teacherA,
            'teacherB' => $teacherB,
        ];
    }
}
